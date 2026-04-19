<?php

namespace App\Service\Investissement;

use App\Entity\Investissement\Deal;
use App\Entity\UsersAvis\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Integrates the Yousign v3 API (sandbox or production).
 *
 * Full flow:
 *   createSignatureRequest → uploadDocument → addSigner → activate
 *
 * The Deal fields yousign_signature_request_id and yousign_status
 * are persisted after a successful activation.
 */
class YousignService
{
    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
        private string                 $apiKey,
        private string                 $baseUrl,
        private string                 $projectDir,
    ) {}

    /**
     * Complete Yousign workflow for a deal.
     * Creates the signature request, uploads the PDF, adds the buyer as signer,
     * activates it (Yousign sends the email) and persists the request ID in the Deal.
     *
     * @throws \RuntimeException on any API error
     */
    public function sendSignatureRequest(Deal $deal, User $buyer): string
    {
        // 1. Create the signature request
        $requestName = sprintf('Contrat BizHub — Deal #%d', $deal->getDeal_id());
        $sr = $this->apiPost('/signature_requests', [
            'name'          => $requestName,
            'delivery_mode' => 'email',
            'timezone'      => 'Africa/Tunis',
        ]);
        $signatureRequestId = $sr['id']
            ?? throw new \RuntimeException('Yousign: missing id in createSignatureRequest response.');

        // 2. Upload the PDF contract
        $pdfAbsPath = $this->projectDir . '/public' . $deal->getContract_pdf_path();
        if (!file_exists($pdfAbsPath)) {
            throw new \RuntimeException('Contrat PDF introuvable : ' . $pdfAbsPath);
        }
        $doc = $this->apiUploadPdf(
            '/signature_requests/' . $signatureRequestId . '/documents',
            $pdfAbsPath,
            sprintf('contrat-deal-%d.pdf', $deal->getDeal_id())
        );
        $documentId = $doc['id']
            ?? throw new \RuntimeException('Yousign: missing id in uploadDocument response.');

        // 3. Add the buyer as signer with a signature field on page 1
        [$firstName, $lastName] = $this->splitFullName($buyer->getFullName() ?? 'Investisseur');
        $this->apiPost('/signature_requests/' . $signatureRequestId . '/signers', [
            'info' => [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $buyer->getEmail(),
                'locale'     => 'fr',
            ],
            'signature_level'               => 'electronic_signature',
            'signature_authentication_mode' => 'no_otp',
            'fields'                        => [[
                'document_id' => $documentId,
                'type'        => 'signature',
                'page'        => 1,
                'x'           => 77,
                'y'           => 600,
                'height'      => 60,
                'width'       => 150,
            ]],
        ]);

        // 4. Activate — Yousign dispatches the email to the signer
        $this->apiPost('/signature_requests/' . $signatureRequestId . '/activate', []);

        // 5. Persist Yousign IDs in the Deal
        $deal->setYousign_signature_request_id($signatureRequestId);
        $deal->setYousign_status('ongoing');
        $this->em->flush();

        return $signatureRequestId;
    }

    /**
     * Fetch the current status of a signature request from Yousign.
     */
    public function getStatus(string $signatureRequestId): array
    {
        $response = $this->httpClient->request(
            'GET',
            $this->baseUrl . '/signature_requests/' . $signatureRequestId,
            ['headers' => $this->jsonHeaders()]
        );
        return $response->toArray();
    }

    /**
     * Poll Yousign API and synchronise the deal status.
     * If the signature is done, the deal is marked as SIGNED.
     * Returns true when the signature is confirmed done.
     */
    public function syncDealStatus(Deal $deal): bool
    {
        $requestId = $deal->getYousign_signature_request_id();
        if (!$requestId) {
            return false;
        }

        $data   = $this->getStatus($requestId);
        $status = $data['status'] ?? null;

        $deal->setYousign_status($status);

        if ($status === 'done' && $deal->getStatus() === Deal::STATUS_PENDING_SIGNATURE) {
            $deal->setStatus(Deal::STATUS_SIGNED);
            $deal->setCompleted_at(new \DateTime());
            $deal->setSignature_token(null);
            $deal->setSignature_token_expires_at(null);
            $this->em->flush();
            return true;
        }

        $this->em->flush();
        return $status === 'done';
    }

    /**
     * Download the signed PDF from Yousign and store it locally.
     * Returns the public path (e.g. /contracts/signed/deal-1-signed.pdf) or null on failure.
     */
    public function downloadSignedDocument(Deal $deal): ?string
    {
        $requestId = $deal->getYousign_signature_request_id();
        if (!$requestId) {
            return null;
        }

        // Fetch signature request details to find the signable document ID
        $data      = $this->getStatus($requestId);
        $documents = $data['documents'] ?? [];
        $docId     = null;
        foreach ($documents as $doc) {
            if (($doc['nature'] ?? '') === 'signable_document') {
                $docId = $doc['id'];
                break;
            }
        }

        if (!$docId) {
            return null;
        }

        // Download the signed binary PDF
        $response = $this->httpClient->request(
            'GET',
            $this->baseUrl . '/signature_requests/' . $requestId . '/documents/' . $docId . '/download',
            ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey]]
        );

        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $dir = $this->projectDir . '/public/contracts/signed/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('deal-%d-signed.pdf', $deal->getDeal_id());
        file_put_contents($dir . $filename, $response->getContent());

        return '/contracts/signed/' . $filename;
    }

    /**
     * Process a Yousign webhook payload and update the deal accordingly.
     * Called from the webhook controller endpoint.
     * Returns true if the deal was transitioned to SIGNED.
     */
    public function handleWebhook(array $payload, Deal $deal): bool
    {
        $event  = $payload['event_name'] ?? $payload['type'] ?? '';
        $status = $payload['data']['signature_request']['status']
               ?? $payload['status']
               ?? null;

        $deal->setYousign_status($status ?? $event);

        $signed = false;
        // All signers done → mark deal as signed
        if ($status === 'done' || in_array($event, ['signature_request.done', 'signer.done'], true)) {
            if ($deal->getStatus() === Deal::STATUS_PENDING_SIGNATURE) {
                $deal->setStatus(Deal::STATUS_SIGNED);
                $deal->setCompleted_at(new \DateTime());
                $deal->setSignature_token(null);
                $deal->setSignature_token_expires_at(null);
                $signed = true;
            }
        }

        $this->em->flush();
        return $signed;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** POST JSON and return decoded array; throws on 4xx/5xx. */
    private function apiPost(string $path, array $body): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
            'headers' => $this->jsonHeaders(),
            'json'    => $body,
        ]);

        $code = $response->getStatusCode();
        if ($code >= 400) {
            $detail = '';
            try { $detail = json_encode($response->toArray(false)); } catch (\Throwable) {}
            throw new \RuntimeException(sprintf(
                'Yousign API error %d on POST %s: %s', $code, $path, $detail
            ));
        }

        return $response->toArray();
    }

    /** Upload a PDF via multipart/form-data; throws on 4xx/5xx. */
    private function apiUploadPdf(string $path, string $absolutePath, string $filename): array
    {
        $boundary = '----BizHubBoundary' . bin2hex(random_bytes(8));
        $fileData = file_get_contents($absolutePath);

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/pdf\r\n\r\n";
        $body .= $fileData . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"nature\"\r\n\r\n";
        $body .= "signable_document\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
        ]);

        $code = $response->getStatusCode();
        if ($code >= 400) {
            $detail = '';
            try { $detail = json_encode($response->toArray(false)); } catch (\Throwable) {}
            throw new \RuntimeException(sprintf(
                'Yousign upload error %d: %s', $code, $detail
            ));
        }

        return $response->toArray();
    }

    private function jsonHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    /** Split "First Last" or "First Middle Last" into [firstName, lastName]. */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [
            $parts[0] ?? 'Prénom',
            $parts[1] ?? 'Nom',
        ];
    }
}
