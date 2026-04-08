<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Marketplace\PanierRepository;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\Table(name: 'panier')]
#[ORM\HasLifecycleCallbacks]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_panier', type: 'integer')]
    private ?int $idPanier = null;

    #[ORM\Column(name: 'id_client', type: 'integer', nullable: false)]
    private ?int $idClient = null;

    #[ORM\Column(name: 'id_produit', type: 'integer', nullable: false)]
    private ?int $idProduit = null;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: false)]
    private ?int $quantite = null;

    #[ORM\Column(name: 'date_ajout', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateAjout = null;

    public function __construct()
    {
        $this->dateAjout = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->dateAjout === null) {
            $this->dateAjout = new \DateTime();
        }
    }

    // ── Getters / Setters ─────────────────────────────────────────────────

    public function getIdPanier(): ?int { return $this->idPanier; }

    public function getIdClient(): ?int { return $this->idClient; }
    public function setIdClient(?int $v): self { $this->idClient = $v; return $this; }

    public function getIdProduit(): ?int { return $this->idProduit; }
    public function setIdProduit(?int $v): self { $this->idProduit = $v; return $this; }

    public function getQuantite(): ?int { return $this->quantite; }
    public function setQuantite(?int $v): self { $this->quantite = $v; return $this; }

    public function getDateAjout(): ?\DateTimeInterface { return $this->dateAjout; }
    public function setDateAjout(?\DateTimeInterface $v): self { $this->dateAjout = $v; return $this; }
}
