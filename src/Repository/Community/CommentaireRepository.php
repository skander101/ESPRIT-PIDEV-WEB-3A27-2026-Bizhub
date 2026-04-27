<?php

namespace App\Repository\Community;

use App\Entity\Community\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    public function findByPostIdWithAuthor(int $postId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT c.*, u.full_name as author_name, u.avatar_url
            FROM commentaire c
            LEFT JOIN user u ON c.user_id = u.user_id
            WHERE c.post_id = :postId
            ORDER BY c.created_at ASC
        ';
        return $conn->fetchAllAssociative($sql, ['postId' => $postId]);
    }

    public function countByPostId(int $postId): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COUNT(*) FROM commentaire WHERE post_id = :postId';
        return (int) $conn->fetchOne($sql, ['postId' => $postId]);
    }
}
