<?php

namespace App\Entity\Marketplace;

use App\Entity\UsersAvis\User;
use App\Repository\Marketplace\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\Table(name: 'product_service')]
class ProductService
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const CATEGORIES = [
        'Informatique & Tech'  => 'informatique',
        'Mode & Vêtements'     => 'mode',
        'Alimentation'         => 'alimentation',
        'Électronique'         => 'electronique',
        'Mobilier & Déco'      => 'mobilier',
        'Services'             => 'services',
        'Santé & Bien-être'    => 'sante',
        'Sport & Loisirs'      => 'sport',
        'Autres'               => 'autres',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $product_id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $price = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'integer')]
    private int $stock = 0;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'seller_id', referencedColumnName: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $seller = null;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(mappedBy: 'productService', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders     = new ArrayCollection();
        $this->created_at = new \DateTime();
    }

    // ── Getters / Setters ──────────────────────────────────────────────────────

    public function getProductId(): ?int          { return $this->product_id; }

    public function getName(): ?string             { return $this->name; }
    public function setName(string $name): self   { $this->name = $name; return $this; }

    public function getDescription(): ?string                     { return $this->description; }
    public function setDescription(?string $description): self   { $this->description = $description; return $this; }

    public function getPrice(): ?float             { return $this->price; }
    public function setPrice(float $price): self  { $this->price = $price; return $this; }

    public function getCategory(): ?string                   { return $this->category; }
    public function setCategory(?string $category): self    { $this->category = $category; return $this; }

    public function getStock(): int              { return $this->stock; }
    public function setStock(int $stock): self  { $this->stock = $stock; return $this; }

    public function getImageUrl(): ?string                   { return $this->image_url; }
    public function setImageUrl(?string $image_url): self   { $this->image_url = $image_url; return $this; }

    public function getStatus(): string              { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function isActive(): bool                { return $this->status === self::STATUS_ACTIVE; }

    public function getCreatedAt(): \DateTimeInterface                    { return $this->created_at; }
    public function setCreatedAt(\DateTimeInterface $created_at): self   { $this->created_at = $created_at; return $this; }

    public function getSeller(): ?User             { return $this->seller; }
    public function setSeller(?User $seller): self { $this->seller = $seller; return $this; }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection { return $this->orders; }

    public function isInStock(): bool { return $this->stock > 0; }
}
