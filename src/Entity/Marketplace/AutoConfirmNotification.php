<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\AutoConfirmNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AutoConfirmNotificationRepository::class)]
#[ORM\Table(name: 'auto_confirm_notification')]
class AutoConfirmNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $investisseurId;

    #[ORM\Column(name: 'commande_id')]
    private int $commandeId;

    #[ORM\Column(length: 50)]
    private string $startupName;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $montantTtc;

    #[ORM\Column(type: 'integer')]
    private int $scoreAuto;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getInvestisseurId(): int { return $this->investisseurId; }
    public function setInvestisseurId(int $v): static { $this->investisseurId = $v; return $this; }

    public function getCommandeId(): int { return $this->commandeId; }
    public function setCommandeId(int $v): static { $this->commandeId = $v; return $this; }

    public function getStartupName(): string { return $this->startupName; }
    public function setStartupName(string $v): static { $this->startupName = $v; return $this; }

    public function getMontantTtc(): string { return $this->montantTtc; }
    public function setMontantTtc(string $v): static { $this->montantTtc = $v; return $this; }

    public function getScoreAuto(): int { return $this->scoreAuto; }
    public function setScoreAuto(int $v): static { $this->scoreAuto = $v; return $this; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
