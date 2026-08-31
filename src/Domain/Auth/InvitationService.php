<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Signer\Key\InMemory;

final class InvitationService
{
    private Configuration $jwtConfig;

    public function __construct(
        private readonly \App\Domain\Project\ProjectRepositoryInterface $projectRepository,
        private readonly \App\Domain\Workspace\WorkspaceMemberRepositoryInterface $workspaceMemberRepository,
        private readonly \App\Domain\Identity\UserRepositoryInterface $userRepository,
        private readonly \App\Domain\Auth\LineLoginService $lineLoginService,
    ) {
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            InMemory::plainText('your-secret-key-change-in-production')
        );
    }

    public function createInvitation(int $workspaceId, int $projectId, int $actorId, string $roleCode, int $invitedBy): string
    {
        // Create placeholder user (without line_user_id)
        $userId = $this->userRepository->create([
            'name' => 'Pending User',
            'email' => 'pending_' . uniqid() . '@placeholder',
            'password_hash' => null,
            'auth_provider' => 'line',
            'line_user_id' => null,
        ]);

        // Create workspace member with active status (will be activated after LINE binding)
        $this->workspaceMemberRepository->create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role_id' => $this->getRoleIdByCode('MEMBER'), // default role, can be overridden
            'status' => 'active',
        ]);

        // Generate claim token (JWT with 24h expiry)
        $now = new \DateTimeImmutable();
        $token = $this->jwtConfig->builder()
            ->issuedBy('pmois')
            ->permittedFor('pmois')
            ->identifiedBy('invite-' . uniqid())
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt($now->modify('+24 hours'))
            ->withClaim('workspace_id', $workspaceId)
            ->withClaim('user_id', $userId)
            ->withClaim('role_code', 'MEMBER')
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

        return $token->toString();
    }

    public function validateClaimToken(string $token): ?array
    {
        try {
            $parsed = $this->jwtConfig->parser()->parse($token);
            $this->jwtConfig->validator()->assert($this->jwtConfig->validator()->validate($parsed));

            return [
                'workspace_id' => $parsed->claims()->get('workspace_id'),
                'user_id' => $parsed->claims()->get('user_id'),
                'role_code' => $parsed->claims()->get('role_code'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function processClaim(string $token, string $lineSub, string $lineDisplayName, string $avatarUrl): int
    {
        $claims = $this->validateClaimToken($token);
        if ($claims === null) {
            throw new \InvalidArgumentException('Invalid or expired claim token');
        }

        $userId = $claims['user_id'];
        $workspaceId = $claims['workspace_id'];

        // Update user with LINE info
        // This would update the user record with LINE profile data
        // Implementation depends on UserRepository having an update method

        return $claims['user_id'];
    }

    private function getRoleIdByCode(string $roleCode): int
    {
        // This should be implemented with a role repository
        // For now, return a default
        return 1;
    }
}