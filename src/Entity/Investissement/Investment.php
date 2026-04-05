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

    public function setInvestment_date(?\DateTimeInterface $investment_date): self
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

    public function setContract_url(?string $contract_url): self
    {
        $this->contract_url = $contract_url;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

}
