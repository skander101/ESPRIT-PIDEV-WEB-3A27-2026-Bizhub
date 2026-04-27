<?php

namespace App\Service\Auth;

/**
 * Generates cryptographically secure tokens and verifies them with hash_equals.
 */
class SecureTokenService
{
    /**
     * Generates a URL-safe token for links or API payloads.
     */
    public function generateToken(int $bytes = 48): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * Constant-time token comparison to avoid timing attacks.
     */
    public function equals(string $knownToken, string $providedToken): bool
    {
        return hash_equals($knownToken, $providedToken);
    }
}
