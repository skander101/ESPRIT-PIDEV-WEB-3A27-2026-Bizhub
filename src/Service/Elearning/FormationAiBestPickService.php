<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use App\Repository\Elearning\FormationRepository;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Returns a recommended formation using the FastAPI recommender.
 */
final class FormationAiBestPickService
{
    private const RECOMMENDER_URL = 'http://127.0.0.1:8765';

    public function __construct(
        private readonly FormationRepository $formationRepository,
    ) {
    }

    public function suggestForUser(User $user, string $notes = ''): array
    {
        if ($user->getUserType() === 'formateur') {
            return [
                'ok'      => false,
                'message' => 'Cette suggestion est réservés aux participants.',
            ];
        }

        $userId = $user->getUser_id();
        
        try {
            $client = HttpClient::create();
            $response = $client->request('POST', self::RECOMMENDER_URL . '/recommendations/' . $userId, [
                'json' => ['max' => 16],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return [
                    'ok'      => false,
                    'message' => 'Service indisponible (code: ' . $statusCode . ')',
                ];
            }

            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if (!is_array($data) || empty($data['formation_ids'])) {
                return [
                    'ok'      => false,
                    'message' => 'Aucune formation recommandée',
                ];
            }

            $firstId = (int) $data['formation_ids'][0];
            $formation = $this->formationRepository->findOneBy(['formation_id' => $firstId]);

            if (!$formation) {
                return [
                    'ok'      => false,
                    'message' => 'Formation #' . $firstId . ' introuvable',
                ];
            }

            return [
                'ok'            => true,
                'formation_id'  => (int) $formation->getFormation_id(),
                'title'         => (string) $formation->getTitle(),
                'reason'        => 'Formation recommandée pour vous',
                'en_ligne'      => $formation->getEnLigne(),
                'source'        => 'ia',
            ];
        } catch (\Throwable $e) {
            error_log('FormationAiBestPickService error: ' . $e->getMessage());
            return [
                'ok'      => false,
                'message' => 'Erreur de connexion: ' . substr($e->getMessage(), 0, 50),
            ];
        }
    }
}
