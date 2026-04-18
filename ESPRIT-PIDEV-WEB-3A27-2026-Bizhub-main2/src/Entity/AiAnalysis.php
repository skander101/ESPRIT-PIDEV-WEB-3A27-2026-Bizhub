<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AiAnalysisRepository;

#[ORM\Entity(repositoryClass: AiAnalysisRepository::class)]
#[ORM\Table(name: 'ai_analysis')]
class AiAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
