<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\NegotiationRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: NegotiationRepository::class)]
#[ORM\Table(name: 'negotiation')]
class Negotiation
{
    const STATUS_OPEN     = 'open';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED  = 'expired';

    const STATUTS = [
        'Ouverte'   => self::STATUS_OPEN,
        'Acceptée'  => self::STATUS_ACCEPTED,
        'Rejetée'   => self::STATUS_REJECTED,
        'Expirée'   => self::STATUS_EXPIRED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $negotiation_id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'negotiations')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id')]
    #[Assert\NotNull(message: 'Le projet est obligatoire.')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investorNegotiations')]
    #[ORM\JoinColumn(name: 'investor_id', referencedColumnName: 'user_id')]
    #[Assert\NotNull(message: 'L\'investisseur est obligatoire.')]
    private ?User $investor = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'startupNegotiations')]
    #[ORM\JoinColumn(name: 'startup_id', referencedColumnName: 'user_id')]
    #[Assert\NotNull(message: 'La startup est obligatoire.')]
    private ?User $startup = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUS_OPEN, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED],
        message: 'Statut de négociation invalide.'
    )]
    private ?string $status = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant proposé doit être positif.')]
    private ?float $proposed_amount = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant final doit être positif.')]
    private ?float $final_amount = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\LessThanOrEqual(value: 'now', message: 'La date de création ne peut pas être dans le futur.')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\LessThanOrEqual(value: 'now', message: 'La date de mise à jour ne peut pas être dans le futur.')]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\OneToMany(targetEntity: NegotiationMessage::class, mappedBy: 'negotiation')]
    private Collection $negotiationMessages;

    public function __construct()
    {
        $this->negotiationMessages = new ArrayCollection();
    }

    public function getNegotiation_id(): ?int { return $this->negotiation_id; }
    public function setNegotiation_id(int $negotiation_id): self { $this->negotiation_id = $negotiation_id; return $this; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }

    public function getInvestor(): ?User { return $this->investor; }
    public function setInvestor(?User $investor): self { $this->investor = $investor; return $this; }

    public function getStartup(): ?User { return $this->startup; }
    public function setStartup(?User $startup): self { $this->startup = $startup; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status; return $this; }

    public function getProposed_amount(): ?float { return $this->proposed_amount; }
    public function setProposed_amount(?float $proposed_amount): self { $this->proposed_amount = $proposed_amount; return $this; }

    public function getFinal_amount(): ?float { return $this->final_amount; }
    public function setFinal_amount(?float $final_amount): self { $this->final_amount = $final_amount; return $this; }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreated_at(?\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }

    public function getUpdated_at(): ?\DateTimeInterface { return $this->updated_at; }
    public function setUpdated_at(?\DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }

    /** @return Collection<int, NegotiationMessage> */
    public function getNegotiationMessages(): Collection
    {
        if (!$this->negotiationMessages instanceof Collection) {
            $this->negotiationMessages = new ArrayCollection();
        }
        return $this->negotiationMessages;
    }

    public function addNegotiationMessage(NegotiationMessage $negotiationMessage): self
    {
        if (!$this->getNegotiationMessages()->contains($negotiationMessage)) {
            $this->getNegotiationMessages()->add($negotiationMessage);
        }
        return $this;
    }

    public function removeNegotiationMessage(NegotiationMessage $negotiationMessage): self
    {
        $this->getNegotiationMessages()->removeElement($negotiationMessage);
        return $this;
    }
}
