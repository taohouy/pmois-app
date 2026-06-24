<?php

declare(strict_types=1);

namespace App\Application\Http\Responders;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Standard API Response Envelope
 * อ้างอิง System Design v0.1 หมวด 3.3 — ใช้ทุก endpoint ไม่มีข้อยกเว้น
 */
final class ApiResponse
{
    public static function success(Response $response, mixed $data, array $meta = [], int $status = 200): Response
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => array_merge(
                ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
                $meta
            ),
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function error(
        Response $response,
        string $code,
        string $message,
        array $details = [],
        int $status = 400
    ): Response {
        $payload = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
