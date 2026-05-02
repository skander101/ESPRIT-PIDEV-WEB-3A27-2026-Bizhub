<?php

namespace App\Service\Chatbot;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CloudflareClient
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $cloudflareApiToken,
        private readonly string $cloudflareAccId,
    ) {}

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function complete(string $model, string $systemPrompt, array $messages, int $maxTokens): string
    {
        $url = sprintf(self::BASE_URL, rawurlencode(trim($this->cloudflareAccId)), ltrim($model, '/'));

        $body = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$messages,
            ],
            'max_tokens' => $maxTokens,
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . trim($this->cloudflareApiToken),
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($statusCode >= 400) {
            $error = $this->extractErrorMessage($rawBody);
            throw new \RuntimeException($error ?: 'Cloudflare AI request failed with status ' . $statusCode);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Cloudflare AI response is not valid JSON.');
        }

        return $this->extractText($payload);
    }

    private function extractErrorMessage(string $rawBody): string
    {
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return '';
        }

        if (isset($data['errors'][0]['message']) && is_string($data['errors'][0]['message'])) {
            return $data['errors'][0]['message'];
        }

        if (isset($data['errors'][0]) && is_string($data['errors'][0])) {
            return $data['errors'][0];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractText(array $payload): string
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

        $nestedResult = $payload['result']['output'] ?? $payload['result']['content'] ?? null;
        if (is_string($nestedResult) && trim($nestedResult) !== '') {
            return trim($nestedResult);
        }

        $flattened = $this->flattenText($payload);
        if ($flattened !== '') {
            return $flattened;
        }

        throw new \RuntimeException('Cloudflare AI response did not contain a readable answer.');
    }

    /**
     * @param array<mixed> $data
     */
    private function flattenText(array $data): string
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
}
