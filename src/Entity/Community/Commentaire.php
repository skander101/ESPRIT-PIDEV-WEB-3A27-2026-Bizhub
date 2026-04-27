<?php

namespace App\Entity\Community;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\Community\CommentaireRepository;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
#[ORM\Table(name: 'commentaire')]
class Commentaire
{
    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $comment_id = null;

    public function getComment_id(): ?int
    {
        return $this->comment_id;
    }

    public function setComment_id(int $comment_id): self
    {
        $this->comment_id = $comment_id;
        return $this;
    }

#[ORM\Column(type: 'integer', nullable: false)]
    private int $post_id;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $user_id;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private string $content;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    protected function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
