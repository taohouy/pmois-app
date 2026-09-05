<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * RateLimitMiddleware (M5 — API Rate Limiting)
 *
 * - Sliding-window นับต่อ identity (API token ก่อน ถ้าไม่มีใช้ session/user)
 * - limit จาก env RATE_LIMIT_PER_MINUTE (default 120 — Configuration over Hardcode)
 * - เกิน limit → 429 RATE_LIMITED พร้อม X-RateLimit-* headers
 *
 * ข้อจำกัดที่ประกาศชัด: ตัวนับเป็น in-memory per PHP worker (พอสำหรับขนาดองค์กรภายใน
 * และ single-node deployment) — ถ้า scale หลาย node จะย้ายไป shared store ในอนาคต
 * (ไม่ใช้ Redis ตาม M0 constraint — ไม่เพิ่ม infrastructure)
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var array<string, array<int, int>> key => [timestamps] */
    private static array $windows = [];

    private const HEADER_LIMIT = 'X-RateLimit-Limit';
    private const HEADER_REMAINING = 'X-RateLimit-Remaining';
    private const HEADER_RESET = 'X-RateLimit-Reset';

    public function __construct(private readonly int $limitPerMinute)
    {
    }

    public static function fromEnv(): self
    {
        $limit = (int) ($GLOBALS['app_env']['RATE_LIMIT_PER_MINUTE'] ?? 120);

        return new self($limit > 0 ? $limit : 120);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $identity = $this->identityKey($request);
        $limit = $this->limitPerMinute;
        $now = time();

        $window = self::$windows[$identity] ?? [];
        $window = array_values(array_filter($window, static fn (int $ts): bool => $ts > $now - 60));
        $window[] = $now;
        self::$windows[$identity] = $window;

        $remaining = max(0, $limit - count($window));
        $reset = $now + 60;

        if (count($window) > $limit) {
            $response = (new ResponseFactory())->createResponse(429);
            $response = $response
                ->withHeader(self::HEADER_LIMIT, (string) $limit)
                ->withHeader(self::HEADER_REMAINING, '0')
                ->withHeader(self::HEADER_RESET, (string) $reset)
                ->withHeader('Retry-After', '60');
            $response->getBody()->write((string) json_encode([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'RATE_LIMITED', 'message' => 'Too many requests', 'details' => []],
                'meta' => ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader(self::HEADER_LIMIT, (string) $limit)
            ->withHeader(self::HEADER_REMAINING, (string) $remaining)
            ->withHeader(self::HEADER_RESET, (string) $reset);
    }

    private function identityKey(Request $request): string
    {
        $tokenId = $request->getAttribute('api_token_id');
        if ($tokenId !== null) {
            return 'token:' . $tokenId;
        }

        $userId = $request->getAttribute('user_id');
        if ($userId !== null) {
            return 'user:' . $userId;
        }

        return 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
