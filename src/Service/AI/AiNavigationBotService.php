<?php

namespace App\Service\AI;

class AiNavigationBotService
{
    public const GO_TO_LOGIN = 'GO_TO_LOGIN';
    public const GO_TO_SIGNUP = 'GO_TO_SIGNUP';
    public const GO_TO_PROFILE = 'GO_TO_PROFILE';
    public const GO_TO_USER_MANAGEMENT = 'GO_TO_USER_MANAGEMENT';
    public const GO_TO_FORMATIONS = 'GO_TO_FORMATIONS';
    public const GO_TO_REVIEWS = 'GO_TO_REVIEWS';
    public const GO_BACK = 'GO_BACK';
    public const HELP = 'HELP';
    public const QUERY_DATABASE = 'QUERY_DATABASE';
    public const UNKNOWN = 'UNKNOWN';
    public const REFUSE_SENSITIVE = 'REFUSE_SENSITIVE';

    public function classifyIntent(string $message): string
    {
        $input = mb_strtolower(trim($message));

        if ($input === '') {
            return self::UNKNOWN;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(login|sign in|connexion)\\b/')) {
            return self::GO_TO_LOGIN;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(sign ?up|register|inscription|create account)\\b/')) {
            return self::GO_TO_SIGNUP;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(profile|account)\\b/')) {
            return self::GO_TO_PROFILE;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(user management|manage users|admin users|users admin)\\b/')) {
            return self::GO_TO_USER_MANAGEMENT;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(formations|formation|training|courses)\\b/')) {
            return self::GO_TO_FORMATIONS;
        }

        if ($this->isNavigationIntent($input, '/\\b(go to|open|navigate to|take me to)\\b.*\\b(reviews|review|avis|ratings)\\b/')) {
            return self::GO_TO_REVIEWS;
        }

        if (preg_match('/\\b(go back|back|previous page|return)\\b/', $input) === 1) {
            return self::GO_BACK;
        }

        if (preg_match('/\\b(help|commands|what can you do|aide)\\b/', $input) === 1) {
            return self::HELP;
        }

        if ($this->isSensitiveDataQuestion($input)) {
            return self::REFUSE_SENSITIVE;
        }

        if (preg_match('/\\b(what|which|how many|count|show|list|find|search|where|who|tell me|give me|combien|trouve|liste|montre)\\b/', $input) === 1) {
            return self::QUERY_DATABASE;
        }

        return self::UNKNOWN;
    }

    public function sensitiveDataRefusalMessage(): string
    {
        return 'I cannot share sensitive data such as IDs, passwords, tokens, secrets, keys, or hashes.';
    }

    public function helpMessage(): string
    {
        return implode("\n", [
            'Available commands:',
            '- go to login',
            '- go to signup',
            '- go to profile',
            '- go to user management',
            '- go to formations',
            '- go to reviews',
            '- go back',
            '- ask a database question (e.g. "how many formations are available?")',
        ]);
    }

    private function isSensitiveDataQuestion(string $input): bool
    {
        $containsSensitiveTerm = preg_match('/\\b(ids?|passwords?|tokens?|secrets?|keys?|hash(es)?)\\b/', $input) === 1;
        $asksForDisclosure = preg_match('/\\b(show|list|give|reveal|display|print|provide|what are|get|dump|expose)\\b/', $input) === 1;

        return $containsSensitiveTerm && ($asksForDisclosure || str_contains($input, '?'));
    }

    private function isNavigationIntent(string $input, string $pattern): bool
    {
        return preg_match($pattern, $input) === 1;
    }
}
