<?php

namespace App\Entity\Elearning;

use App\Entity\UsersAvis\Avis;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formation')]
class Formation
{
    public function __construct()
    {
        $this->trainingRequests = new ArrayCollection();
        $this->participations = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'formation_id', type: 'integer')]
    private ?int $formation_id = null;

    #[ORM\Column(name: 'title', type: 'string', length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 200)]
    private ?string $title = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'formations')]
    #[ORM\JoinColumn(name: 'trainer_id', referencedColumnName: 'user_id', nullable: false)]
    #[Assert\NotNull(message: 'Le formateur est obligatoire.')]
    private ?User $user = null;

    #[ORM\Column(name: 'start_date', type: 'date')]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(name: 'end_date', type: 'date')]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    private ?\DateTimeInterface $end_date = null;

    #[ORM\Column(name: 'cost', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cost = '0.00';

    #[ORM\Column(name: 'lieu', type: 'string', length: 300)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(max: 300)]
    private ?string $lieu = null;

    /** Colonne SQL souvent nommée en_ligne sur les bases existantes ; « en ligne » côté appli via $enligne / isEnligne(). */
    #[ORM\Column(name: 'en_ligne', type: 'boolean', options: ['default' => false])]
    private bool $enligne = false;

    #[ORM\OneToOne(targetEntity: Avis::class, mappedBy: 'formation')]
    private ?Avis $avi = null;

    #[ORM\OneToMany(targetEntity: TrainingRequest::class, mappedBy: 'formation')]
    private Collection $trainingRequests;

    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'formation', orphanRemoval: true)]
    private Collection $participations;

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
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

    public function getStart_date(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStart_date(?\DateTimeInterface $start_date): self
    {
        $this->start_date = $start_date;

        return $this;
    }

    /** Utilisé par les formulaires Symfony (PropertyAccessor : start_date → getStartDate). */
    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->start_date = $startDate;

        return $this;
    }

    public function getEnd_date(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEnd_date(?\DateTimeInterface $end_date): self
    {
        $this->end_date = $end_date;

        return $this;
    }

    /** Utilisé par les formulaires Symfony (PropertyAccessor : end_date → getEndDate). */
    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->end_date = $endDate;

        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(string|float|int|null $cost): self
    {
        if ($cost === null || $cost === '') {
            $this->cost = null;
        } else {
            $this->cost = is_string($cost) ? $cost : number_format((float) $cost, 2, '.', '');
        }

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function isEnligne(): bool
    {
        return $this->enligne;
    }

    public function setEnligne(bool $enligne): self
    {
        $this->enligne = $enligne;

        return $this;
    }

    public function getAvi(): ?Avis
    {
        return $this->avi;
    }

    public function setAvi(?Avis $avi): self
    {
        $this->avi = $avi;

        return $this;
    }

    /**
     * @return Collection<int, TrainingRequest>
     */
    public function getTrainingRequests(): Collection
    {
        return $this->trainingRequests;
    }

    public function addTrainingRequest(TrainingRequest $trainingRequest): self
    {
        if (!$this->trainingRequests->contains($trainingRequest)) {
            $this->trainingRequests->add($trainingRequest);
            $trainingRequest->setFormation($this);
        }

        return $this;
    }

    public function removeTrainingRequest(TrainingRequest $trainingRequest): self
    {
        $this->trainingRequests->removeElement($trainingRequest);

        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): self
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setFormation($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): self
    {
        $this->participations->removeElement($participation);

        return $this;
    }

    #[Assert\Callback]
    public function validateCost(ExecutionContextInterface $context, mixed $payload = null): void
    {
        $cost = $this->cost;
        if ($cost === null) {
            return;
        }
        $s = trim((string) $cost);
        if ($s === '') {
            return;
        }
        $normalized = str_replace(',', '.', $s);
        if (!is_numeric($normalized)) {
            $context->buildViolation('Le coût doit être un nombre valide.')
                ->atPath('cost')
                ->addViolation();

            return;
        }
        if ((float) $normalized < 0) {
            $context->buildViolation('Le coût ne peut pas être négatif.')
                ->atPath('cost')
                ->addViolation();
        }
    }
}
