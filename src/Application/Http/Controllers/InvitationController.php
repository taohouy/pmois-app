<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\InvitationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class InvitationController implements RequestHandler
{
    public function __construct(private readonly \App\Domain\Auth\InvitationService $invitationService)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $method = $request->getMethod();

        if ($method === 'POST') {
            // Create invitation
            $data = $request->getParsedBody();
            $token = $this->invitationService->createInvitation(
                (int) ($data['workspace_id'] ?? 0),
                (int) ($data['project_id'] ?? 0),
                $request->getAttribute('user_id'),
                $data['role_code'] ?? 'MEMBER',
                $request->getAttribute('user_id')
            );

            return ApiResponse::success(['claim_url' => $this->generateClaimUrl($token)], 'Invitation created');
        }

        return ApiResponse::error('METHOD_NOT_ALLOWED', 'Method not allowed', [], 405);
    }

    private function generateClaimUrl(string $token): string
    {
        // In production, this would be a proper frontend URL
        return '/claim/' . $token;
    }
}