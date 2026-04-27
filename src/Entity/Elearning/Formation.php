<?php

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Elearning\FormationRepository;
use App\Entity\UsersAvis\User;
use App\Entity\UsersAvis\Avis;
use App\Entity\Elearning\TrainingRequest;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formation')]
#[Assert\Callback(callback: 'validateLocationForPresential')]
class Formation
{
    public function __construct()
    {
        $this->avis = new ArrayCollection();
        $this->trainingRequests = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $formation_id = null;

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $title;

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'trainer_id', referencedColumnName: 'user_id')]
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

#[ORM\Column(type: 'date', nullable: false)]
    private \DateTimeInterface $start_date;

    #[ORM\Column(type: 'date', nullable: false)]
    private \DateTimeInterface $end_date;

    public function getStart_date(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStart_date(\DateTimeInterface $start_date): self
    {
        $this->start_date = $start_date;
        return $this;
    }

    public function setStartDate(\DateTimeInterface $start_date): self
    {
        $this->start_date = $start_date;
        return $this;
    }

    public function getEnd_date(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEnd_date(\DateTimeInterface $end_date): self
    {
        $this->end_date = $end_date;
        return $this;
    }

    public function setEndDate(\DateTimeInterface $end_date): self
    {
        $this->end_date = $end_date;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cost = null;

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): self
    {
        $this->cost = $cost;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 7, nullable: true)]
    private ?string $longitude = null;

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): self
    {
        $trimmed = $lieu !== null ? trim($lieu) : null;
        $this->lieu = ($trimmed === '' || $trimmed === null) ? null : $trimmed;

        return $this;
    }

    public function getLatitude(): ?float
    {
        if ($this->latitude === null || $this->latitude === '') {
            return null;
        }

        return (float) $this->latitude;
    }

    public function setLatitude(string|int|float|null $latitude): self
    {
        if ($latitude === null || $latitude === '') {
            $this->latitude = null;
        } else {
            $this->latitude = (string) $latitude;
        }

        return $this;
    }

    public function getLongitude(): ?float
    {
        if ($this->longitude === null || $this->longitude === '') {
            return null;
        }

        return (float) $this->longitude;
    }

    public function setLongitude(string|int|float|null $longitude): self
    {
        if ($longitude === null || $longitude === '') {
            $this->longitude = null;
        } else {
            $this->longitude = (string) $longitude;
        }

        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $en_ligne = false;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $max_formateurs = 1;

    public function isEn_ligne(): bool
    {
        return $this->en_ligne;
    }

    public function isEnLigne(): bool
    {
        return $this->en_ligne;
    }

    public function getEnLigne(): bool
    {
        return $this->en_ligne;
    }

    public function setEn_ligne(bool $en_ligne): self
    {
        $this->en_ligne = $en_ligne;

        return $this;
    }

    public function setEnLigne(bool $en_ligne): self
    {
        $this->en_ligne = $en_ligne;

        return $this;
    }

    public function validateLocationForPresential(ExecutionContextInterface $context): void
    {
        if ($this->en_ligne) {
            return;
        }

        if ($this->lieu === null || $this->lieu === '') {
            $context->buildViolation('Indiquez un lieu sur la carte (adresse) pour une formation présentielle.')
                ->atPath('lieu')
                ->addViolation();
        }

        if ($this->getLatitude() === null || $this->getLongitude() === null) {
            $context->buildViolation('Cliquez sur la carte pour enregistrer la position (latitude et longitude).')
                ->atPath('latitude')
                ->addViolation();
        }
    }

    public function getMaxFormateurs(): int
    {
        return $this->max_formateurs;
    }

    public function setMaxFormateurs(int $max_formateurs): self
    {
        $this->max_formateurs = max(1, $max_formateurs);

        return $this;
    }

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'formation')]
    private Collection $avis;

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        if (!$this->avis instanceof Collection) {
            $this->avis = new ArrayCollection();
        }

        return $this->avis;
    }

    public function addAvis(Avis $avi): self
    {
        if (!$this->getAvis()->contains($avi)) {
            $this->getAvis()->add($avi);
            $avi->setFormation($this);
        }

        return $this;
    }

    public function removeAvis(Avis $avi): self
    {
        $this->getAvis()->removeElement($avi);

        return $this;
    }

    public function getAvi(): ?Avis
    {
        return $this->getAvis()->first() ?: null;
    }

    public function setAvi(?Avis $avi): self
    {
        if ($avi !== null) {
            $this->addAvis($avi);
        }

        return $this;
    }

    #[ORM\OneToMany(targetEntity: TrainingRequest::class, mappedBy: 'formation')]
    private Collection $trainingRequests;

    /**
     * @return Collection<int, TrainingRequest>
     */
    public function getTrainingRequests(): Collection
    {
        if (!$this->trainingRequests instanceof Collection) {
            $this->trainingRequests = new ArrayCollection();
        }
        return $this->trainingRequests;
    }

    public function addTrainingRequest(TrainingRequest $trainingRequest): self
    {
        if (!$this->getTrainingRequests()->contains($trainingRequest)) {
            $this->getTrainingRequests()->add($trainingRequest);
        }
        return $this;
    }

    public function removeTrainingRequest(TrainingRequest $trainingRequest): self
    {
        $this->getTrainingRequests()->removeElement($trainingRequest);
        return $this;
    }

}
