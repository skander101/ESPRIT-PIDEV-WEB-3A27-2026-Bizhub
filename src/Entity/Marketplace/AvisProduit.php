<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\AvisProduitRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: AvisProduitRepository::class)]
#[ORM\Table(name: 'avis_produit')]
class AvisProduit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $avis_produit_id = null;

    public function getAvis_produit_id(): ?int
    {
        return $this->avis_produit_id;
    }

    public function setAvis_produit_id(int $avis_produit_id): self
    {
        $this->avis_produit_id = $avis_produit_id;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'avisProduit')]
    #[ORM\JoinColumn(name: 'buyer_id', referencedColumnName: 'user_id', unique: true)]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\OneToOne(targetEntity: ProductService::class, inversedBy: 'avisProduit')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'product_id', unique: true)]
    private ?ProductService $productService = null;

    public function getProductService(): ?ProductService
    {
        return $this->productService;
    }

    public function setProductService(?ProductService $productService): self
    {
        $this->productService = $productService;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null;

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_verified = null;

    public function is_verified(): ?bool
    {
        return $this->is_verified;
    }

    public function setIs_verified(?bool $is_verified): self
    {
        $this->is_verified = $is_verified;
        return $this;
    }

}
