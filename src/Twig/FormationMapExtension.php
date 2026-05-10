<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Elearning\Formation;
use App\Service\Elearning\FormationLocationPresentationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

final class FormationMapExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormationLocationPresentationService $formationLocationPresentationService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('formation_google_maps_url', function (Formation $formation): ?string {
                return $this->formationLocationPresentationService->googleMapsUrlForFormation($formation);
            }),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_lieu', [$this, 'formatLieu']),
        ];
    }

    public function formatLieu(?string $lieu): ?string
    {
        if ($lieu === null || $lieu === '') {
            return null;
        }

        if (preg_match('/^-?\d+\.?\d*,\s*-?\d+\.?\d*$/', $lieu)) {
            return '📍 Coordonnées uniquement';
        }

        return $lieu;
    }
}
