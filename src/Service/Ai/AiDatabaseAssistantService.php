<?php

namespace App\Service\Ai;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class AiDatabaseAssistantService
{
    private const FALLBACK_MESSAGE = "Bzz... I couldn't find that in the hive! 🐝";

    public function __construct(
        private readonly Connection $connection,
        private readonly CloudflareAiService $cloudflareAiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, array{role: string, content: string}> $recentHistory
     */
    public function answer(string $userInput, array $recentHistory): string
    {
        $context = $this->buildDatabaseContext();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        $turns = array_slice($recentHistory, -20);
        foreach ($turns as $turn) {
            if (!isset($turn['role'], $turn['content'])) {
                continue;
            }

            if (!in_array($turn['role'], ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) $turn['content']);
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => $turn['role'],
                'content' => $content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => trim($userInput),
        ];

        try {
            $response = trim($this->cloudflareAiService->generateText(
                '@cf/openai/gpt-oss-120b',
                $messages,
                350
            ));
        } catch (\Throwable $e) {
            $this->logger->error('AI database assistant failed.', [
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return self::FALLBACK_MESSAGE;
        }

        if ($response === '') {
            return self::FALLBACK_MESSAGE;
        }

        return $response;
    }

    private function buildSystemPrompt(string $context): string
    {
        return <<<PROMPT
You are BizHub's database assistant.
Rules:
- Answer only from the provided DB context.
- Never invent data.
- Never reveal IDs.
- Keep answers concise and direct.
- For count or popularity questions, use Summary stats and Insights when available.
- If information is missing, reply exactly with: Bzz... I couldn't find that in the hive! 🐝

DB CONTEXT:
{$context}
PROMPT;
    }

    private function buildDatabaseContext(): string
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tableNames = $schemaManager->listTableNames();
        $tableNames = $this->prioritizeTables($tableNames, ['user', 'formation', 'participation', 'avis', 'training_request']);
        $tableNames = array_slice($tableNames, 0, 15);

        if ($tableNames === []) {
            return 'No tables available.';
        }

        $schemaLines = [];
        $statsLines = [];
        $samples = [];

        foreach ($tableNames as $tableName) {
            try {
                $columns = $schemaManager->listTableColumns($tableName);
            } catch (\Throwable) {
                continue;
            }

            $columnNames = array_map(static fn($column) => $column->getName(), $columns);
            $safeColumns = array_values(array_filter($columnNames, fn(string $column): bool => !$this->isSensitiveColumn($column)));

            $schemaLines[] = sprintf('%s(%s)', $tableName, implode(', ', array_slice($safeColumns, 0, 8)));

            try {
                $statsLines[] = sprintf('%s: %d rows', $tableName, (int) $this->connection->fetchOne(
                    sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteIdentifier($tableName))
                ));
            } catch (\Throwable) {
                $statsLines[] = sprintf('%s: unavailable', $tableName);
            }

            if ($safeColumns === []) {
                continue;
            }

            $selectedColumns = array_slice($safeColumns, 0, 4);
            $quotedColumns = array_map(fn(string $column): string => $this->connection->quoteIdentifier($column), $selectedColumns);

            try {
                $rows = $this->connection->fetchAllAssociative(sprintf(
                    'SELECT %s FROM %s LIMIT 3',
                    implode(', ', $quotedColumns),
                    $this->connection->quoteIdentifier($tableName)
                ));

                if ($rows !== []) {
                    $samples[$tableName] = $this->sanitizeRows($rows);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $insightLines = $this->buildDerivedInsights($tableNames);

        return sprintf(
            "Schema:\n%s\n\nSummary stats:\n%s\n\nInsights:\n%s\n\nSample rows:\n%s",
            implode("\n", $schemaLines),
            implode("\n", $statsLines),
            $insightLines === [] ? 'No derived insights available.' : implode("\n", $insightLines),
            json_encode($samples, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param array<int, string> $tableNames
     *
     * @return array<int, string>
     */
    private function buildDerivedInsights(array $tableNames): array
    {
        $insights = [];
        $tableNameMap = [];

        foreach ($tableNames as $tableName) {
            $tableNameMap[mb_strtolower($tableName)] = $tableName;
        }

        if (isset($tableNameMap['user'])) {
            try {
                $userCount = (int) $this->connection->fetchOne(
                    sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteIdentifier($tableNameMap['user']))
                );
                $insights[] = sprintf('Users in app: %d', $userCount);
            } catch (\Throwable) {
                $insights[] = 'Users in app: unavailable';
            }
        }

        if (isset($tableNameMap['formation'], $tableNameMap['participation'])) {
            try {
                $popularFormation = $this->connection->fetchAssociative(sprintf(
                    'SELECT f.%1$s AS formation_title, COUNT(*) AS participant_count
                     FROM %2$s p
                     INNER JOIN %3$s f ON p.%4$s = f.%5$s
                     GROUP BY f.%5$s, f.%1$s
                     ORDER BY participant_count DESC, formation_title ASC
                     LIMIT 1',
                    $this->connection->quoteIdentifier('title'),
                    $this->connection->quoteIdentifier($tableNameMap['participation']),
                    $this->connection->quoteIdentifier($tableNameMap['formation']),
                    $this->connection->quoteIdentifier('formation_id'),
                    $this->connection->quoteIdentifier('formation_id')
                ));

                if (is_array($popularFormation) && isset($popularFormation['formation_title'])) {
                    $title = trim((string) $popularFormation['formation_title']);
                    $count = isset($popularFormation['participant_count']) ? (int) $popularFormation['participant_count'] : 0;

                    if ($title !== '') {
                        $insights[] = sprintf(
                            'Most popular formation: "%s" (%d %s)',
                            mb_substr($title, 0, 120),
                            $count,
                            $count === 1 ? 'participation' : 'participations'
                        );
                    }
                } else {
                    $insights[] = 'Most popular formation: unavailable';
                }
            } catch (\Throwable) {
                $insights[] = 'Most popular formation: unavailable';
            }
        }

        return $insights;
    }

    /**
     * @param array<int, string> $tableNames
     * @param array<int, string> $priorityTables
     *
     * @return array<int, string>
     */
    private function prioritizeTables(array $tableNames, array $priorityTables): array
    {
        $prioritized = [];
        $remaining = [];
        $priorityMap = array_fill_keys(array_map('mb_strtolower', $priorityTables), true);

        foreach ($tableNames as $tableName) {
            if (isset($priorityMap[mb_strtolower($tableName)])) {
                $prioritized[] = $tableName;
                continue;
            }

            $remaining[] = $tableName;
        }

        return array_merge($prioritized, $remaining);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeRows(array $rows): array
    {
        $sanitized = [];

        foreach ($rows as $row) {
            $cleanRow = [];

            foreach ($row as $column => $value) {
                if ($this->isSensitiveColumn((string) $column)) {
                    continue;
                }

                if (is_string($value)) {
                    $cleanRow[$column] = mb_substr($value, 0, 120);
                    continue;
                }

                $cleanRow[$column] = $value;
            }

            if ($cleanRow !== []) {
                $sanitized[] = $cleanRow;
            }
        }

        return $sanitized;
    }

    private function isSensitiveColumn(string $column): bool
    {
        return preg_match('/(^id$|_id$|password|token|secret|key|hash|salt)/i', $column) === 1;
    }
}
