<?php

namespace App\Service\Chatbot;

class RouterAgent
{
private const MODEL = '@cf/meta/llama-3.1-8b-instruct';
    private const MAX_TOKENS = 15;

    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
    ) {}

    public function classify(string $message): string
    {
        try {
            file_put_contents(dirname(__DIR__, 3) . '/var/log/router_debug.log', date('H:i:s') . ' | called with: ' . $message . "\n", FILE_APPEND);
            $systemPrompt = 'Classify the user message into exactly one word: db, nav, or general.

db = questions about data, counts, statistics, lists, records, popularity, most/least, rankings
nav = requests to go somewhere, open a page, navigate, find a form, take me to
general = advice, definitions, how-to questions about startups/investing

Examples:
"go to marketplace" -> nav
"take me to the product form" -> nav
"navigate to formations" -> nav
"open the dashboard" -> nav
"how many users" -> db
"which formation is most popular" -> db
"show me active projects" -> db
"list all investors" -> db
"how much funding was raised" -> db
"what is a term sheet" -> general
"how do i write a pitch deck" -> general
"what is venture capital" -> general

Reply with ONLY one word. No punctuation. No explanation.';

        $response = $this->cloudflareClient->complete(
            self::MODEL,
            $systemPrompt,
            [['role' => 'user', 'content' => trim($message)]],
            self::MAX_TOKENS
        );

        // Extract first word only — model may return more than one word
        $raw = strtolower(trim($response));
        $raw = trim($raw, '.!?\'" ');
        $firstWord = strtok($raw, " \n\r\t");

        if (in_array($firstWord, ['db', 'nav', 'general'], true)) {
            return $firstWord;
        }

        // Fallback: scan full response for any valid intent word
        foreach (['nav', 'db', 'general'] as $intent) {
            if (str_contains($raw, $intent)) {
                return $intent;
            }
        }

        return 'general';
        } catch (\Throwable $e) {
            file_put_contents(dirname(__DIR__, 3) . '/var/log/router_debug.log', date('H:i:s') . ' | EXCEPTION: ' . $e->getMessage() . "\n", FILE_APPEND);
            return 'general';
        }
    }
}
