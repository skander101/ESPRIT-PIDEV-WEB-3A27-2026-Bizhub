<?php

namespace App\Service\Chatbot;

use Symfony\Contracts\Cache\CacheInterface;

class ConversationHistory
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function getHistory(string $agentName, string $sessionId): array
    {
        $key = sprintf('chatbot_%s_%s', $agentName, $sessionId);
        return $this->cache->get($key, fn() => []);
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function appendAndSave(string $agentName, string $sessionId, string $role, string $content, int $maxTurns): void
    {
        $key = sprintf('chatbot_%s_%s', $agentName, $sessionId);
        $history = $this->getHistory($agentName, $sessionId);
        $history[] = ['role' => $role, 'content' => $content];

        if (count($history) > $maxTurns) {
            $history = array_slice($history, -$maxTurns);
        }

        $this->cache->delete($key);
        $this->cache->get($key, function () use ($history) {
            return $history;
        });
    }
}
