<?php

namespace App\Tests\Service\Ai;

use App\Service\Ai\AiDatabaseAssistantService;
use App\Service\Ai\CloudflareAiService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AiDatabaseAssistantServiceTest extends TestCase
{
    public function testDatabaseAssistantBuildsContextAndReturnsCloudflareAnswer(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableNames')
            ->willReturn(['formation']);
        $schemaManager
            ->method('listTableColumns')
            ->with('formation')
            ->willReturn([
                'formation_id' => new Column('formation_id', Type::getType(Types::INTEGER)),
                'title' => new Column('title', Type::getType(Types::STRING)),
                'category' => new Column('category', Type::getType(Types::STRING)),
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn(string $identifier): string => '`'.$identifier.'`');
        $connection->method('fetchOne')->willReturn(2);
        $connection->method('fetchAllAssociative')->willReturn([
            ['title' => 'Symfony Basics', 'category' => 'Tech'],
        ]);

        $cloudflare = $this->createMock(CloudflareAiService::class);
        $cloudflare
            ->expects(self::once())
            ->method('generateText')
            ->with(
                '@cf/openai/gpt-oss-120b',
                self::callback(static function (array $messages): bool {
                    if (!isset($messages[0]['content'])) {
                        return false;
                    }

                    return str_contains((string) $messages[0]['content'], 'formation(title, category)')
                        && str_contains((string) $messages[0]['content'], '2 rows');
                }),
                350
            )
            ->willReturn('There are 2 formations available.');

        $service = new AiDatabaseAssistantService($connection, $cloudflare, new NullLogger());

        $answer = $service->answer('How many formations are available?', [
            ['role' => 'user', 'content' => 'hello'],
            ['role' => 'assistant', 'content' => 'hi'],
        ]);

        self::assertSame('There are 2 formations available.', $answer);
    }

    public function testDatabaseAssistantReturnsFallbackWhenCloudflareFails(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableNames')
            ->willReturn(['avis']);
        $schemaManager
            ->method('listTableColumns')
            ->with('avis')
            ->willReturn([
                'avis_id' => new Column('avis_id', Type::getType(Types::INTEGER)),
                'comment' => new Column('comment', Type::getType(Types::STRING)),
            ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn(string $identifier): string => '`'.$identifier.'`');
        $connection->method('fetchOne')->willReturn(5);
        $connection->method('fetchAllAssociative')->willReturn([
            ['comment' => 'Great formation'],
        ]);

        $cloudflare = $this->createMock(CloudflareAiService::class);
        $cloudflare
            ->method('generateText')
            ->willThrowException(new \RuntimeException('Cloudflare failure'));

        $service = new AiDatabaseAssistantService($connection, $cloudflare, new NullLogger());

        $answer = $service->answer('Give me review stats', []);

        self::assertSame("Bzz... I couldn't find that in the hive! 🐝", $answer);
    }

    public function testDatabaseAssistantIncludesInsightsForCountsAndPopularityQuestions(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableNames')
            ->willReturn(['ai_analysis', 'formation', 'participation', 'user']);
        $schemaManager
            ->method('listTableColumns')
            ->willReturnCallback(static function (string $tableName): array {
                return match ($tableName) {
                    'formation' => [
                        'formation_id' => new Column('formation_id', Type::getType(Types::INTEGER)),
                        'title' => new Column('title', Type::getType(Types::STRING)),
                    ],
                    'participation' => [
                        'formation_id' => new Column('formation_id', Type::getType(Types::INTEGER)),
                        'created_at' => new Column('created_at', Type::getType(Types::DATETIME_MUTABLE)),
                    ],
                    'user' => [
                        'user_id' => new Column('user_id', Type::getType(Types::INTEGER)),
                        'email' => new Column('email', Type::getType(Types::STRING)),
                    ],
                    default => [
                        'project_id' => new Column('project_id', Type::getType(Types::INTEGER)),
                        'analysis_type' => new Column('analysis_type', Type::getType(Types::STRING)),
                    ],
                };
            });

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn(string $identifier): string => '`'.$identifier.'`');
        $connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int {
                if (str_contains($sql, 'FROM `user`')) {
                    return 61;
                }

                return 2;
            });
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'formation_title' => 'Symfony Basics',
                'participant_count' => 12,
            ]);

        $cloudflare = $this->createMock(CloudflareAiService::class);
        $cloudflare
            ->expects(self::once())
            ->method('generateText')
            ->with(
                '@cf/openai/gpt-oss-120b',
                self::callback(static function (array $messages): bool {
                    if (!isset($messages[0]['content'])) {
                        return false;
                    }

                    $systemPrompt = (string) $messages[0]['content'];

                    return str_contains($systemPrompt, 'Users in app: 61')
                        && str_contains($systemPrompt, 'Most popular formation: "Symfony Basics" (12 participations)');
                }),
                350
            )
            ->willReturn('There are 61 users in the app, and Symfony Basics is the most popular formation.');

        $service = new AiDatabaseAssistantService($connection, $cloudflare, new NullLogger());

        $answer = $service->answer('which formation is the most popular?', []);

        self::assertSame('There are 61 users in the app, and Symfony Basics is the most popular formation.', $answer);
    }
}
