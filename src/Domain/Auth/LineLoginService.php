<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Infrastructure\Http\HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * LINE Login — ระบบรองรับ LINE เท่านั้น (CTO Constraint #1)
 *
 * Security (CTO Review 1.2):
 *  - id_token ต้องผ่าน verification จริง — ใช้ LINE verify endpoint
 *    (POST https://api.line.me/oauth2/v2.1/verify) ซึ่ง LINE เป็นผู้ตรวจ signature
 *    แล้ว PMOIS ตรวจซ้ำ: issuer, audience (channel id), expiration, issued-at, nonce
 *  - ห้าม decode โดยไม่ verify (ลบ implementation แบบ development-only ออกแล้ว)
 */
final class LineLoginService
{
    private const AUTH_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_URL = 'https://api.line.me/oauth2/v2.1/token';
    private const VERIFY_URL = 'https://api.line.me/oauth2/v2.1/verify';
    private const EXPECTED_ISSUER = 'https://access.line.me';
    /** clock skew สำหรับ iat (วินาที) */
    private const IAT_SKEW = 300;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $channelId,
        private readonly string $channelSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function getAuthorizationUrl(string $state, string $nonce): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->channelId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => 'openid profile email',
            'nonce' => $nonce,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * @return array<string, mixed> token response (access_token, id_token, ...)
     * @throws \RuntimeException OAUTH_EXCHANGE_FAILED
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $data = $this->postForm(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->channelId,
            'client_secret' => $this->channelSecret,
        ]);

        if (!isset($data['access_token'], $data['id_token'])) {
            throw new AuthException('OAUTH_EXCHANGE_FAILED');
        }

        return $data;
    }

    /**
     * Verify id_token ผ่าน LINE verify endpoint (LINE ตรวจ signature ด้วย key ของ LINE เอง)
     * แล้ว PMOIS ตรวจซ้ำ issuer / audience / exp / iat / nonce
     *
     * @return array<string, mixed> verified claims
     * @throws AuthException ID_TOKEN_INVALID (ข้อความระบุเหตุผล)
     */
    public function verifyIdToken(string $idToken, string $expectedNonce): array
    {
        $claims = $this->postForm(self::VERIFY_URL, [
            'id_token' => $idToken,
            'client_id' => $this->channelId,
        ]);

        if (isset($claims['error'])) {
            // LINE ปฏิเสธ id_token (signature ไม่ผ่าน / format พัง ฯลฯ)
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: ' . ($claims['error_description'] ?? $claims['error']));
        }

        foreach (['iss', 'sub', 'aud', 'exp', 'iat'] as $required) {
            if (!isset($claims[$required])) {
                throw new AuthException('ID_TOKEN_INVALID', "ID_TOKEN_INVALID: missing {$required} claim");
            }
        }

        if ($claims['iss'] !== self::EXPECTED_ISSUER) {
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: issuer mismatch');
        }
        if ($claims['aud'] !== $this->channelId) {
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: audience mismatch');
        }
        if ((int) $claims['exp'] <= time()) {
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: token expired');
        }
        if ((int) $claims['iat'] > time() + self::IAT_SKEW) {
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: issued-at in the future');
        }
        if ($expectedNonce !== '' && (!isset($claims['nonce']) || !hash_equals($expectedNonce, (string) $claims['nonce']))) {
            throw new AuthException('ID_TOKEN_INVALID', 'ID_TOKEN_INVALID: nonce mismatch');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserProfile(string $accessToken): array
    {
        $request = $this->requestFactory->createRequest('GET', 'https://api.line.me/v2/profile')
            ->withAddedHeader('Authorization', 'Bearer ' . $accessToken);

        $response = $this->httpClient->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function postForm(string $url, array $form): array
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($form)));

        $response = $this->httpClient->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }
}
