<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\ProduitServiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitServiceRepository::class)]
#[ORM\Table(name: 'produit_service')]
#[ORM\HasLifecycleCallbacks]
class ProduitService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_produit', type: 'integer')]
    private ?int $idProduit = null;

    #[ORM\Column(name: 'id_profile', type: 'integer', nullable: false)]
    private ?int $idProfile = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 255, nullable: false)]
    private ?string $nom = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 10, scale: 3, nullable: false)]
    private ?string $prix = null;

    #[ORM\Column(name: 'quantite', type: 'integer', nullable: false)]
    private ?int $quantite = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(name: 'disponible', type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $disponible = true;

    #[ORM\Column(name: 'owner_user_id', type: 'integer', nullable: true)]
    private ?int $ownerUserId = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 255, nullable: true)]
    private ?string $imagePath = null;

    public function getIdProduit(): ?int
    {
        return $this->idProduit;
    }

    public function getIdProfile(): ?int
    {
        return $this->idProfile;
    }

    public function setIdProfile(?int $v): self
    {
        $this->idProfile = $v;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $v): self
    {
        $this->nom = $v;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $v): self
    {
        $this->description = $v;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $v): self
    {
        $this->prix = $v;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $v): self
    {
        $this->quantite = $v;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $v): self
    {
        $this->categorie = $v;

        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $v): self
    {
        $this->disponible = $v;

        return $this;
    }

    public function getOwnerUserId(): ?int
    {
        return $this->ownerUserId;
    }

    public function setOwnerUserId(?int $v): self
    {
        $this->ownerUserId = $v;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $v): self
    {
        $this->imagePath = $v;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}