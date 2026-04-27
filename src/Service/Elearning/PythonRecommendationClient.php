<?php

declare(strict_types=1);

namespace App\Service\Elearning;

use App\Entity\UsersAvis\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls FastAPI recommender: POST /recommendations/{userId}
 */
final class PythonRecommendationClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiBaseUrl,
    ) {
    }

    /**
     * @return list<int>|null null if API disabled or error
     */
    public function fetchRecommendedFormationIds(User $user, int $max = 16): ?array
    {
        $base = rtrim($this->apiBaseUrl, '/');
        if ($base === '') {
            return null;
        }

        $url = $base . '/recommendations/' . $user->getUserId();

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => 3.5,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => ['max' => $max],
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Recommendations API HTTP ' . $status . ' for user ' . $user->getUserId());

                return null;
            }
            $data = $response->toArray(false);
            $ids = $data['formation_ids'] ?? null;
            if (!is_array($ids)) {
                return null;
            }
            $out = [];
            foreach ($ids as $id) {
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    $out[] = (int) $id;
                }
            }

            return array_values(array_unique($out));
        } catch (\Throwable $e) {
            $this->logger->notice('Recommendations API unreachable: ' . $e->getMessage());

            return null;
        }
    }
}
