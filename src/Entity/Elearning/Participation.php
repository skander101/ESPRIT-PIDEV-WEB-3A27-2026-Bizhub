<?php

declare(strict_types=1);

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;

use App\Repository\Elearning\ParticipationRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\HasLifecycleCallbacks]
class Participation
{
    public const STATUS_AWAITING_PAYMENT = 'AWAITING_PAYMENT';

    public const STATUS_PAID = 'PAID';

    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $user_id = null;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;

        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_affectation = null;

    public function getDate_affectation(): ?\DateTimeInterface
    {
        return $this->date_affectation;
    }

    public function getDateAffectation(): ?\DateTimeInterface
    {
        return $this->date_affectation;
    }

    public function setDate_affectation(?\DateTimeInterface $date_affectation): self
    {
        $this->date_affectation = $date_affectation;

        return $this;
    }

    public function setDateAffectation(?\DateTimeInterface $dateAffectation): self
    {
        $this->date_affectation = $dateAffectation;

        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remarques = null;

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(?string $remarques): self
    {
        $this->remarques = $remarques;

        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $payment_status = null;

    public function getPayment_status(): ?string
    {
        return $this->payment_status;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->payment_status;
    }

    public function setPayment_status(string $payment_status): self
    {
        $this->payment_status = $payment_status;

        return $this;
    }

    public function setPaymentStatus(?string $paymentStatus): self
    {
        $this->payment_status = $paymentStatus ?? 'PENDING';

        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_provider = null;

    public function getPayment_provider(): ?string
    {
        return $this->payment_provider;
    }

    public function getPaymentProvider(): ?string
    {
        return $this->payment_provider;
    }

    public function setPayment_provider(?string $payment_provider): self
    {
        $this->payment_provider = $payment_provider;

        return $this;
    }

    public function setPaymentProvider(?string $paymentProvider): self
    {
        $this->payment_provider = $paymentProvider;

        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_ref = null;

    public function getPayment_ref(): ?string
    {
        return $this->payment_ref;
    }

    public function getPaymentRef(): ?string
    {
        return $this->payment_ref;
    }

    public function setPayment_ref(?string $payment_ref): self
    {
        $this->payment_ref = $payment_ref;

        return $this;
    }

    public function setPaymentRef(?string $paymentRef): self
    {
        $this->payment_ref = $paymentRef;

        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?string $amount = null;

    public function getAmount(): ?float
    {
        if ($this->amount === null || $this->amount === '') {
            return null;
        }

        return (float) $this->amount;
    }

    public function setAmount(string|int|float $amount): self
    {
        $this->amount = (string) $amount;

        return $this;
    }

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $paid_at = null;

    public function getPaid_at(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    public function setPaid_at(?\DateTimeInterface $paid_at): self
    {
        $this->paid_at = self::toDateTimeImmutableOrNull($paid_at);

        return $this;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): self
    {
        $this->paid_at = self::toDateTimeImmutableOrNull($paidAt);

        return $this;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_candidature = null;

    public function getId_candidature(): ?int
    {
        return $this->id_candidature;
    }

    public function getId(): ?int
    {
        return $this->id_candidature;
    }

    public function setId_candidature(int $id_candidature): self
    {
        $this->id_candidature = $id_candidature;

        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $formation_id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id')]
    private ?Formation $formation = null;

    /**
     * Transient status derived from persisted fields (e.g. payment_status).
     * Kept for backward compatibility with existing code paths.
     */
    private string $lifecycleStatus = self::STATUS_AWAITING_PAYMENT;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $transaction_id = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $certificate_path = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $v): self
    {
        $this->user = $v;
        if ($v !== null) {
            $this->user_id = (int) $v->getUser_id();
        }

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $v): self
    {
        $this->formation = $v;
        if ($v !== null) {
            $this->formation_id = (int) $v->getFormation_id();
        }

        return $this;
    }

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;

        return $this;
    }

    public function getStatus(): string
    {
        $ps = $this->payment_status;
        if ($ps === 'PAID') {
            return self::STATUS_PAID;
        }
        if ($ps === 'PENDING') {
            return self::STATUS_AWAITING_PAYMENT;
        }

        return self::STATUS_CANCELLED;
    }

    public function setStatus(string $status): self
    {
        $this->lifecycleStatus = $status;
        if ($status === self::STATUS_PAID) {
            $this->payment_status = 'PAID';
        } elseif ($status === self::STATUS_AWAITING_PAYMENT) {
            $this->payment_status = 'PENDING';
        } elseif ($status === self::STATUS_CANCELLED) {
            $this->payment_status = 'FAILED';
        }

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transaction_id;
    }

    public function setTransactionId(?string $transactionId): self
    {
        $this->transaction_id = $transactionId;

        return $this;
    }

    public function getCertificatePath(): ?string
    {
        return $this->certificate_path;
    }

    public function setCertificatePath(?string $certificatePath): self
    {
        $this->certificate_path = $certificatePath;

        return $this;
    }

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->created_at === null) {
            $this->created_at = new \DateTimeImmutable();
        }
    }

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->created_at = $createdAt instanceof \DateTimeImmutable
            ? $createdAt
            : \DateTimeImmutable::createFromMutable($createdAt);

        return $this;
    }

    private static function toDateTimeImmutableOrNull(?\DateTimeInterface $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        return \DateTimeImmutable::createFromMutable($value);
    }

    public function isPaidEnrollment(): bool
    {
        return $this->payment_status === 'PAID';
    }

    public function isAwaitingPayment(): bool
    {
        return $this->payment_status === 'PENDING';
    }

    public function isCancelled(): bool
    {
        return $this->payment_status !== 'PAID' && $this->payment_status !== 'PENDING';
    }
}
