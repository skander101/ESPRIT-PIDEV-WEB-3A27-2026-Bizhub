<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CommandeRepository;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
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

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $id_produit = null;

    public function getId_produit(): ?int
    {
        return $this->id_produit;
    }

    public function setId_produit(?int $id_produit): self
    {
        $this->id_produit = $id_produit;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quantite = null;

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_commande = null;

    public function getDate_commande(): ?\DateTimeInterface
    {
        return $this->date_commande;
    }

    public function setDate_commande(\DateTimeInterface $date_commande): self
    {
        $this->date_commande = $date_commande;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_status = null;

    public function getPayment_status(): ?string
    {
        return $this->payment_status;
    }

    public function setPayment_status(?string $payment_status): self
    {
        $this->payment_status = $payment_status;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_ref = null;

    public function getPayment_ref(): ?string
    {
        return $this->payment_ref;
    }

    public function setPayment_ref(?string $payment_ref): self
    {
        $this->payment_ref = $payment_ref;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $payment_url = null;

    public function getPayment_url(): ?string
    {
        return $this->payment_url;
    }

    public function setPayment_url(?string $payment_url): self
    {
        $this->payment_url = $payment_url;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $est_payee = null;

    public function isEst_payee(): ?bool
    {
        return $this->est_payee;
    }

    public function setEst_payee(bool $est_payee): self
    {
        $this->est_payee = $est_payee;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $paid_at = null;

    public function getPaid_at(): ?\DateTimeInterface
    {
        return $this->paid_at;
    }

    public function setPaid_at(?\DateTimeInterface $paid_at): self
    {
        $this->paid_at = $paid_at;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $total_ht = null;

    public function getTotal_ht(): ?float
    {
        return $this->total_ht;
    }

    public function setTotal_ht(?float $total_ht): self
    {
        $this->total_ht = $total_ht;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $total_tva = null;

    public function getTotal_tva(): ?float
    {
        return $this->total_tva;
    }

    public function setTotal_tva(?float $total_tva): self
    {
        $this->total_tva = $total_tva;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $total_ttc = null;

    public function getTotal_ttc(): ?float
    {
        return $this->total_ttc;
    }

    public function setTotal_ttc(?float $total_ttc): self
    {
        $this->total_ttc = $total_ttc;
        return $this;
    }

}
