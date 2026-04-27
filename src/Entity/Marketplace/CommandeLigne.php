<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Marketplace\CommandeLigneRepository;

#[ORM\Entity(repositoryClass: CommandeLigneRepository::class)]
#[ORM\Table(name: 'commande_ligne')]
#[ORM\HasLifecycleCallbacks]
class CommandeLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_ligne', type: 'integer')]
    private ?int $idLigne = null;

    // Relation ManyToOne vers Commande
    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'commande_id', nullable: false)]
    private ?Commande $commande = null;

    #[ORM\Column(name: 'id_produit', type: 'integer', nullable: false)]
    private int $idProduit;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: false)]
    private int $quantite;

    #[ORM\Column(name: 'prix_ht_unitaire', type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $prixHtUnitaire = '0.00';

#[ORM\Column(name: 'tva_rate', type: 'decimal', precision: 5, scale: 2, nullable: false, options: ['default' => '19.00'])]
    private string $tvaRate = '19.00';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->tvaRate   = '19.00';
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────

    public function getTotalHt(): float
    {
        return (float) $this->prixHtUnitaire * (int) $this->quantite;
    }

    public function getTotalTtc(): float
    {
        return $this->getTotalHt() * (1 + (float) $this->tvaRate / 100);
    }

    // ── Getters / Setters ─────────────────────────────────────────────────

    public function getIdLigne(): ?int { return $this->idLigne; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $v): self { $this->commande = $v; return $this; }

    public function getIdProduit(): ?int { return $this->idProduit; }
    public function setIdProduit(int $v): self { $this->idProduit = $v; return $this; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): self { $this->quantite = $v; return $this; }

    public function getPrixHtUnitaire(): ?string { return $this->prixHtUnitaire; }
    public function setPrixHtUnitaire(?string $v): self { $this->prixHtUnitaire = $v; return $this; }

    public function getTvaRate(): string { return $this->tvaRate; }
    public function setTvaRate(string $v): self { $this->tvaRate = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $v): self { $this->createdAt = $v; return $this; }
}
