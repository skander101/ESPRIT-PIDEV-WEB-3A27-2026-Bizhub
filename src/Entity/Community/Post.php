<?php

namespace App\Entity\Community;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\Community\PostRepository;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
class Post
{
    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $post_id = null;

    public function getPost_id(): ?int
    {
        return $this->post_id;
    }

    public function setPost_id(int $post_id): self
    {
        $this->post_id = $post_id;
        return $this;
    }

#[ORM\Column(type: 'integer', nullable: false)]
    private int $user_id;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: false)]
    private string $content;

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $category = null;

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $created_at;

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    protected function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $media_url = null;

    public function getMedia_url(): ?string
    {
        return $this->media_url;
    }

    public function setMedia_url(?string $media_url): self
    {
        $this->media_url = $media_url;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $media_type = null;

    public function getMedia_type(): ?string
    {
        return $this->media_type;
    }

    public function setMedia_type(?string $media_type): self
    {
        $this->media_type = $media_type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $location = null;

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $location_lat = null;

    public function getLocation_lat(): ?string
    {
        return $this->location_lat;
    }

    public function setLocation_lat(?string $location_lat): self
    {
        $this->location_lat = $location_lat;
        return $this;
    }

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $location_lon = null;

    public function getLocation_lon(): ?string
    {
        return $this->location_lon;
    }

    public function setLocation_lon(?string $location_lon): self
    {
        $this->location_lon = $location_lon;
        return $this;
    }

}
