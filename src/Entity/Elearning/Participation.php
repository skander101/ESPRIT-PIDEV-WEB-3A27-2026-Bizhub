<?php

namespace App\Entity\Elearning;

use App\Entity\UsersAvis\User;
use App\Repository\Elearning\ParticipationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[UniqueEntity(fields: ['user', 'formation'], message: 'Cet utilisateur participe déjà à cette formation.')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_candidature', type: 'integer')]
    private ?int $id_candidature = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id', nullable: false)]
    #[Assert\NotNull]
    private ?Formation $formation = null;

    #[ORM\Column(name: 'date_affectation', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_affectation = null;

    #[ORM\Column(name: 'remarques', type: 'text', nullable: true)]
    private ?string $remarques = null;

    #[ORM\Column(name: 'payment_status', type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $payment_status = 'PENDING';

    #[ORM\Column(name: 'payment_provider', type: 'string', length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $payment_provider = null;

    #[ORM\Column(name: 'payment_ref', type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $payment_ref = null;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull]
    private string $amount = '0.00';

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paid_at = null;

    public function __construct()
    {
        $this->date_affectation = new \DateTime();
    }

    public function getId_candidature(): ?int
    {
        return $this->id_candidature;
    }

    public function setId_candidature(int $id_candidature): self
    {
        $this->id_candidature = $id_candidature;

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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getDate_affectation(): ?\DateTimeInterface
    {
        return $this->date_affectation;
    }

    public function setDate_affectation(?\DateTimeInterface $date_affectation): self
    {
        $this->date_affectation = $date_affectation;

        return $this;
    }

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(?string $remarques): self
    {
        $this->remarques = $remarques;

        return $this;
    }

    public function getPayment_status(): string
    {
        return $this->payment_status;
    }

    public function setPayment_status(string $payment_status): self
    {
        $this->payment_status = $payment_status;

        return $this;
    }

    public function getPayment_provider(): ?string
    {
        return $this->payment_provider;
    }

    public function setPayment_provider(?string $payment_provider): self
    {
        $this->payment_provider = $payment_provider;

        return $this;
    }

    public function getPayment_ref(): ?string
    {
        return $this->payment_ref;
    }

    public function setPayment_ref(?string $payment_ref): self
    {
        $this->payment_ref = $payment_ref;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string|float|int $amount): self
    {
        $this->amount = is_string($amount)
            ? $amount
            : number_format((float) $amount, 2, '.', '');

        return $this;
    }

    public function getPaid_at(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    public function setPaid_at(?\DateTimeInterface $paid_at): self
    {
        $this->paid_at = $paid_at;

        return $this;
    }
}
