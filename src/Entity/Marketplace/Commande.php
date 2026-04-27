<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\Marketplace\CommandeRepository;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
#[ORM\HasLifecycleCallbacks]
class Commande
{
    const STATUT_ATTENTE          = 'en_attente';
    const STATUT_CONFIRMEE        = 'confirmee';
    const STATUT_EN_COURS_PAIEMENT = 'en_cours_paiement';
    const STATUT_PAYEE            = 'payee';
    const STATUT_EN_PREPARATION   = 'en_preparation';
    const STATUT_ANNULEE          = 'annulee';
    const STATUT_LIVREE           = 'livree';

    const PAYMENT_STATUSES = [
        'non initié' => 'non initié',
        'en cours'   => 'en cours',
        'complété'   => 'complété',
        'échoué'     => 'échoué',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_commande', type: 'integer')]
    private ?int $idCommande = null;

    #[ORM\Column(name: 'id_client', type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    #[Assert\Positive(message: 'ID client invalide.')]
    private ?int $idClient = null;

    #[ORM\Column(name: 'id_produit', type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'ID produit invalide.')]
    private ?int $idProduit = null;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'La quantité doit être ≥ 1.')]
    #[Assert\LessThanOrEqual(value: 999, message: 'Maximum 999 unités.')]
    private ?int $quantite = null;

    #[ORM\Column(name: 'date_commande', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 50, nullable: false)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [
            self::STATUT_ATTENTE,
            self::STATUT_CONFIRMEE,
            self::STATUT_EN_COURS_PAIEMENT,
            self::STATUT_PAYEE,
            self::STATUT_EN_PREPARATION,
            self::STATUT_ANNULEE,
            self::STATUT_LIVREE,
        ],
        message: 'Statut invalide.'
    )]
    private string $statut = self::STATUT_ATTENTE;

    #[ORM\Column(name: 'payment_status', type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['non initié', 'en cours', 'complété', 'échoué'],
        message: 'Statut de paiement invalide.'
    )]
    private ?string $paymentStatus = null;

    #[ORM\Column(name: 'payment_ref', type: 'string', length: 255, nullable: true)]
    private ?string $paymentRef = null;

    #[ORM\Column(name: 'payment_url', type: 'text', nullable: true)]
    private ?string $paymentUrl = null;

    #[ORM\Column(name: 'est_payee', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $estPayee = false;

    #[ORM\Column(name: 'paid_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(name: 'stripe_session_id', type: 'string', length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(name: 'stripe_payment_intent_id', type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(name: 'score_auto', type: 'integer', nullable: true)]
    private ?int $scoreAuto = null;

    #[ORM\Column(name: 'total_ht', type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $totalHt = null;

    #[ORM\Column(name: 'total_tva', type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $totalTva = null;

    #[ORM\Column(name: 'total_ttc', type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $totalTtc = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes       = new ArrayCollection();
        $this->dateCommande = new \DateTime();
        $this->estPayee     = false;
        $this->statut       = self::STATUT_ATTENTE;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->dateCommande === null) {
            $this->dateCommande = new \DateTime();
        }
    }

    public function getIdCommande(): ?int { return $this->idCommande; }

    public function getIdClient(): ?int { return $this->idClient; }
    public function setIdClient(?int $v): self { $this->idClient = $v; return $this; }

    public function getIdProduit(): ?int { return $this->idProduit; }
    public function setIdProduit(?int $v): self { $this->idProduit = $v; return $this; }

    public function getQuantite(): ?int { return $this->quantite; }
    public function setQuantite(?int $v): self { $this->quantite = $v; return $this; }

    public function getDateCommande(): ?\DateTimeInterface { return $this->dateCommande; }
    public function setDateCommande(?\DateTimeInterface $v): self { $this->dateCommande = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): self { $this->statut = $v; return $this; }

    /**
     * Statut cohérent tenant compte à la fois de estPayee et de statut.
     * Évite les incohérences dues à des webhooks tardifs ou des edge-cases
     * qui laissent estPayee=true mais statut != 'payee'.
     */
    public function getEffectiveStatut(): string
    {
        $paidStates = [self::STATUT_PAYEE, self::STATUT_EN_PREPARATION, self::STATUT_LIVREE];
        if ($this->estPayee && !in_array($this->statut, $paidStates, true)) {
            return self::STATUT_PAYEE; // self-heal: payment confirmed, force paid state
        }
        return $this->statut;
    }

    public function getPaymentStatus(): ?string { return $this->paymentStatus; }
    public function setPaymentStatus(?string $v): self { $this->paymentStatus = $v; return $this; }

    public function getPaymentRef(): ?string { return $this->paymentRef; }
    public function setPaymentRef(?string $v): self { $this->paymentRef = $v; return $this; }

    public function getPaymentUrl(): ?string { return $this->paymentUrl; }
    public function setPaymentUrl(?string $v): self { $this->paymentUrl = $v; return $this; }

    public function isEstPayee(): bool { return $this->estPayee; }
    public function setEstPayee(bool $v): self { $this->estPayee = $v; return $this; }

    public function getPaidAt(): ?\DateTimeInterface { return $this->paidAt; }
    public function setPaidAt(?\DateTimeInterface $v): self { $this->paidAt = $v; return $this; }

    public function getTotalHt(): ?string { return $this->totalHt; }
    public function setTotalHt(?string $v): self { $this->totalHt = $v; return $this; }

    public function getTotalTva(): ?string { return $this->totalTva; }
    public function setTotalTva(?string $v): self { $this->totalTva = $v; return $this; }

    public function getTotalTtc(): ?string { return $this->totalTtc; }
    public function setTotalTtc(?string $v): self { $this->totalTtc = $v; return $this; }

    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(CommandeLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }
        return $this;
    }

    public function removeLigne(CommandeLigne $ligne): self
    {
        $this->lignes->removeElement($ligne);
        return $this;
    }

    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $v): self { $this->stripeSessionId = $v; return $this; }

    public function getStripePaymentIntentId(): ?string { return $this->stripePaymentIntentId; }
    public function setStripePaymentIntentId(?string $v): self { $this->stripePaymentIntentId = $v; return $this; }

    public function getScoreAuto(): ?int { return $this->scoreAuto; }
    public function setScoreAuto(?int $v): self { $this->scoreAuto = $v; return $this; }

    public function __toString(): string { return 'Commande #' . $this->idCommande; }
}
