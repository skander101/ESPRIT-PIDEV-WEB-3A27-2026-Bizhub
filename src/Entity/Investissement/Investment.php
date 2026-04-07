<?php

namespace App\Entity\Investissement;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\InvestmentRepository;
use App\Entity\UsersAvis\User;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
#[ORM\Table(name: 'investment')]
class Investment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $investment_id = null;

    public function getInvestment_id(): ?int
    {
        return $this->investment_id;
    }

    public function setInvestment_id(int $investment_id): self
    {
        $this->investment_id = $investment_id;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'investments')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'investments')]
    #[ORM\JoinColumn(name: 'investor_id', referencedColumnName: 'user_id')]
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

    #[ORM\Column(type: 'decimal', nullable: false)]
    private ?float $amount = null;

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $investment_date = null;

    public function getInvestment_date(): ?\DateTimeInterface
    {
        return $this->investment_date;
    }

    public function getInvestmentDate(): ?\DateTimeInterface
    {
        return $this->investment_date;
    }

    public function setInvestment_date(?\DateTimeInterface $investment_date): self
    {
        $this->investment_date = $investment_date;
        return $this;
    }

    public function setInvestmentDate(?\DateTimeInterface $investment_date): self
    {
        $this->investment_date = $investment_date;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $contract_url = null;

    public function getContract_url(): ?string
    {
        return $this->contract_url;
    }

    public function getContractUrl(): ?string
    {
        return $this->contract_url;
    }

    public function setContract_url(?string $contract_url): self
    {
        $this->contract_url = $contract_url;
        return $this;
    }

    public function setContractUrl(?string $contract_url): self
    {
        $this->contract_url = $contract_url;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $payment_mode = 'virement';

    public function getPaymentMode(): ?string { return $this->payment_mode; }
    public function setPaymentMode(?string $payment_mode): self { $this->payment_mode = $payment_mode; return $this; }

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $statut = 'en_attente';

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $type_investissement = null;

    public function getTypeInvestissement(): ?string { return $this->type_investissement; }
    public function setTypeInvestissement(?string $type_investissement): self { $this->type_investissement = $type_investissement; return $this; }

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $duree_souhaitee = null;

    public function getDureeSouhaitee(): ?string { return $this->duree_souhaitee; }
    public function setDureeSouhaitee(?string $duree_souhaitee): self { $this->duree_souhaitee = $duree_souhaitee; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conditions_particulieres = null;

    public function getConditionsParticulieres(): ?string { return $this->conditions_particulieres; }
    public function setConditionsParticulieres(?string $conditions_particulieres): self { $this->conditions_particulieres = $conditions_particulieres; return $this; }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
