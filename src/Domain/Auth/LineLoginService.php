<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LineLoginService
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly string $channelId,
        private readonly string $channelSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->channelId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => 'openid profile email',
        ];

        return 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $request = $this->requestFactory->createRequest('POST', 'https://api.line.me/oauth2/v2.1/token');
        $request = $request->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded');
        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ]);
        $request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ])));

        $response = $this->httpClient->sendRequest($request);
        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['access_token'], $data['id_token'])) {
            throw new \RuntimeException('Failed to exchange code for tokens');
        }

        return $data;
    }

    public function getUserProfile(string $accessToken): array
    {
        $request = (new \GuzzleHttp\Psr7\Request('GET', 'https://api.line.me/v2/profile'))
            ->withAddedHeader('Authorization', 'Bearer ' . $accessToken);

        $response = $this->httpClient->sendRequest(
            (new \GuzzleHttp\Psr7\Request('GET', 'https://api.line.me/v2/profile'))
                ->withAddedHeader('Authorization', 'Bearer ' . $accessToken)
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    public function verifyIdToken(string $idToken): array
    {
        // TODO: Implement proper ID token verification with LINE's JWKS
        // For now, decode without verification (for development only)
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid ID token format');
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return $payload;
    }
}