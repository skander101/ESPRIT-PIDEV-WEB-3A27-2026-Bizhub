<?php

namespace App\Repository\Community;

use App\Entity\Community\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findAllWithAuthor(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT p.*, u.full_name as author_name, u.avatar_url
            FROM post p
            LEFT JOIN app_user u ON p.user_id = u.user_id
            ORDER BY p.created_at DESC
        ';
        return $conn->fetchAllAssociative($sql);
    }

    public function searchPosts(string $search = '', string $category = ''): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $search = trim($search);
        $category = trim($category);

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
        $sql .= ' ORDER BY p.created_at DESC';

        return $conn->fetchAllAssociative($sql, $params);
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
}
