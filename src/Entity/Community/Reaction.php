<?php

namespace App\Entity\Community;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\Community\ReactionRepository;

#[ORM\Entity(repositoryClass: ReactionRepository::class)]
#[ORM\Table(name: 'reaction')]
class Reaction
{
    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $reaction_id = null;

    public function getReaction_id(): ?int
    {
        return $this->reaction_id;
    }

    public function setReaction_id(int $reaction_id): self
    {
        $this->reaction_id = $reaction_id;
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

    #[ORM\Column(type: 'string', nullable: false)]
    private string $type = '';

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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
