<?php

namespace App\Entity\Community;

use App\Repository\Community\ReactionRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\Community\Post;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReactionRepository::class)]
#[ORM\Table(name: 'reaction')]
class Reaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'reaction_id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'post_id', nullable: false)]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $type = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
