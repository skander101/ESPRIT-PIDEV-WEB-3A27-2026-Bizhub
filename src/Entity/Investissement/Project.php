<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ProjectRepository;
use App\Entity\UsersAvis\User;
use App\Entity\AiAnalysis;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project
{
    // ── Statuts métier ──────────────────────────────────────────────────────
    const STATUS_BROUILLON = 'brouillon';
    const STATUS_PUBLIE    = 'publie';
    const STATUS_EN_COURS  = 'en_cours';
    const STATUS_FINANCE   = 'finance';
    const STATUS_FERME     = 'ferme';

    const STATUTS = [
        'Brouillon'  => self::STATUS_BROUILLON,
        'Publié'     => self::STATUS_PUBLIE,
        'En cours'   => self::STATUS_EN_COURS,
        'Financé'    => self::STATUS_FINANCE,
        'Fermé'      => self::STATUS_FERME,
    ];

    // ── Secteurs d'activité ─────────────────────────────────────────────────
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

    public function getProject_id(): ?int
    {
        return $this->project_id;
    }

    public function setProject_id(int $project_id): self
    {
        $this->project_id = $project_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'startup_id', referencedColumnName: 'user_id')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $required_budget = null;

    public function getRequired_budget(): ?float
    {
        return $this->required_budget;
    }

    public function getRequiredBudget(): ?float
    {
        return $this->required_budget;
    }

    public function setRequired_budget(float $required_budget): self
    {
        $this->required_budget = $required_budget;
        return $this;
    }

    public function setRequiredBudget(float $required_budget): self
    {
        $this->required_budget = $required_budget;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $secteur = null;

    public function getSecteur(): ?string
    {
        return $this->secteur;
    }

    public function setSecteur(?string $secteur): self
    {
        $this->secteur = $secteur;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: AiAnalysis::class, mappedBy: 'project')]
    private Collection $aiAnalysis;

    /**
     * @return Collection<int, AiAnalysis>
     */
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

    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'project')]
    private Collection $investments;

    /**
     * @return Collection<int, Investment>
     */
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

    #[ORM\OneToMany(targetEntity: Negotiation::class, mappedBy: 'project')]
    private Collection $negotiations;

    /**
     * @return Collection<int, Negotiation>
     */
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
