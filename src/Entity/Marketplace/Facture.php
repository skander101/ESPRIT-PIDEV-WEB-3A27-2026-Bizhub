<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\FactureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Table(name: 'facture')]
#[ORM\HasLifecycleCallbacks]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    /**
     * OneToOne vers Commande — une commande = une facture maximum.
     */
    #[ORM\OneToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'commande_id', nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    /**
     * Numéro de facture unique : FAC-YYYY-NNNNN (ex: FAC-2026-00042)
     */
    #[ORM\Column(name: 'numero_facture', type: 'string', length: 50, nullable: false, unique: true)]
    private string $numeroFacture = '';

    #[ORM\Column(name: 'date_facture', type: 'datetime', nullable: false)]
    private \DateTimeInterface $dateFacture;

    #[ORM\Column(name: 'total_ht', type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $totalHt = '0.00';

    #[ORM\Column(name: 'total_tva', type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $totalTva = '0.00';

    #[ORM\Column(name: 'total_ttc', type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $totalTtc = '0.00';

    /**
     * Référence Stripe (session ID ou payment_intent ID).
     */
    #[ORM\Column(name: 'stripe_ref', type: 'string', length: 255, nullable: true)]
    private ?string $stripeRef = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        if ($this->dateFacture === null) {
            $this->dateFacture = new \DateTime();
        }
    }

    // ── Getters / Setters ────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $v): self { $this->commande = $v; return $this; }

    public function getNumeroFacture(): string { return $this->numeroFacture; }
    public function setNumeroFacture(string $v): self { $this->numeroFacture = $v; return $this; }

public function getDateFacture(): \DateTimeInterface { return $this->dateFacture; }
    protected function setDateFacture(\DateTimeInterface $v): self { $this->dateFacture = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    protected function setCreatedAt(\DateTimeInterface $v): self { $this->createdAt = $v; return $this; }

    public function __toString(): string { return $this->numeroFacture; }
}
