<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\InvitationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class InvitationClaimMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly \App\Domain\Auth\InvitationService $invitationService)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $request->getAttribute('token');

        if ($token === null) {
            return ApiResponse::error('INVALID_TOKEN', 'Invalid or missing claim token', [], 400);
        }

        $claims = (new \App\Domain\Auth\InvitationService(
            new \App\Infrastructure\Persistence\MySQL\MySqlProjectRepository(new \PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30')),
            new \App\Infrastructure\Persistence\MySQL\MySqlWorkspaceMemberRepository(new \PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30')),
            new \App\Infrastructure\Persistence\MySQL\MySqlUserRepository(new \PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30')),
            new \App\Domain\Auth\LineLoginService(
                new \GuzzleHttp\Client(),
                new \GuzzleHttp\Psr7\RequestFactory(),
                '', '', ''
            )
        ))->validateClaimToken($token);

        if ($claims === null) {
            return ApiResponse::error('INVALID_TOKEN', 'Invalid or expired claim token', [], 400);
        }

        $request = $request
            ->withAttribute('claim_workspace_id', $claims['workspace_id'])
            ->withAttribute('claim_user_id', $claims['user_id'])
            ->withAttribute('claim_role_code', $claims['role_code']);

        return $handler->handle($request);
    }
}