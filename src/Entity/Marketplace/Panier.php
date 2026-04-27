<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    #[Assert\Positive(message: 'ID client invalide.')]
    private ?int $idClient = null;

    #[ORM\Column(name: 'id_produit', type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'Le produit est obligatoire.')]
    #[Assert\Positive(message: 'ID produit invalide.')]
    private ?int $idProduit = null;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'La quantité est obligatoire.')]
    #[Assert\Positive(message: 'La quantité doit être ≥ 1.')]
    #[Assert\LessThanOrEqual(value: 999, message: 'Maximum 999 unités.')]
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
