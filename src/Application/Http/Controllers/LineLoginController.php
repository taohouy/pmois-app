<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Auth\LineLoginService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class LineLoginController implements \Psr\Http\Server\RequestHandlerInterface
{
    public function __construct(private readonly \App\Domain\Auth\LineLoginService $lineLoginService)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (str_starts_with($request->getUri()->getPath(), '/auth/line')) {
            if (str_ends_with($request->getUri()->getPath(), '/callback')) {
                return $this->handleCallback($request);
            }

            return $this->handleLogin($request);
        }

        return ApiResponse::error('NOT_FOUND', 'Not found', [], 404);
    }

    private function handleLogin(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $state = bin2hex(random_bytes(16));
        $authUrl = $this->lineLoginService->getAuthorizationUrl($state);

        // Store state in session or cache (simplified)
        return ApiResponse::success(['auth_url' => $authUrl, 'state' => $state], 'Redirect to LINE Login');
    }

    private function handleCallback(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;

        if ($code === null) {
            return ApiResponse::error('INVALID_CALLBACK', 'Missing authorization code', [], 400);
        }

        try {
            $tokens = $this->lineLoginService->exchangeCodeForTokens($code);
            $userProfile = $this->lineLoginService->getUserProfile($tokens['access_token']);

            // Lookup or create user
            // For now, return the user info
            return ApiResponse::success([
                'line_user_id' => $userProfile['userId'] ?? null,
                'display_name' => $userProfile['displayName'] ?? null,
                'picture_url' => $userProfile['pictureUrl'] ?? null,
            ], 'LINE Login successful');

        } catch (\Throwable $e) {
            return ApiResponse::error('LINE_LOGIN_FAILED', $e->getMessage(), [], 400);
        }
    }
}