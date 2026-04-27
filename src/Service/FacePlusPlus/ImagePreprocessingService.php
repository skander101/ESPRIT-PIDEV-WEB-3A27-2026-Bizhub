<?php

declare(strict_types=1);

namespace App\Service\FacePlusPlus;

class ImagePreprocessingService
{
    public function toGrayscaleBase64(string $base64Image): string
    {
        if (str_starts_with($base64Image, 'data:')) {
            $base64Image = preg_replace('#^data:image/[^;]+;base64,#', '', $base64Image) ?: $base64Image;
        }

        return $base64Image;
    }
}