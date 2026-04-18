<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Intégration avec l'API Yousign v3 pour la signature électronique des contrats.
 */
class YousignService
{
    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
        private string $apiKey,
        private string $baseUrl,
        private string $projectDir,
    ) {}

    /**
     * Envoie une demande de signature Yousign pour l'acheteur.
     * Appelé par DealController.
     */
    public function sendSignatureRequest(Deal $deal, User $buyer): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('La clé API Yousign n\'est pas configurée. Ajoutez YOUSIGN_API_KEY dans votre .env.');
        }

        $contractPath = $deal->getContract_pdf_path();
        if (!$contractPath) {
            throw new \RuntimeException('Le contrat PDF n\'a pas encore été généré.');
        }

        $absPath = $this->projectDir . '/public' . $contractPath;
        if (!file_exists($absPath)) {
            throw new \RuntimeException('Fichier contrat introuvable : ' . $absPath);
        }

        // 1. Créer la demande de signature
        $signatureRequest = $this->request('POST', '/signature_requests', [
            'name'          => sprintf('Contrat deal #%d — BizHub', $deal->getDeal_id()),
            'delivery_mode' => 'email',
            'timezone'      => 'Africa/Tunis',
        ]);

        $requestId = $signatureRequest['id'] ?? null;
        if (!$requestId) {
            throw new \RuntimeException('Yousign n\'a pas retourné d\'ID de demande : ' . json_encode($signatureRequest));
        }

        // 2. Uploader le document
        $documentResponse = $this->uploadDocument($requestId, $absPath);
        $documentId = $documentResponse['id'] ?? null;
        if (!$documentId) {
            throw new \RuntimeException('Impossible d\'uploader le document sur Yousign.');
        }

        // 3. Ajouter le signataire (acheteur/investisseur)
        $this->request('POST', "/signature_requests/{$requestId}/signers", [
            'info' => [
                'first_name'   => $buyer->getFirstName() ?? 'Signataire',
                'last_name'    => $buyer->getLastName() ?? '',
                'email'        => $buyer->getEmail(),
                'locale'       => 'fr',
            ],
            'signature_level'                => 'electronic_signature',
            'signature_authentication_mode'  => 'no_otp',
            'fields' => [[
                'document_id' => $documentId,
                'type'        => 'signature',
                'page'        => 1,
                'x'           => 80,
                'y'           => 600,
                'width'       => 180,
                'height'      => 60,
            ]],
        ]);

        // 4. Activer
        $this->request('POST', "/signature_requests/{$requestId}/activate");

        // 5. Persister l'ID sur le deal
        $deal->setYousign_signature_request_id($requestId);
        $deal->setYousign_status('ongoing');
        $deal->setEmail_sent(true);
        $this->em->flush();
    }

    /**
     * Vérifie le statut Yousign et met à jour le deal en conséquence.
     * Retourne true si la signature est complète.
     * Appelé par DealController::yousignSync().
     */
    public function syncDealStatus(Deal $deal): bool
    {
        $requestId = $deal->getYousign_signature_request_id();
        if (!$requestId || empty($this->apiKey)) {
            return false;
        }

        try {
            $data   = $this->request('GET', "/signature_requests/{$requestId}");
            $status = $data['status'] ?? 'unknown';

            $deal->setYousign_status($status);
            $this->em->flush();

            return $status === 'done';

        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Télécharge le document signé depuis Yousign et le sauvegarde localement.
     * Retourne le chemin relatif (public/) ou null en cas d'échec.
     * Appelé par DealController::yousignSync().
     */
    public function downloadSignedDocument(Deal $deal): ?string
    {
        $requestId = $deal->getYousign_signature_request_id();
        if (!$requestId || empty($this->apiKey)) {
            return null;
        }

        try {
            // Récupérer la liste des documents
            $docs      = $this->request('GET', "/signature_requests/{$requestId}/documents");
            $documents = $docs['data'] ?? $docs;

            if (empty($documents)) {
                return null;
            }

            $docId = $documents[0]['id'] ?? null;
            if (!$docId) {
                return null;
            }

            // Télécharger le PDF signé
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->baseUrl, '/') . "/signature_requests/{$requestId}/documents/{$docId}/download",
                [
                    'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                    'timeout' => 30,
                ]
            );

            $pdfContent = $response->getContent(false);
            if (empty($pdfContent)) {
                return null;
            }

            $dir = $this->projectDir . '/public/contracts';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = sprintf('contract_deal_%d_signed_%s.pdf', $deal->getDeal_id(), date('Ymd_His'));
            file_put_contents($dir . '/' . $filename, $pdfContent);

            return '/contracts/' . $filename;

        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Traite un webhook Yousign et met à jour le deal.
     * Retourne true si la signature est complète (event: signature_request.done).
     * Appelé par DealController::yousignWebhook().
     */
    public function handleWebhook(array $payload, Deal $deal): bool
    {
        $eventName = $payload['event_name'] ?? $payload['name'] ?? '';

        if ($eventName === 'signature_request.done') {
            $deal->setYousign_status('done');
            $this->em->flush();
            return true;
        }

        if ($eventName === 'signer.done') {
            // Signature partielle — on vérifie si tous ont signé
            $done = $this->syncDealStatus($deal);
            return $done;
        }

        if (in_array($eventName, ['signature_request.declined', 'signature_request.expired'], true)) {
            $deal->setYousign_status(str_replace('signature_request.', '', $eventName));
            $this->em->flush();
        }

        return false;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
        ];

        if (!empty($body)) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . $path, $options);
        return $response->toArray(false) ?: [];
    }

    private function uploadDocument(string $requestId, string $filePath): array
    {
        $response = $this->httpClient->request(
            'POST',
            rtrim($this->baseUrl, '/') . "/signature_requests/{$requestId}/documents",
            [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'body'    => [
                    'file'    => fopen($filePath, 'r'),
                    'nature'  => 'signable_document',
                ],
                'timeout' => 30,
            ]
        );
        return $response->toArray(false) ?: [];
    }
}
