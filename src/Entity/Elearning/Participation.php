<?php

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Elearning\ParticipationRepository;
use App\Entity\UsersAvis\User;
use App\Entity\Elearning\Formation;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\Index(name: 'idx_participation_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_participation_formation', columns: ['formation_id'])]
#[ORM\HasLifecycleCallbacks]
class Participation
{
    public const STATUS_PAID = 'PAID';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_REFUNDED = 'REFUNDED';
    public const STATUS_AWAITING_PAYMENT = 'AWAITING_PAYMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_candidature = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id')]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_affectation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remarques = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PENDING'])]
    private string $payment_status = 'PENDING';

    #[ORM\Column(name: 'participation_status', type: 'string', length: 32, options: ['default' => 'AWAITING_PAYMENT'])]
    private string $lifecycleStatus = 'AWAITING_PAYMENT';

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $payment_provider = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $payment_ref = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $amount = '0.00';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $paid_at = null;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $certificatePath = null;

    public function getId_candidature(): ?int
    {
        return $this->id_candidature;
    }

    public function setId_candidature(int $id_candidature): self
    {
        $this->id_candidature = $id_candidature;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): self { $this->user = $v; return $this; }

    public function getFormation(): ?Formation { return $this->formation; }
    public function setFormation(?Formation $v): self { $this->formation = $v; return $this; }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->created_at = new \DateTime();
    }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }

    public function getDate_affectation(): ?\DateTimeInterface
    {
        return $this->date_affectation;
    }

    public function getDateAffectation(): ?\DateTimeInterface
    {
        return $this->date_affectation;
    }

    protected function setDate_affectation(?\DateTimeInterface $date_affectation): self
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
        $this->payment_status = $paymentStatus;

        return $this;
    }

    public function getLifecycleStatus(): string
    {
        return $this->lifecycleStatus;
    }

    public function setLifecycleStatus(string $lifecycleStatus): self
    {
        $this->lifecycleStatus = $lifecycleStatus;
        return $this;
    }

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

    public function getAmount(): ?string { return $this->amount; }

    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getPaid_at(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    protected function setPaid_at(?\DateTimeInterface $paid_at): self
    {
        $this->paid_at = $paid_at;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): self
    {
        return $this->setPaid_at($paidAt);
    }

    public function getCertificatePath(): ?string
    {
        return $this->certificatePath;
    }

    public function setCertificatePath(?string $path): self
    {
        $this->certificatePath = $path;
        return $this;
    }

    public function isPaidEnrollment(): bool
    {
        return $this->payment_status === 'PAID' && strtoupper($this->lifecycleStatus) === 'PAID';
    }

    public function isAwaitingPayment(): bool
    {
        return $this->lifecycleStatus === self::STATUS_AWAITING_PAYMENT && $this->payment_status === 'PENDING';
    }

    public function setStatus(string $status): self
    {
        return $this->setLifecycleStatus($status);
    }

    public function getStatus(): string
    {
        return $this->lifecycleStatus;
    }
}
