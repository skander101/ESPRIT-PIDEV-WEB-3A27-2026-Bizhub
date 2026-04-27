<?php

namespace App\Service\Auth;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleOAuthService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private RouterInterface $router,
        private string $googleClientId,
        private string $googleClientSecret,
    ) {
    }

    public function buildAuthorizationUrl(string $state): string
    {
        $redirectUri = $this->router->generate('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);

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

    public function exchangeCodeForTokens(string $code): array
    {
        $redirectUri = $this->router->generate('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => trim($this->googleClientId),
                'client_secret' => trim($this->googleClientSecret),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            $error = $data['error'];
            $errorDescription = $data['error_description'] ?? 'No description provided';
            throw new \RuntimeException(sprintf(
                'Google OAuth error: %s - %s',
                $error,
                $errorDescription
            ));
        }

        return $data;
    }

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