<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\InvitationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class ClaimController implements \Psr\Http\Server\RequestHandlerInterface
{
    public function __construct(private readonly \App\Domain\Auth\InvitationService $invitationService)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $token = $request->getAttribute('token');

        if ($token === null) {
            return ApiResponse::error('INVALID_TOKEN', 'Invalid or missing claim token', [], 400);
        }

        // Process the claim - this would be handled by InvitationService
        // For now, return a placeholder response
        return ApiResponse::success(['message' => 'Claim processed'], 'Claim processed');
    }
}