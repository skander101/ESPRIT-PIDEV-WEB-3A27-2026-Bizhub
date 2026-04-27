<?php

declare(strict_types=1);

namespace App\Entity\Elearning;

use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRecommendationEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationRecommendationEventRepository::class)]
#[ORM\Table(name: 'formation_recommendation_event')]
#[ORM\Index(name: 'idx_fre_formation', columns: ['formation_id'])]
#[ORM\Index(name: 'idx_fre_user_created', columns: ['user_id', 'created_at'])]
class FormationRecommendationEvent
{
    public const EVENT_IMPRESSION = 'impression';

    public const EVENT_CLICK = 'click';

    public const EVENT_ENROLL = 'enroll';

    public const SECTION_PERSONALIZED = 'personalized';

    public const SECTION_TRENDING = 'trending';

    public const SECTION_POPULAR = 'popular';

    public const SECTION_NEW = 'new';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'formation_id', nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $section = self::SECTION_PERSONALIZED;

    #[ORM\Column(name: 'event_type', type: 'string', length: 24)]
    private string $eventType = self::EVENT_IMPRESSION;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    protected function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
