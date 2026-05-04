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
        $systemPrompt = 'You are the Queen Bee of the startup ecosystem — an expert advisor on startups, fundraising, venture capital, pitch decks, equity, and term sheets. Your hive runs on sharp insight and zero fluff. You only answer questions about startups, entrepreneurship, fundraising, and the investment world. If a question falls outside this domain, politely decline and remind the user that the hive stays focused. Be concise, direct, and practical — every word should add honey, not wax.';
        $historyMessages = $this->history->getHistory('general', $sessionId);

        $messages = [...$historyMessages, ['role' => 'user', 'content' => trim($message)]];

        $text = $this->cloudflareClient->complete(self::MODEL, $systemPrompt, $messages, self::MAX_TOKENS);

        $this->history->appendAndSave('general', $sessionId, 'user', trim($message), self::HISTORY_WINDOW * 2);
        $this->history->appendAndSave('general', $sessionId, 'assistant', $text, self::HISTORY_WINDOW * 2);

        return new AgentResponse($text, 'general');
    }
}
