<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Elearning\Formation;
use App\Service\Elearning\FormationLocationPresentationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

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
}
