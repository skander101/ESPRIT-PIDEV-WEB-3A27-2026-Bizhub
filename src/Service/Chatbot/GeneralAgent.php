<?php

namespace App\Service\Chatbot;

class GeneralAgent
{
    private const MODEL = '@cf/meta/llama-3.1-8b-instruct';
    private const MAX_TOKENS = 1200;
    private const HISTORY_WINDOW = 8;

    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
        private readonly ConversationHistory $history,
    ) {}

    public function reply(string $message, string $sessionId): AgentResponse
    {
        $systemPrompt = 'You are an expert advisor on startups, fundraising, venture capital, pitch decks, equity, term sheets, and the startup ecosystem. Give concise, practical advice with no fluff. You are helpful, direct, and knowledgeable about the startup world.';

        $historyMessages = $this->history->getHistory('general', $sessionId);

        $messages = [...$historyMessages, ['role' => 'user', 'content' => trim($message)]];

        $text = $this->cloudflareClient->complete(self::MODEL, $systemPrompt, $messages, self::MAX_TOKENS);

        $this->history->appendAndSave('general', $sessionId, 'user', trim($message), self::HISTORY_WINDOW * 2);
        $this->history->appendAndSave('general', $sessionId, 'assistant', $text, self::HISTORY_WINDOW * 2);

        return new AgentResponse($text, 'general');
    }
}
