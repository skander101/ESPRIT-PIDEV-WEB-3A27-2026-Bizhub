<?php

namespace App\Repository\Community;

use App\Entity\Community\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    private const PAGE_SIZE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findAllWithAuthor(): array
    {
        return $this->searchPosts('', '', 1);
    }

    public function searchPosts(string $search = '', string $category = '', int $page = 1): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $search = trim($search);
        $category = trim($category);
        $page = max(1, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(p.title LIKE :q OR p.content LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $where[] = 'p.category = :cat';
            $params['cat'] = $category;
        }

        $sql = '
            SELECT p.*, u.full_name as author_name, u.avatar_url
            FROM post p
            LEFT JOIN app_user u ON p.user_id = u.user_id
        ';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset;

        return $conn->fetchAllAssociative($sql, $params);
    }

    public function countPosts(string $search = '', string $category = ''): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $search = trim($search);
        $category = trim($category);

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(title LIKE :q OR content LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $where[] = 'category = :cat';
            $params['cat'] = $category;
        }

        $sql = 'SELECT COUNT(*) FROM post';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int) $conn->fetchOne($sql, $params);
    }

    public function findOneWithAuthor(int $id): ?array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT p.*, u.full_name as author_name, u.avatar_url
            FROM post p
            LEFT JOIN app_user u ON p.user_id = u.user_id
            WHERE p.post_id = :id
        ';
        $result = $conn->fetchAllAssociative($sql, ['id' => $id]);
        return $result ? $result[0] : null;
    }

    /** @return array<int, int> postId => commentCount */
    public function getCommentCountsByPostIds(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }
        $conn = $this->getEntityManager()->getConnection();
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = 'SELECT post_id, COUNT(*) AS cnt FROM commentaire WHERE post_id IN (' . $placeholders . ') GROUP BY post_id';
        $rows = $conn->fetchAllAssociative($sql, $postIds);
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['post_id']] = (int) $row['cnt'];
        }
        return $counts;
    }
}
