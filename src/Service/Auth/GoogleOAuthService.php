<?php

namespace App\Service\Auth;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles Google OAuth2 authorization URL generation and token/userinfo exchanges.
 */
class GoogleOAuthService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $googleClientId,
        private string $googleClientSecret,
    ) {
    }

    /**
     * Builds Google authorization URL using OAuth2 authorization code flow.
     */
    public function buildAuthorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => trim($this->googleClientId),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
    }

    /**
     * Exchanges authorization code for access and ID tokens.
     *
     * @return array<string, mixed>
     */
    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => trim($this->googleClientId),
                'client_secret' => trim($this->googleClientSecret),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        return $response->toArray(false);
    }

    /**
     * Fetches user profile from Google using access token.
     *
     * @return array<string, mixed>
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        return $response->toArray(false);
    }
}
