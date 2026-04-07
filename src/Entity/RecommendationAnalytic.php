<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RecommendationAnalyticRepository;

#[ORM\Entity(repositoryClass: RecommendationAnalyticRepository::class)]
#[ORM\Table(name: 'recommendation_analytic')]
class RecommendationAnalytic
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
