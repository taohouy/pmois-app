<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Identity\RoleRepositoryInterface;
use App\Domain\Identity\UserRepositoryInterface;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Workspace\WorkspaceMemberRepositoryInterface;

/**
 * InvitationService — Invitation/Account Claim flow (M0 R5 §7.1, M1 Plan §3.2)
 *
 * Claim token: HMAC-SHA256 signed payload (base64url) — ไม่พึ่ง dependency ภายนอก
 * โครงสร้าง: base64url(json payload) . '.' . base64url(hmac) โดย payload มี exp (24h)
 *
 * หลัก fail-closed (ห้ามลืม):
 *  - การสร้าง account ทำที่นี่เท่านั้น (placeholder user + active membership + claim token)
 *  - LINE Login ปกติ "ไม่สร้าง" user/membership — แค่ bind line_user_id ผ่าน claim
 */
final class InvitationService
{
    private const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly WorkspaceMemberRepositoryInterface $workspaceMemberRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LineLoginService $lineLoginService,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {
    }

    public function createInvitation(int $workspaceId, int $projectId, int $actorId, string $roleCode, int $invitedBy): string
    {
        // Create placeholder user (without line_user_id)
        $user = $this->userRepository->createWithLine(
            'Pending User',
            'pending_' . uniqid() . '@placeholder',
            '',
            'line'
        );

        // Resolve role (MEMBER default) — role ต้องมีจริงจาก migration 0011
        $role = $this->roleRepository->findByCode($roleCode);
        if ($role === null) {
            throw new \InvalidArgumentException("role '{$roleCode}' not seeded");
        }

        // Active membership ทันที (claim เป็นแค่การ bind line_user_id ภายหลัง)
        $this->workspaceMemberRepository->create(
            $workspaceId,
            $user->id,
            $role->id,
            'active'
        );

        return $this->signToken([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'user_id' => $user->id,
            'role_code' => $roleCode,
            'exp' => time() + self::TOKEN_TTL_HOURS * 3600,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateClaimToken(string $token): ?array
    {
        $payload = $this->parseToken($token);
        if ($payload === null) {
            return null;
        }
        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public function processClaim(string $token, string $lineSub, string $lineDisplayName, string $avatarUrl): int
    {
        $claims = $this->validateClaimToken($token);
        if ($claims === null) {
            throw new \InvalidArgumentException('Invalid or expired claim token');
        }

        $userId = (int) $claims['user_id'];

        // claim reuse — account นี้ถูก bind ไปแล้ว (ห้าม claim ซ้ำ)
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('Claim token points to a missing account');
        }
        if ($user->lineUserId !== null && $user->lineUserId !== '') {
            throw new \DomainException('CLAIM_ALREADY_USED');
        }

        // duplicate binding — LINE account นี้ถูก bind กับ user อื่นอยู่แล้ว
        $existing = $this->userRepository->findByLineUserId($lineSub);
        if ($existing !== null && $existing->id !== $userId) {
            throw new \DomainException('LINE_ALREADY_BOUND');
        }

        // bind line_user_id — จุดเดียวที่ LINE identity ถูกผูกกับ account (fail-closed rule)
        $this->userRepository->updateLineInfo($userId, $lineSub, $lineDisplayName, $avatarUrl, 'line');

        return $userId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signToken(array $payload): string
    {
        $body = $this->base64UrlEncode((string) json_encode($payload));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->secret(), true));

        return $body . '.' . $signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($body), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function secret(): string
    {
        // Configuration over Hardcode: APP_SECRET จาก env — fallback สำหรับ dev เท่านั้น
        return $GLOBALS['app_env']['APP_SECRET'] ?? 'pmois-dev-secret-change-in-production';
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
