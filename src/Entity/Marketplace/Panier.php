<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\PanierRepository;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\Table(name: 'panier')]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_panier = null;

    public function getId_panier(): ?int
    {
        return $this->id_panier;
    }

    public function setId_panier(int $id_panier): self
    {
        $this->id_panier = $id_panier;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $id_client = null;

    public function getId_client(): ?int
    {
        return $this->id_client;
    }

    public function setId_client(int $id_client): self
    {
        $this->id_client = $id_client;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $id_produit = null;

    public function getId_produit(): ?int
    {
        return $this->id_produit;
    }

    public function setId_produit(int $id_produit): self
    {
        $this->id_produit = $id_produit;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $quantite = null;

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_ajout = null;

    public function getDate_ajout(): ?\DateTimeInterface
    {
        return $this->date_ajout;
    }

    public function setDate_ajout(\DateTimeInterface $date_ajout): self
    {
        $this->date_ajout = $date_ajout;
        return $this;
    }

}
