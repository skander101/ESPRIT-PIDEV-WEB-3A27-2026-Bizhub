<?php

namespace App\Service\Community;

use App\Entity\Community\Post;

final class PostTestManager
{
    public function validate(Post $post): bool
    {
        $title = trim((string) $post->getTitle());
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }
        if (mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Le titre ne doit pas dépasser 255 caractères');
        }
        $post->setTitle($title);

        $content = trim((string) $post->getContent());
        if ($content === '') {
            throw new \InvalidArgumentException('Le contenu est obligatoire');
        }
        $post->setContent($content);

        $category = $post->getCategory();
        $category = $category !== null ? trim($category) : '';
        $post->setCategory($category !== '' ? $category : 'General');

        $location = $post->getLocation();
        $location = $location !== null ? trim($location) : null;
        $post->setLocation($location !== '' ? $location : null);

        $this->validateCoordinate($post->getLocationLat(), -90.0, 90.0, 'Latitude invalide');
        $this->validateCoordinate($post->getLocationLon(), -180.0, 180.0, 'Longitude invalide');

        $post->setLocationLat($this->normalizeCoordinate($post->getLocationLat()));
        $post->setLocationLon($this->normalizeCoordinate($post->getLocationLon()));

        return true;
    }

    private function validateCoordinate(?string $value, float $min, float $max, string $message): void
    {
        if ($value === null) {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException($message);
        }

        $f = (float) $value;
        if ($f < $min || $f > $max) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function normalizeCoordinate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
