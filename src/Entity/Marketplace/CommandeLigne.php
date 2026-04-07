<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CommandeLigneRepository;

#[ORM\Entity(repositoryClass: CommandeLigneRepository::class)]
#[ORM\Table(name: 'commande_ligne')]
class CommandeLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_ligne = null;

    public function getId_ligne(): ?int
    {
        return $this->id_ligne;
    }

    public function setId_ligne(int $id_ligne): self
    {
        $this->id_ligne = $id_ligne;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $id_commande = null;

    public function getId_commande(): ?int
    {
        return $this->id_commande;
    }

    public function setId_commande(int $id_commande): self
    {
        $this->id_commande = $id_commande;
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

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $prix_ht_unitaire = null;

    public function getPrix_ht_unitaire(): ?float
    {
        return $this->prix_ht_unitaire;
    }

    public function setPrix_ht_unitaire(float $prix_ht_unitaire): self
    {
        $this->prix_ht_unitaire = $prix_ht_unitaire;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $tva_rate = null;

    public function getTva_rate(): ?float
    {
        return $this->tva_rate;
    }

    public function setTva_rate(float $tva_rate): self
    {
        $this->tva_rate = $tva_rate;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
