<?php

namespace App\Service\Chatbot;

class AgentResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $intent,
        public readonly array $navLinks = [],
    ) {}
}
