<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Identity\UserRepositoryInterface;
use PDO;

/**
 * PmoisAuthenticationService — หัวใจของ Authentication Flow (CTO Review 1.1–1.3)
 *
 * Login flow:
 *   beginLogin(fingerprint)  -> persist state (one-time, hash-only, bound to fingerprint) + nonce
 *   completeCallback(state, code, fingerprint)
 *     -> verify state (exists / not used / not expired / fingerprint match)
 *     -> exchange code -> verify id_token (signature ผ่าน LINE, iss/aud/exp/iat/nonce)
 *     -> mark state used (one-time)
 *     -> resolve PMOIS user จาก verified line_user_id (sub)
 *     -> fail-closed: user active + active workspace membership เท่านั้น
 *     -> create PMOIS session
 *
 * Claim flow (CTO Review 1.4) — client ห้ามกำหนด line_user_id เอง:
 *   beginClaim(fingerprint, claimToken) -> state (purpose=claim, bind claim token)
 *   completeCallback(...) -> verified sub เท่านั้นถูก bind
 *     -> validate claim token (expiry/tamper) -> user ยังไม่ถูก claim
 *     -> ไม่มี user อื่นถือ sub นี้ (duplicate binding) -> bind -> session
 */
final class PmoisAuthenticationService
{
    private const STATE_TTL_SECONDS = 600;   // 10 นาทีพอสำหรับ OAuth round trip
    private const SESSION_TTL_HOURS = 8;

    public function __construct(
        private readonly LineLoginService $lineLoginService,
        private readonly OAuthStateRepositoryInterface $stateRepository,
        private readonly UserSessionRepositoryInterface $sessionRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly InvitationService $invitationService,
        private readonly PDO $db,
    ) {
    }

    /**
     * @return array{state: string, auth_url: string}
     */
    public function beginLogin(string $fingerprint): array
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));

        $this->stateRepository->create(
            hash('sha256', $state),
            'login',
            hash('sha256', $fingerprint),
            $nonce,
            null,
            (new \DateTimeImmutable('+' . self::STATE_TTL_SECONDS . ' seconds'))->format('Y-m-d H:i:s')
        );

        return [
            'state' => $state,
            'auth_url' => $this->lineLoginService->getAuthorizationUrl($state, $nonce),
        ];
    }

    /**
     * @return array{state: string, auth_url: string}
     * @throws \InvalidArgumentException CLAIM_TOKEN_INVALID
     */
    public function beginClaim(string $fingerprint, string $claimToken): array
    {
        // claim token ต้อง valid (HMAC + ไม่หมดอายุ) ก่อนเริ่ม OAuth
        if ($this->invitationService->validateClaimToken($claimToken) === null) {
            throw new \InvalidArgumentException('CLAIM_TOKEN_INVALID');
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));

        $this->stateRepository->create(
            hash('sha256', $state),
            'claim',
            hash('sha256', $fingerprint),
            $nonce,
            $claimToken,
            (new \DateTimeImmutable('+' . self::STATE_TTL_SECONDS . ' seconds'))->format('Y-m-d H:i:s')
        );

        return [
            'state' => $state,
            'auth_url' => $this->lineLoginService->getAuthorizationUrl($state, $nonce),
        ];
    }

    /**
     * @return array{user_id: int, workspace_id: int|null, purpose: string, session_token: string, claimed: bool}
     * @throws \DomainException STATE_INVALID|STATE_EXPIRED|STATE_MISMATCH|STATE_REUSED|UNAUTHORIZED_IDENTITY|CLAIM_TOKEN_INVALID|CLAIM_ALREADY_USED|LINE_ALREADY_BOUND
     * @throws \RuntimeException OAUTH_EXCHANGE_FAILED
     * @throws \InvalidArgumentException ID_TOKEN_INVALID
     */
    public function completeCallback(string $state, string $code, string $fingerprint): array
    {
        // 1. State verification — one-time, bound to browser fingerprint
        $stateRow = $this->stateRepository->findByStateHash(hash('sha256', $state));
        if ($stateRow === null) {
            throw new \DomainException('STATE_INVALID');
        }
        if ($stateRow['used_at'] !== null) {
            throw new \DomainException('STATE_REUSED');
        }
        if (strtotime((string) $stateRow['expires_at']) < time()) {
            throw new \DomainException('STATE_EXPIRED');
        }
        if (!hash_equals((string) $stateRow['fingerprint_hash'], hash('sha256', $fingerprint))) {
            throw new \DomainException('STATE_MISMATCH');
        }

        // 2. Exchange + verify ID token (signature/iss/aud/exp/iat/nonce)
        $tokens = $this->lineLoginService->exchangeCodeForTokens($code);
        $claims = $this->lineLoginService->verifyIdToken(
            (string) $tokens['id_token'],
            (string) ($stateRow['nonce'] ?? '')
        );

        // 3. One-time use — mark ทันทีที่ identity ผ่าน verification
        $this->stateRepository->markUsed((int) $stateRow['id']);

        $lineUserId = (string) $claims['sub'];
        $displayName = (string) ($claims['name'] ?? '');
        $pictureUrl = (string) ($claims['picture'] ?? '');

        // 4. PMOIS authentication
        if (($stateRow['purpose'] ?? 'login') === 'claim') {
            return $this->completeClaim((string) $stateRow['claim_token'], $lineUserId, $displayName, $pictureUrl);
        }

        return $this->establishSession($lineUserId, $displayName, $pictureUrl, 'login', false);
    }

    /**
     * Bind verified LINE identity กับ placeholder account (claim flow)
     */
    private function completeClaim(string $claimToken, string $lineUserId, string $displayName, string $pictureUrl): array
    {
        $claimClaims = $this->invitationService->validateClaimToken($claimToken);
        if ($claimClaims === null) {
            throw new \DomainException('CLAIM_TOKEN_INVALID');
        }

        $userId = (int) $claimClaims['user_id'];
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \DomainException('CLAIM_TOKEN_INVALID');
        }

        // claim reuse: account นี้ถูก claim ไปแล้ว (line_user_id ว่าง = ยังไม่ claim)
        if ($user->lineUserId !== null && $user->lineUserId !== '') {
            throw new \DomainException('CLAIM_ALREADY_USED');
        }

        // duplicate binding: LINE account นี้ถูก bind กับ user อื่นแล้ว
        $existing = $this->userRepository->findByLineUserId($lineUserId);
        if ($existing !== null && $existing->id !== $userId) {
            throw new \DomainException('LINE_ALREADY_BOUND');
        }

        // bind — ใช้ verified sub เท่านั้น (client ห้ามกำหนดเอง)
        $this->invitationService->processClaim($claimToken, $lineUserId, $displayName, $pictureUrl);

        return $this->establishSession($lineUserId, $displayName, $pictureUrl, 'claim', true);
    }

    /**
     * Fail-closed PMOIS session establishment (CTO Review 1.3)
     */
    private function establishSession(string $lineUserId, string $displayName, string $pictureUrl, string $purpose, bool $claimed): array
    {
        $user = $this->userRepository->findByLineUserId($lineUserId);

        // fail-closed: ไม่มี bound user หรือ suspended
        if ($user === null || $user->status !== 'active') {
            throw new \DomainException('UNAUTHORIZED_IDENTITY');
        }

        // fail-closed: ต้องมี active workspace membership อย่างน้อย 1
        $stmt = $this->db->prepare(
            "SELECT workspace_id FROM workspace_members
             WHERE user_id = :user_id AND status = 'active'
             ORDER BY workspace_id ASC LIMIT 1"
        );
        $stmt->execute(['user_id' => $user->id]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($membership === false) {
            throw new \DomainException('UNAUTHORIZED_IDENTITY');
        }

        // 5. create authenticated PMOIS session
        $sessionToken = bin2hex(random_bytes(32));
        $this->sessionRepository->create(
            $user->id,
            hash('sha256', $sessionToken),
            $purpose,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            (new \DateTimeImmutable('+' . self::SESSION_TTL_HOURS . ' hours'))->format('Y-m-d H:i:s')
        );

        return [
            'user_id' => $user->id,
            'workspace_id' => (int) $membership['workspace_id'],
            'purpose' => $purpose,
            'session_token' => $sessionToken,
            'claimed' => $claimed,
            'display_name' => $displayName,
            'picture_url' => $pictureUrl,
        ];
    }

    /**
     * @return array<string, mixed>|null session row (active)
     */
    public function resolveSession(string $sessionToken): ?array
    {
        return $this->sessionRepository->findActiveByTokenHash(hash('sha256', $sessionToken));
    }

    public function logout(string $sessionToken): bool
    {
        $session = $this->resolveSession($sessionToken);
        if ($session === null) {
            return false;
        }

        return $this->sessionRepository->revoke((int) $session['id']);
    }
}
