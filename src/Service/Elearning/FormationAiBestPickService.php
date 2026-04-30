<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;

/**
 * Returns a recommended formation using the simplest approach.
 */
final class FormationAiBestPickService
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
    ) {
    }

    public function suggestForUser(User $user, string $notes = ''): array
    {
        if ($user->getUserType() === 'formateur') {
            return [
                'ok'      => false,
                'message' => 'Cette suggestion est réservée aux participants.',
            ];
        }

        $all = $this->formationRepository->findAllOrderedByStartDate();

        if (empty($all)) {
            return [
                'ok'      => false,
                'message' => 'Aucune formation disponible.',
            ];
        }

        // Return the first formation from the catalog
        $formation = $all[0];

        return [
            'ok'            => true,
            'formation_id'  => (int) $formation->getFormation_id(),
            'title'         => (string) $formation->getTitle(),
            'reason'        => 'Formation suggérée pour vous.',
            'en_ligne'      => $formation->getEnLigne(),
            'source'        => 'simple',
        ];
    }
}
