<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\NegotiationRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: NegotiationRepository::class)]
#[ORM\Table(name: 'negotiation')]
class Negotiation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $negotiation_id = null;

    public function getNegotiation_id(): ?int
    {
        return $this->negotiation_id;
    }

    public function setNegotiation_id(int $negotiation_id): self
    {
        $this->negotiation_id = $negotiation_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'negotiations')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id')]
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investorNegotiations')]
    #[ORM\JoinColumn(name: 'investor_id', referencedColumnName: 'user_id')]
    private ?User $investor = null;

    public function getInvestor(): ?User
    {
        return $this->investor;
    }

    public function setInvestor(?User $investor): self
    {
        $this->investor = $investor;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'startupNegotiations')]
    #[ORM\JoinColumn(name: 'startup_id', referencedColumnName: 'user_id')]
    private ?User $startup = null;

    public function getStartup(): ?User
    {
        return $this->startup;
    }

    public function setStartup(?User $startup): self
    {
        $this->startup = $startup;
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

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $proposed_amount = null;

    public function getProposed_amount(): ?float
    {
        return $this->proposed_amount;
    }

    public function setProposed_amount(?float $proposed_amount): self
    {
        $this->proposed_amount = $proposed_amount;
        return $this;
    }

    #[ORM\Column(type: 'decimal', nullable: true)]
    private ?float $final_amount = null;

    public function getFinal_amount(): ?float
    {
        return $this->final_amount;
    }

    public function setFinal_amount(?float $final_amount): self
    {
        $this->final_amount = $final_amount;
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

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    public function getUpdated_at(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdated_at(?\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: NegotiationMessage::class, mappedBy: 'negotiation')]
    private Collection $negotiationMessages;

    /**
     * @return Collection<int, NegotiationMessage>
     */
    public function getNegotiationMessages(): Collection
    {
        if (!$this->negotiationMessages instanceof Collection) {
            $this->negotiationMessages = new ArrayCollection();
        }
        return $this->negotiationMessages;
    }

    public function addNegotiationMessage(NegotiationMessage $negotiationMessage): self
    {
        if (!$this->getNegotiationMessages()->contains($negotiationMessage)) {
            $this->getNegotiationMessages()->add($negotiationMessage);
        }
        return $this;
    }

    public function removeNegotiationMessage(NegotiationMessage $negotiationMessage): self
    {
        $this->getNegotiationMessages()->removeElement($negotiationMessage);
        return $this;
    }

}
