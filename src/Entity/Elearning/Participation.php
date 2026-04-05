<?php

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ParticipationRepository;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
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

    public function setDate_affectation(?\DateTimeInterface $date_affectation): self
    {
        $this->date_affectation = $date_affectation;
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

    public function setPayment_status(string $payment_status): self
    {
        $this->payment_status = $payment_status;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_provider = null;

    public function getPayment_provider(): ?string
    {
        return $this->payment_provider;
    }

    public function setPayment_provider(?string $payment_provider): self
    {
        $this->payment_provider = $payment_provider;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_ref = null;

    public function getPayment_ref(): ?string
    {
        return $this->payment_ref;
    }

    public function setPayment_ref(?string $payment_ref): self
    {
        $this->payment_ref = $payment_ref;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
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

    public function setPaid_at(?\DateTimeInterface $paid_at): self
    {
        $this->paid_at = $paid_at;
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

    public function getFormation_id(): ?int
    {
        return $this->formation_id;
    }

    public function setFormation_id(int $formation_id): self
    {
        $this->formation_id = $formation_id;
        return $this;
    }

}
