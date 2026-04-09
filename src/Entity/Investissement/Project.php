<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\ProjectRepository;
use App\Entity\UsersAvis\User;
use App\Entity\AiAnalysis;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project
{
    public const STATUS_BROUILLON = 'pending';
    public const STATUS_PUBLIE    = 'in_progress';
    public const STATUS_EN_COURS  = 'in_progress';
    public const STATUS_FINANCE   = 'funded';
    public const STATUS_FERME     = 'completed';

    const STATUTS = [
        'En attente' => self::STATUS_BROUILLON,
        'En cours'   => self::STATUS_EN_COURS,
        'Financé'    => self::STATUS_FINANCE,
        'Terminé'    => self::STATUS_FERME,
    ];

    const SECTEURS = [
        'Technologie'     => 'tech',
        'FinTech'         => 'fintech',
        'Santé'           => 'sante',
        'Agriculture'     => 'agriculture',
        'Éducation'       => 'education',
        'Commerce'        => 'commerce',
        'Énergie'         => 'energie',
        'Immobilier'      => 'immobilier',
        'Transport'       => 'transport',
        'Autre'           => 'autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $project_id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'startup_id', referencedColumnName: 'user_id')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.', normalizer: 'trim')]
    #[Assert\Length(
        min: 20,
        max: 5000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', nullable: false)]
    #[Assert\NotBlank(message: 'Le budget est obligatoire.')]
    #[Assert\Positive(message: 'Le budget doit être positif.')]
    private ?float $required_budget = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'Veuillez sélectionner un secteur.')]
    #[Assert\Choice(
        choices: ['tech', 'fintech', 'sante', 'agriculture', 'education', 'commerce', 'energie', 'immobilier', 'transport', 'autre'],
        message: 'Veuillez choisir un secteur valide.'
    )]
    private ?string $secteur = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true, options: ['default' => 'pending'])]
    #[Assert\NotBlank(message: 'Veuillez choisir un statut.')]
    #[Assert\Choice(
        choices: ['pending', 'in_progress', 'funded', 'completed'],
        message: 'Veuillez choisir un statut valide.'
    )]
    private ?string $status = self::STATUS_BROUILLON;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\OneToMany(targetEntity: AiAnalysis::class, mappedBy: 'project')]
    private Collection $aiAnalysis;

    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'project')]
    private Collection $investments;

    #[ORM\OneToMany(targetEntity: Negotiation::class, mappedBy: 'project')]
    private Collection $negotiations;

    public function __construct()
    {
        $this->aiAnalysis   = new ArrayCollection();
        $this->investments  = new ArrayCollection();
        $this->negotiations = new ArrayCollection();
    }

    public function getProject_id(): ?int { return $this->project_id; }
    public function setProject_id(int $project_id): self { $this->project_id = $project_id; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getRequired_budget(): ?float { return $this->required_budget; }
    public function getRequiredBudget(): ?float { return $this->required_budget; }
    public function setRequired_budget(float $required_budget): self { $this->required_budget = $required_budget; return $this; }
    public function setRequiredBudget(float $required_budget): self { $this->required_budget = $required_budget; return $this; }

    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $secteur): self { $this->secteur = $secteur; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status; return $this; }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreated_at(?\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function setCreatedAt(?\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }

    public function getAiAnalysis(): Collection
    {
        if (!$this->aiAnalysis instanceof Collection) {
            $this->aiAnalysis = new ArrayCollection();
        }
        return $this->aiAnalysis;
    }

    public function addAiAnalysi(AiAnalysis $aiAnalysi): self
    {
        if (!$this->getAiAnalysis()->contains($aiAnalysi)) {
            $this->getAiAnalysis()->add($aiAnalysi);
        }
        return $this;
    }

    public function removeAiAnalysi(AiAnalysis $aiAnalysi): self
    {
        $this->getAiAnalysis()->removeElement($aiAnalysi);
        return $this;
    }

    public function getInvestments(): Collection
    {
        if (!$this->investments instanceof Collection) {
            $this->investments = new ArrayCollection();
        }
        return $this->investments;
    }

    public function addInvestment(Investment $investment): self
    {
        if (!$this->getInvestments()->contains($investment)) {
            $this->getInvestments()->add($investment);
        }
        return $this;
    }

    public function removeInvestment(Investment $investment): self
    {
        $this->getInvestments()->removeElement($investment);
        return $this;
    }

    public function getNegotiations(): Collection
    {
        if (!$this->negotiations instanceof Collection) {
            $this->negotiations = new ArrayCollection();
        }
        return $this->negotiations;
    }

    public function addNegotiation(Negotiation $negotiation): self
    {
        if (!$this->getNegotiations()->contains($negotiation)) {
            $this->getNegotiations()->add($negotiation);
        }
        return $this;
    }

    public function removeNegotiation(Negotiation $negotiation): self
    {
        $this->getNegotiations()->removeElement($negotiation);
        return $this;
    }
}
