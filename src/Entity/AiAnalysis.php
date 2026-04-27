<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AiAnalysisRepository;
use App\Entity\Investissement\Project;

#[ORM\Entity(repositoryClass: AiAnalysisRepository::class)]
#[ORM\Table(name: 'ai_analysis')]
class AiAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'aiAnalysis')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }
}
