<?php

namespace App\Entity\UsersAvis;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\UsersAvis\AvisRepository;
use App\Entity\Elearning\Formation;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
class Avis
{
    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $avis_id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(name: 'reviewer_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

        #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'avis')]
        #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id', nullable: false)]
    #[Assert\NotNull(message: 'Please select a formation.')]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotBlank(message: 'Please select a rating')]
    #[Assert\Choice(choices: [1, 2, 3, 4, 5], message: 'Rating must be between 1 and 5')]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Comment must not exceed 1000 characters')]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_verified = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_edited = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_removed = null;

    public function getAvisId(): ?int { return $this->avis_id; }
    public function getAvis_id(): ?int { return $this->avis_id; }
    public function setAvisId(int $avis_id): self { $this->avis_id = $avis_id; return $this; }
    public function setAvis_id(int $avis_id): self { $this->avis_id = $avis_id; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getFormation(): ?Formation { return $this->formation; }
    public function setFormation(?Formation $formation): self { $this->formation = $formation; return $this; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $comment; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->created_at; }
    public function getCreated_at(): \DateTimeInterface { return $this->created_at; }
    protected function setCreatedAt(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    protected function setCreated_at(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }

    public function getIsVerified(): ?bool { return $this->is_verified; }
    public function is_verified(): ?bool { return $this->is_verified; }
    public function setIsVerified(?bool $is_verified): self { $this->is_verified = $is_verified; return $this; }
    public function setIs_verified(?bool $is_verified): self { $this->is_verified = $is_verified; return $this; }

    public function getIsEdited(): ?bool { return $this->is_edited; }
    public function isEdited(): ?bool { return $this->is_edited; }
    public function setIsEdited(?bool $is_edited): self { $this->is_edited = $is_edited; return $this; }

    public function getIsRemoved(): ?bool { return $this->is_removed; }
    public function isRemoved(): ?bool { return $this->is_removed; }
    public function setIsRemoved(?bool $is_removed): self { $this->is_removed = $is_removed; return $this; }

    public function getDisplayComment(): string
    {
        if ($this->is_removed) {
            return '<--This comment has been removed by a moderator-->';
        }
        return $this->comment ?? '';
    }
}
