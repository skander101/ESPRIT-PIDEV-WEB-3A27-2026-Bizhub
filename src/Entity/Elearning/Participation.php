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
#[ORM\HasLifecycleCallbacks]
class Participation
{
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
        $this->payment_status = $paymentStatus;

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

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private ?float $amount = null;

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
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
        $this->paid_at = $paid_at;
        return $this;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): self
    {
        $this->paid_at = $paidAt;

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

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): self { $this->user = $v; return $this; }

    public function getFormation(): ?Formation { return $this->formation; }
    public function setFormation(?Formation $v): self { $this->formation = $v; return $this; }

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        $this->created_at = new \DateTime();
    }

    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }

}
