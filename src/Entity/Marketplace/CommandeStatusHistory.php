<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\CommandeStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeStatusHistoryRepository::class)]
#[ORM\Table(name: 'commande_status_history')]
#[ORM\HasLifecycleCallbacks]
class CommandeStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id_commande', nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(name: 'statut_precedent', type: 'string', length: 50, nullable: true)]
    private ?string $statutPrecedent = null;

    #[ORM\Column(name: 'statut_nouveau', type: 'string', length: 50, nullable: false)]
    private string $statutNouveau;

    #[ORM\Column(name: 'changed_at', type: 'datetime', nullable: false)]
    private \DateTimeInterface $changedAt;

    #[ORM\Column(name: 'changed_by_user_id', type: 'integer', nullable: true)]
    private ?int $changedByUserId = null;

    #[ORM\Column(name: 'note', type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->changedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCommande(): Commande { return $this->commande; }
    public function setCommande(Commande $commande): self { $this->commande = $commande; return $this; }

    public function getStatutPrecedent(): ?string { return $this->statutPrecedent; }
    public function setStatutPrecedent(?string $v): self { $this->statutPrecedent = $v; return $this; }

    public function getStatutNouveau(): string { return $this->statutNouveau; }
    public function setStatutNouveau(string $v): self { $this->statutNouveau = $v; return $this; }

    public function getChangedAt(): \DateTimeInterface { return $this->changedAt; }

    public function getChangedByUserId(): ?int { return $this->changedByUserId; }
    public function setChangedByUserId(?int $v): self { $this->changedByUserId = $v; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): self { $this->note = $v; return $this; }
}
