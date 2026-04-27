<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\NegotiationMessageRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: NegotiationMessageRepository::class)]
#[ORM\Table(name: 'negotiation_message')]
class NegotiationMessage
{
    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $message_id = null;

    public function getMessage_id(): ?int
    {
        return $this->message_id;
    }

    public function setMessage_id(int $message_id): self
    {
        $this->message_id = $message_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Negotiation::class, inversedBy: 'negotiationMessages')]
    #[ORM\JoinColumn(name: 'negotiation_id', referencedColumnName: 'negotiation_id')]
    private ?Negotiation $negotiation = null;

    public function getNegotiation(): ?Negotiation
    {
        return $this->negotiation;
    }

    public function setNegotiation(?Negotiation $negotiation): self
    {
        $this->negotiation = $negotiation;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'user_id', nullable: true)]
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

    #[ORM\Column(type: 'text', nullable: false)]
    private string $message;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message_type = null;

    public function getMessage_type(): ?string
    {
        return $this->message_type;
    }

    public function setMessage_type(?string $message_type): self
    {
        $this->message_type = $message_type;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $proposed_amount = null;

    public function getProposed_amount(): ?string
    {
        return $this->proposed_amount;
    }

    public function setProposed_amount(?string $proposed_amount): self
    {
        $this->proposed_amount = $proposed_amount;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $sentiment = null;

    public function getSentiment(): ?string
    {
        return $this->sentiment;
    }

    public function setSentiment(?string $sentiment): self
    {
        $this->sentiment = $sentiment;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    protected function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
