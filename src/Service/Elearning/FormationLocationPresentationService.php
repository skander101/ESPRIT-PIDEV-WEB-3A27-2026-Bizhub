<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class FormationLocationPresentationService
{
    public function buildGoogleMapsUrl(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return sprintf(
            'https://www.google.com/maps/search/?api=1&query=%.7f,%.7f',
            $latitude,
            $longitude
        );
    }

    public function googleMapsUrlForFormation(Formation $formation): ?string
    {
        if ($formation->isEnLigne()) {
            return null;
        }

        return $this->buildGoogleMapsUrl($formation->getLatitude(), $formation->getLongitude());
    }

    /**
     * Raw SVG string for QR encoding the Google Maps URL (for inline image responses).
     */
    public function locationQrSvgStringForFormation(Formation $formation): ?string
    {
        $url = $this->googleMapsUrlForFormation($formation);
        if ($url === null) {
            return null;
        }

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new RendererStyle(220, 2),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }
}
