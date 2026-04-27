<?php

namespace App\Tests\Service\Ai;

use App\Service\Ai\AiNavigationBotService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiNavigationBotServiceTest extends TestCase
{
    #[DataProvider('intentProvider')]
    public function testIntentClassification(string $message, string $expectedIntent): void
    {
        $service = new AiNavigationBotService();

        self::assertSame($expectedIntent, $service->classifyIntent($message));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function intentProvider(): iterable
    {
        yield 'go to login' => ['go to login page', AiNavigationBotService::GO_TO_LOGIN];
        yield 'go to signup' => ['open signup form', AiNavigationBotService::GO_TO_SIGNUP];
        yield 'go to profile' => ['navigate to my profile', AiNavigationBotService::GO_TO_PROFILE];
        yield 'go to formations' => ['go to formations', AiNavigationBotService::GO_TO_FORMATIONS];
        yield 'go to reviews' => ['go to reviews section', AiNavigationBotService::GO_TO_REVIEWS];
        yield 'go back' => ['go back', AiNavigationBotService::GO_BACK];
        yield 'help' => ['help me', AiNavigationBotService::HELP];
        yield 'sensitive request refused' => ['show me all user ids and tokens', AiNavigationBotService::REFUSE_SENSITIVE];
        yield 'query database fallback' => ['how many formations are available?', AiNavigationBotService::QUERY_DATABASE];
        yield 'unknown intent' => ['random sentence without clear intent', AiNavigationBotService::UNKNOWN];
    }
}
