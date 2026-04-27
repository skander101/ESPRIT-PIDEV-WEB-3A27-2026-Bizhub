<?php

namespace App\Tests\Service\Ai;

use App\Service\Ai\CloudflareAiService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class CloudflareAiServiceTest extends TestCase
{
    #[DataProvider('textPayloadProvider')]
    public function testParseTextPayload(array $payload, string $expected): void
    {
        $service = new CloudflareAiService(new MockHttpClient(), new NullLogger(), 'token', 'acc');

        self::assertSame($expected, $service->parseTextPayload($payload));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function textPayloadProvider(): iterable
    {
        yield 'result response' => [
            ['result' => ['response' => 'Hello from response']],
            'Hello from response',
        ];

        yield 'output text' => [
            ['result' => ['output_text' => 'Hello from output_text']],
            'Hello from output_text',
        ];

        yield 'openai style choices' => [
            ['result' => ['choices' => [['message' => ['content' => 'Hello from choices']]]]],
            'Hello from choices',
        ];

        yield 'nested content list' => [
            ['result' => ['content' => [['type' => 'text', 'text' => 'Hello from nested content']]]],
            'Hello from nested content',
        ];

        yield 'plain result string' => [
            ['result' => 'Hello from scalar result'],
            'Hello from scalar result',
        ];
    }

    public function testParseImagePayloadFromBase64(): void
    {
        $service = new CloudflareAiService(new MockHttpClient(), new NullLogger(), 'token', 'acc');
        $payload = ['result' => ['image' => base64_encode('image-bytes')]];

        $parsed = $service->parseImagePayload($payload);

        self::assertSame('image-bytes', $parsed['bytes']);
        self::assertSame('image/png', $parsed['mimeType']);
    }
}
