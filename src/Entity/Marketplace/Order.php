<?php

namespace App\Entity\Marketplace;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\OrderRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'order')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $order_id = null;

    public function getOrder_id(): ?int
    {
        return $this->order_id;
    }

    public function setOrder_id(int $order_id): self
    {
        $this->order_id = $order_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'buyer_id', referencedColumnName: 'user_id')]
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

    #[ORM\ManyToOne(targetEntity: ProductService::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'product_id')]
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
    private ?int $quantity = null;

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $unit_price = null;

    public function getUnit_price(): ?float
    {
        return $this->unit_price;
    }

    public function setUnit_price(?float $unit_price): self
    {
        $this->unit_price = $unit_price;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $total_price = null;

    public function getTotal_price(): ?float
    {
        return $this->total_price;
    }

    public function setTotal_price(?float $total_price): self
    {
        $this->total_price = $total_price;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $order_date = null;

    public function getOrder_date(): ?\DateTimeInterface
    {
        return $this->order_date;
    }

    public function setOrder_date(?\DateTimeInterface $order_date): self
    {
        $this->order_date = $order_date;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $delivery_address = null;

    public function getDelivery_address(): ?string
    {
        return $this->delivery_address;
    }

    public function setDelivery_address(?string $delivery_address): self
    {
        $this->delivery_address = $delivery_address;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

}
