<?php

namespace App\Entity\Elearning;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Elearning\PaymentRepository;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $payment_id = null;

    public function getPayment_id(): ?int
    {
        return $this->payment_id;
    }

    public function setPayment_id(int $payment_id): self
    {
        $this->payment_id = $payment_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $investment_id;

    public function getInvestment_id(): int
    {
        return $this->investment_id;
    }

public function setInvestment_id(int $investment_id): self
    {
        $this->investment_id = $investment_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $payment_date;

    public function getPayment_date(): \DateTimeInterface
    {
        return $this->payment_date;
    }

    protected function setPayment_date(\DateTimeInterface $payment_date): self
    {
        $this->payment_date = $payment_date;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $payment_method;

    public function getPayment_method(): ?string
    {
        return $this->payment_method;
    }

    public function setPayment_method(string $payment_method): self
    {
        $this->payment_method = $payment_method;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $payment_status;

    public function getPayment_status(): ?string
    {
        return $this->payment_status;
    }

    public function setPayment_status(string $payment_status): self
    {
        $this->payment_status = $payment_status;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private string $transaction_reference;

    public function getTransaction_reference(): ?string
    {
        return $this->transaction_reference;
    }

    public function setTransaction_reference(string $transaction_reference): self
    {
        $this->transaction_reference = $transaction_reference;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private string $notes;

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

}
