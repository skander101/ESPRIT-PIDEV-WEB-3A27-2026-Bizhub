<?php

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TrainingRequestRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: TrainingRequestRepository::class)]
#[ORM\Table(name: 'training_request')]
class TrainingRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'request_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'startup_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'trainingRequests')]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id', nullable: false)]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $status = 'pending'; // pending, accepted, rejected, completed

    #[ORM\Column(name: 'request_date', type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
