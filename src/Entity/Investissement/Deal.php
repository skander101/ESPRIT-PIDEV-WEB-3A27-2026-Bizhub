<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\DealRepository;

#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\Table(name: 'deal')]
class Deal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $deal_id = null;

    public function getDeal_id(): ?int
    {
        return $this->deal_id;
    }

    public function setDeal_id(int $deal_id): self
    {
        $this->deal_id = $deal_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $negotiation_id = null;

    public function getNegotiation_id(): ?int
    {
        return $this->negotiation_id;
    }

    public function setNegotiation_id(?int $negotiation_id): self
    {
        $this->negotiation_id = $negotiation_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $buyer_id = null;

    public function getBuyer_id(): ?int
    {
        return $this->buyer_id;
    }

    public function setBuyer_id(int $buyer_id): self
    {
        $this->buyer_id = $buyer_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $seller_id = null;

    public function getSeller_id(): ?int
    {
        return $this->seller_id;
    }

    public function setSeller_id(int $seller_id): self
    {
        $this->seller_id = $seller_id;
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

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stripe_payment_intent_id = null;

    public function getStripe_payment_intent_id(): ?string
    {
        return $this->stripe_payment_intent_id;
    }

    public function setStripe_payment_intent_id(?string $stripe_payment_intent_id): self
    {
        $this->stripe_payment_intent_id = $stripe_payment_intent_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stripe_payment_status = null;

    public function getStripe_payment_status(): ?string
    {
        return $this->stripe_payment_status;
    }

    public function setStripe_payment_status(?string $stripe_payment_status): self
    {
        $this->stripe_payment_status = $stripe_payment_status;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stripe_checkout_session_id = null;

    public function getStripe_checkout_session_id(): ?string
    {
        return $this->stripe_checkout_session_id;
    }

    public function setStripe_checkout_session_id(?string $stripe_checkout_session_id): self
    {
        $this->stripe_checkout_session_id = $stripe_checkout_session_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $contract_pdf_path = null;

    public function getContract_pdf_path(): ?string
    {
        return $this->contract_pdf_path;
    }

    public function setContract_pdf_path(?string $contract_pdf_path): self
    {
        $this->contract_pdf_path = $contract_pdf_path;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $yousign_signature_request_id = null;

    public function getYousign_signature_request_id(): ?string
    {
        return $this->yousign_signature_request_id;
    }

    public function setYousign_signature_request_id(?string $yousign_signature_request_id): self
    {
        $this->yousign_signature_request_id = $yousign_signature_request_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $yousign_status = null;

    public function getYousign_status(): ?string
    {
        return $this->yousign_status;
    }

    public function setYousign_status(?string $yousign_status): self
    {
        $this->yousign_status = $yousign_status;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $email_sent = null;

    public function isEmail_sent(): ?bool
    {
        return $this->email_sent;
    }

    public function setEmail_sent(?bool $email_sent): self
    {
        $this->email_sent = $email_sent;
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

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    public function getCompleted_at(): ?\DateTimeInterface
    {
        return $this->completed_at;
    }

    public function setCompleted_at(?\DateTimeInterface $completed_at): self
    {
        $this->completed_at = $completed_at;
        return $this;
    }

}
