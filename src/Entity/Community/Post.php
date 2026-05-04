<?php

namespace App\Entity\Community;

use App\Repository\Community\PostRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'post_id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\Column(name: 'media_url', length: 255, nullable: true)]
    private ?string $mediaUrl = null;

    #[ORM\Column(name: 'media_type', length: 255, nullable: true)]
    private ?string $mediaType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'location_lat', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $locationLat = null;

    #[ORM\Column(name: 'location_lon', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $locationLon = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    private function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function setMediaUrl(?string $mediaUrl): static
    {
        $this->mediaUrl = $mediaUrl;
        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function setMediaType(?string $mediaType): static
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getLocationLat(): ?string
    {
        return $this->locationLat;
    }

    public function setLocationLat(?string $locationLat): static
    {
        $this->locationLat = $locationLat;
        return $this;
    }

    public function getLocationLon(): ?string
    {
        return $this->locationLon;
    }

    public function setLocationLon(?string $locationLon): static
    {
        $this->locationLon = $locationLon;
        return $this;
    }
}
