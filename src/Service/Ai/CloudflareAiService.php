<?php

namespace App\Service\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CloudflareAiService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $cloudflareApiToken,
        private readonly string $cloudflareAccountId,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->cloudflareApiToken) !== '' && trim($this->cloudflareAccountId) !== '';
    }

    /**
     * @return array{bytes: string, mimeType: string}
     */
    public function generateImage(string $model, string $prompt): array
    {
        $this->assertConfigured();

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl($model), [
                'headers' => $this->buildHeaders(),
                'json' => [
                    'prompt' => $prompt,
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Cloudflare image request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($statusCode >= 400) {
            $this->logger->warning('Cloudflare image generation returned error status.', [
                'status' => $statusCode,
                'body' => $rawBody,
            ]);

            throw new \RuntimeException('Cloudflare image generation failed with status '.$statusCode.'.');
        }

        $headers = $response->getHeaders(false);
        $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));

        if (str_starts_with($contentType, 'image/')) {
            $mimeType = trim(explode(';', $contentType)[0]);

            if ($rawBody === '') {
                throw new \RuntimeException('Cloudflare returned an empty image response.');
            }

            return [
                'bytes' => $rawBody,
                'mimeType' => $mimeType,
            ];
        }

        if ($rawBody === '') {
            throw new \RuntimeException('Cloudflare returned an empty image payload.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Cloudflare image response is not valid JSON or image bytes.');
        }

        return $this->parseImagePayload($payload);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function generateText(string $model, array $messages, int $maxTokens = 300): string
    {
        $this->assertConfigured();

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl($model), [
                'headers' => $this->buildHeaders(),
                'json' => [
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Cloudflare text request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($statusCode >= 400) {
            $this->logger->warning('Cloudflare text generation returned error status.', [
                'status' => $statusCode,
                'body' => $rawBody,
            ]);

            throw new \RuntimeException('Cloudflare text generation failed with status '.$statusCode.'.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Cloudflare text response is not valid JSON.');
        }

        return $this->parseTextPayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{bytes: string, mimeType: string}
     */
    public function parseImagePayload(array $payload): array
    {
        $candidates = [
            $payload['result']['image'] ?? null,
            $payload['result']['b64_json'] ?? null,
            $payload['result'][0]['image'] ?? null,
            $payload['result'][0]['b64_json'] ?? null,
            $payload['image'] ?? null,
            $payload['b64_json'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $decoded = base64_decode($candidate, true);
            if ($decoded === false || $decoded === '') {
                continue;
            }

            return [
                'bytes' => $decoded,
                'mimeType' => 'image/png',
            ];
        }

        throw new \RuntimeException('Cloudflare image response did not contain usable image data.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parseTextPayload(array $payload): string
    {
        $directCandidates = [
            $payload['result']['response'] ?? null,
            $payload['result']['output_text'] ?? null,
            $payload['result']['text'] ?? null,
            $payload['result']['answer'] ?? null,
            $payload['response'] ?? null,
            $payload['result'] ?? null,
        ];

        foreach ($directCandidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $choiceContent = $payload['result']['choices'][0]['message']['content'] ?? null;
        if (is_string($choiceContent) && trim($choiceContent) !== '') {
            return trim($choiceContent);
        }

        if (is_array($choiceContent)) {
            $flattened = $this->flattenTextFromArray($choiceContent);
            if ($flattened !== '') {
                return $flattened;
            }
        }

        $nestedResult = $payload['result']['output'] ?? $payload['result']['content'] ?? null;
        if (is_array($nestedResult)) {
            $flattened = $this->flattenTextFromArray($nestedResult);
            if ($flattened !== '') {
                return $flattened;
            }
        }

        $flattenedPayload = $this->flattenTextFromArray($payload);
        if ($flattenedPayload !== '') {
            return $flattenedPayload;
        }

        throw new \RuntimeException('Cloudflare text response did not contain a readable answer.');
    }

    /**
     * @param array<mixed> $data
     */
    private function flattenTextFromArray(array $data): string
    {
        $chunks = [];

        array_walk_recursive($data, static function (mixed $value, string|int $key) use (&$chunks): void {
            if (!is_string($value) || trim($value) === '') {
                return;
            }

            if (is_string($key) && !in_array($key, ['text', 'content', 'response', 'output_text', 'answer'], true)) {
                return;
            }

            $chunks[] = trim($value);
        });

        return trim(implode(' ', $chunks));
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Cloudflare AI is not configured. Please set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACC_ID.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.trim($this->cloudflareApiToken),
            'Content-Type' => 'application/json',
        ];
    }

    private function buildUrl(string $model): string
    {
        return sprintf(self::BASE_URL, rawurlencode(trim($this->cloudflareAccountId)), ltrim($model, '/'));
    }
}
