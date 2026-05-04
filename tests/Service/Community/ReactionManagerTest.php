<?php

declare(strict_types=1);

namespace App\Tests\Service\Community;

use App\Service\Community\ReactionManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReactionManagerTest extends TestCase
{
    private Connection&MockObject $conn;
    private ReactionManager $manager;

    protected function setUp(): void
    {
        $this->conn = $this->createMock(Connection::class);
        $this->manager = new ReactionManager($this->conn);
    }

    public function testToggleReactionRejectsInvalidType(): void
    {
        $this->conn->expects(self::never())->method('beginTransaction');

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->toggleReaction(1, 1, 'invalid_type');
    }

    public function testToggleReactionInsertsThenDeletesWhenToggledTwice(): void
    {
        $postId = 10;
        $userId = 7;

        $this->conn->expects(self::exactly(2))->method('beginTransaction');
        $this->conn->expects(self::exactly(2))->method('commit');

        $this->conn->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->with(
                self::stringContains('SELECT reaction_id, type FROM reaction'),
                self::callback(static function (array $params) use ($postId, $userId): bool {
                    return (int) ($params['pid'] ?? 0) === $postId
                        && (int) ($params['uid'] ?? 0) === $userId;
                })
            )
            ->willReturnOnConsecutiveCalls(
                false,
                ['reaction_id' => 55, 'type' => 'LIKE']
            );

        $this->conn->expects(self::once())
            ->method('insert')
            ->with(
                'reaction',
                self::callback(static function (array $data) use ($postId, $userId): bool {
                    $createdAt = $data['created_at'] ?? null;

                    return (int) ($data['post_id'] ?? 0) === $postId
                        && (int) ($data['user_id'] ?? 0) === $userId
                        && (string) ($data['type'] ?? '') === 'LIKE'
                        && is_string($createdAt)
                        && $createdAt !== '';
                })
            );

        $this->conn->expects(self::once())
            ->method('delete')
            ->with('reaction', ['reaction_id' => 55]);

        $this->conn->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('SELECT type, COUNT(*) AS cnt FROM reaction'),
                self::callback(static function (array $params) use ($postId): bool {
                    return (int) ($params['pid'] ?? 0) === $postId;
                })
            )
            ->willReturnOnConsecutiveCalls(
                [['type' => 'LIKE', 'cnt' => 1]],
                []
            );

        /** @var array{userReaction: string|null, counts: array<string,int>, total: int} $res1 */
        $res1 = $this->manager->toggleReaction($postId, $userId, 'like');
        self::assertSame('LIKE', $res1['userReaction']);
        self::assertSame(1, $res1['counts']['LIKE']);
        self::assertSame(1, $res1['total']);

        /** @var array{userReaction: string|null, counts: array<string,int>, total: int} $res2 */
        $res2 = $this->manager->toggleReaction($postId, $userId, 'LIKE');
        self::assertNull($res2['userReaction']);
        self::assertSame(0, $res2['counts']['LIKE']);
        self::assertSame(0, $res2['total']);
    }

    public function testToggleReactionUpdatesTypeWhenDifferent(): void
    {
        $postId = 20;
        $userId = 8;

        $this->conn->expects(self::exactly(2))->method('beginTransaction');
        $this->conn->expects(self::exactly(2))->method('commit');

        $this->conn->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                false,
                ['reaction_id' => 99, 'type' => 'LIKE']
            );

        $this->conn->expects(self::once())
            ->method('insert')
            ->with(
                'reaction',
                self::callback(static function (array $data) use ($postId, $userId): bool {
                    $createdAt = $data['created_at'] ?? null;

                    return (int) ($data['post_id'] ?? 0) === $postId
                        && (int) ($data['user_id'] ?? 0) === $userId
                        && (string) ($data['type'] ?? '') === 'LIKE'
                        && is_string($createdAt)
                        && $createdAt !== '';
                })
            );

        $this->conn->expects(self::once())
            ->method('update')
            ->with(
                'reaction',
                self::callback(static function (array $data): bool {
                    $createdAt = $data['created_at'] ?? null;

                    return (string) ($data['type'] ?? '') === 'LOVE'
                        && is_string($createdAt)
                        && $createdAt !== '';
                }),
                ['reaction_id' => 99]
            );

        $this->conn->expects(self::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['type' => 'LIKE', 'cnt' => 1]],
                [['type' => 'LOVE', 'cnt' => 1]]
            );

        /** @var array{userReaction: string|null, counts: array<string,int>, total: int} $res1 */
        $res1 = $this->manager->toggleReaction($postId, $userId, 'like');
        self::assertSame('LIKE', $res1['userReaction']);

        /** @var array{userReaction: string|null, counts: array<string,int>, total: int} $res2 */
        $res2 = $this->manager->toggleReaction($postId, $userId, 'love');
        self::assertSame('LOVE', $res2['userReaction']);
        self::assertSame(1, $res2['counts']['LOVE']);
        self::assertSame(0, $res2['counts']['LIKE']);
        self::assertSame(1, $res2['total']);
    }

    public function testGetCountsForPostsAggregatesByPostAndType(): void
    {
        $result = $this->createMock(Result::class);
        /** @var Result&MockObject $result */
        $result->method('fetchAllAssociative')->willReturn([
            ['post_id' => 1, 'type' => 'LIKE', 'cnt' => 2],
            ['post_id' => 1, 'type' => 'LOVE', 'cnt' => 1],
            ['post_id' => 2, 'type' => 'CURIOUS', 'cnt' => 1],
        ]);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with(
                self::stringContains('FROM reaction'),
                self::callback(static function (array $params): bool {
                    return ($params['postIds'] ?? null) === [1, 2];
                }),
                self::callback(static function (array $types): bool {
                    return array_key_exists('postIds', $types);
                })
            )
            ->willReturn($result);

        /** @var array{counts: array<int, array<string,int>>, total: int} $res */
        $res = $this->manager->getCountsForPosts([1, 2, 1]);

        self::assertSame(0, $res['total']);

        self::assertSame(2, $res['counts'][1]['LIKE']);
        self::assertSame(1, $res['counts'][1]['LOVE']);
        self::assertSame(1, $res['counts'][2]['CURIOUS']);
    }

    public function testGetUserReactionsForPostsReturnsMap(): void
    {
        $result = $this->createMock(Result::class);
        /** @var Result&MockObject $result */
        $result->method('fetchAllAssociative')->willReturn([
            ['post_id' => 3, 'type' => 'SUPPORT'],
            ['post_id' => 4, 'type' => 'LOVE'],
        ]);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with(
                self::stringContains('FROM reaction'),
                self::callback(static function (array $params): bool {
                    return (int) ($params['uid'] ?? 0) === 99
                        && ($params['postIds'] ?? null) === [3, 4, 999];
                }),
                self::callback(static function (array $types): bool {
                    return array_key_exists('uid', $types) && array_key_exists('postIds', $types);
                })
            )
            ->willReturn($result);

        /** @var array<int, string> $map */
        $map = $this->manager->getUserReactionsForPosts([3, 4, 999], 99);

        self::assertSame('SUPPORT', $map[3]);
        self::assertSame('LOVE', $map[4]);
        self::assertArrayNotHasKey(999, $map);
    }
}
