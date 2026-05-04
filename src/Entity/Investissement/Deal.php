<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Security\Util\SensitiveParameter;

use App\Repository\Investissement\DealRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\Table(name: 'deal')]
class Deal
{
    const STATUS_PENDING_PAYMENT   = 'pending_payment';
    const STATUS_PAID             = 'paid';
    const STATUS_PENDING_SIGNATURE = 'pending_signature';
    const STATUS_SIGNED           = 'signed';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_CANCELLED        = 'cancelled';

    const STATUTS = [
        'En attente paiement'   => self::STATUS_PENDING_PAYMENT,
        'Payé'                  => self::STATUS_PAID,
        'En attente signature'  => self::STATUS_PENDING_SIGNATURE,
        'Signé'                 => self::STATUS_SIGNED,
        'Complété'              => self::STATUS_COMPLETED,
        'Annulé'                 => self::STATUS_CANCELLED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $deal_id = null;

    #[ORM\ManyToOne(targetEntity: Negotiation::class)]
    #[ORM\JoinColumn(name: 'negotiation_id', referencedColumnName: 'negotiation_id', nullable: true)]
    private ?Negotiation $negotiation = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'buyer_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $buyer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'seller_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $seller = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotNull(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    private string $amount = '0.00';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'L\'ID Stripe ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $stripe_payment_intent_id = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ['default' => 'pending'])]
    #[Assert\Length(max: 50, maxMessage: 'Le statut Stripe ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $stripe_payment_status = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'L\'ID session ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $stripe_checkout_session_id = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Le chemin du contrat ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $contract_pdf_path = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'L\'ID YouSign ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $yousign_signature_request_id = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ['default' => 'pending'])]
    #[Assert\Length(max: 50, maxMessage: 'Le statut YouSign ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $yousign_status = 'pending';

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $email_sent = false;

    #[ORM\Column(type: 'string', length: 64, options: ['default' => 'pending_payment'])]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID, self::STATUS_PENDING_SIGNATURE, self::STATUS_SIGNED, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        message: 'Statut de deal invalide.'
    )]
    private string $status = self::STATUS_PENDING_PAYMENT;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Ignore]
    private ?string $signature_token = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $signature_token_expires_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $signature_sent_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getDeal_id(): ?int { return $this->deal_id; }
    public function setDeal_id(int $deal_id): self { $this->deal_id = $deal_id; return $this; }

    public function getNegotiation(): ?Negotiation { return $this->negotiation; }
    public function setNegotiation(?Negotiation $negotiation): self { $this->negotiation = $negotiation; return $this; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }

    public function getBuyer(): ?User { return $this->buyer; }
    public function setBuyer(?User $buyer): self { $this->buyer = $buyer; return $this; }

    public function getSeller(): ?User { return $this->seller; }
    public function setSeller(?User $seller): self { $this->seller = $seller; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getStripe_payment_intent_id(): ?string { return $this->stripe_payment_intent_id; }
    public function setStripe_payment_intent_id(?string $stripe_payment_intent_id): self { $this->stripe_payment_intent_id = $stripe_payment_intent_id; return $this; }

    public function getStripe_payment_status(): ?string { return $this->stripe_payment_status; }
    public function setStripe_payment_status(?string $stripe_payment_status): self { $this->stripe_payment_status = $stripe_payment_status ?? 'pending'; return $this; }

    public function getStripe_checkout_session_id(): ?string { return $this->stripe_checkout_session_id; }
    public function setStripe_checkout_session_id(?string $stripe_checkout_session_id): self { $this->stripe_checkout_session_id = $stripe_checkout_session_id; return $this; }

    public function getContract_pdf_path(): ?string { return $this->contract_pdf_path; }
    public function setContract_pdf_path(?string $contract_pdf_path): self { $this->contract_pdf_path = $contract_pdf_path; return $this; }

    public function getYousign_signature_request_id(): ?string { return $this->yousign_signature_request_id; }
    public function setYousign_signature_request_id(?string $yousign_signature_request_id): self { $this->yousign_signature_request_id = $yousign_signature_request_id; return $this; }

    public function getYousign_status(): ?string { return $this->yousign_status; }
    public function setYousign_status(?string $yousign_status): self { $this->yousign_status = $yousign_status ?? 'pending'; return $this; }

    public function getSignature_token(): ?string { return $this->signature_token; }
    public function setSignature_token(#[SensitiveParameter] ?string $signature_token): self { $this->signature_token = $signature_token; return $this; }

    public function getSignature_token_expires_at(): ?\DateTimeInterface { return $this->signature_token_expires_at; }
    protected function setSignature_token_expires_at(?\DateTimeInterface $signature_token_expires_at): self { $this->signature_token_expires_at = $signature_token_expires_at; return $this; }

    public function getSignature_sent_at(): ?\DateTimeInterface { return $this->signature_sent_at; }
    protected function setSignature_sent_at(?\DateTimeInterface $signature_sent_at): self { $this->signature_sent_at = $signature_sent_at; return $this; }

    public function isEmail_sent(): bool { return $this->email_sent ?? false; }
    public function setEmail_sent(?bool $email_sent): self { $this->email_sent = $email_sent ?? false; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreated_at(): ?\DateTimeImmutable { return $this->created_at; }
    protected function setCreated_at(?\DateTimeImmutable $created_at): self { $this->created_at = $created_at; return $this; }

    public function getCompleted_at(): ?\DateTimeInterface { return $this->completed_at; }
    protected function setCompleted_at(?\DateTimeInterface $completed_at): self { $this->completed_at = $completed_at; return $this; }
}
