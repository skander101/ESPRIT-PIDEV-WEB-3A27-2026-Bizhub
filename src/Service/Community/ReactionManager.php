<?php

namespace App\Service\Community;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

class ReactionManager
{
    public const TYPES = ['LIKE', 'LOVE', 'CELEBRATE', 'SUPPORT', 'INSIGHTFUL', 'CURIOUS'];

    public function __construct(private readonly Connection $conn)
    {
    }

    /** @return array{counts: array<string,int>, total: int} */
    public function getCountsForPosts(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ($postIds === []) {
            return ['counts' => [], 'total' => 0];
        }

        $sql = 'SELECT post_id, type, COUNT(*) AS cnt
                FROM reaction
                WHERE post_id IN (:postIds)
                GROUP BY post_id, type';

        $rows = $this->conn->executeQuery(
            $sql,
            ['postIds' => $postIds],
            ['postIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $byPost = [];
        foreach ($rows as $r) {
            $pid = (int) $r['post_id'];
            $type = (string) $r['type'];
            $cnt = (int) $r['cnt'];
            $byPost[$pid][$type] = $cnt;
        }

        return ['counts' => $byPost, 'total' => 0];
    }

    /** @return array<int, string> postId => type */
    public function getUserReactionsForPosts(array $postIds, int $userId): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ($postIds === []) {
            return [];
        }

        $sql = 'SELECT post_id, type
                FROM reaction
                WHERE user_id = :uid AND post_id IN (:postIds)';

        $rows = $this->conn->executeQuery(
            $sql,
            ['uid' => $userId, 'postIds' => $postIds],
            ['uid' => ParameterType::INTEGER, 'postIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['post_id']] = (string) $r['type'];
        }
        return $out;
    }

    /** @return array{userReaction: string|null, counts: array<string,int>, total: int} */
    public function toggleReaction(int $postId, int $userId, string $type): array
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid reaction type');
        }

        $this->conn->beginTransaction();
        try {
            $existing = $this->conn->fetchAssociative(
                'SELECT reaction_id, type FROM reaction WHERE post_id = :pid AND user_id = :uid',
                ['pid' => $postId, 'uid' => $userId]
            );

            $userReaction = null;
            if (!$existing) {
                $this->conn->insert('reaction', [
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'type' => $type,
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
                $userReaction = $type;
            } else {
                $existingType = (string) $existing['type'];
                $rid = (int) $existing['reaction_id'];

                if (strtoupper($existingType) === $type) {
                    $this->conn->delete('reaction', ['reaction_id' => $rid]);
                    $userReaction = null;
                } else {
                    $this->conn->update('reaction', [
                        'type' => $type,
                        'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ], ['reaction_id' => $rid]);
                    $userReaction = $type;
                }
            }

            $this->conn->commit();

            $counts = $this->getCountsForPost($postId);
            $total = array_sum($counts);

            return ['userReaction' => $userReaction, 'counts' => $counts, 'total' => $total];
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** @return array<string,int> */
    public function getCountsForPost(int $postId): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT type, COUNT(*) AS cnt FROM reaction WHERE post_id = :pid GROUP BY type',
            ['pid' => $postId]
        );

        $out = array_fill_keys(self::TYPES, 0);
        foreach ($rows as $r) {
            $t = (string) $r['type'];
            if (array_key_exists($t, $out)) {
                $out[$t] = (int) $r['cnt'];
            }
        }
        return $out;
    }
}

