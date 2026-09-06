<?php

declare(strict_types=1);

namespace App\Application\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;

/**
 * AppErrorMiddleware (M9 UAT Runtime Fix — Issue 4)
 *
 * หน้าที่: เปลี่ยน uncaught exception / 404 ให้เป็น Generic response เสมอ
 *  - APP_DEBUG=false (production): ห้ามแสดง stack trace / source path / vendor path /
 *    internal exception message — แสดง Generic Error Page (browser) หรือ
 *    Generic JSON envelope (API) เท่านั้น; รายละเอียดบันทึกลง server log ผ่าน error_log()
 *  - APP_DEBUG=true (development): rethrow ให้ Slim แสดง details ตามปกติ
 */
final class AppErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly bool $debug)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e; // dev — Slim error middleware แสดง details
            }

            // Server-side log เท่านั้น — ห้ามส่งออกนอกระบบ
            error_log(sprintf(
                '[PMOIS] %s: %s in %s:%d | uri=%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $request->getUri()->getPath()
            ));

            return $this->genericResponse($request, $e);
        }
    }

    private function genericResponse(Request $request, \Throwable $e): Response
    {
        [$status, $code] = $this->classify($e);

        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            return $this->genericHtml($request, $status, $code);
        }

        $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse($status);
        $response->getBody()->write((string) json_encode([
            'success' => false,
            'data' => null,
            'error' => ['code' => $code, 'message' => $code === 'NOT_FOUND' ? 'Not Found' : 'Internal Server Error', 'details' => []],
            'meta' => ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array{0: int, 1: string} [http status, error code]
     */
    private function classify(\Throwable $e): array
    {
        if ($e instanceof HttpNotFoundException) {
            return [404, 'NOT_FOUND'];
        }
        if ($e instanceof HttpException) {
            $status = $e->getCode();
            return [$status >= 400 && $status < 600 ? $status : 500, 'REQUEST_FAILED'];
        }

        return [500, 'INTERNAL_ERROR'];
    }

    private function genericHtml(Request $request, int $status, string $code): Response
    {
        $title = $status === 404 ? '404 — Page Not Found' : '500 — Something went wrong';
        $message = $status === 404
            ? 'The page you requested does not exist.'
            : 'An unexpected error occurred. Please try again later.';

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>' . $title . ' — PMOIS</title></head>'
            . '<body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;color:#1f2937">'
            . '<div style="text-align:center"><h1>' . (int) $status . '</h1>'
            . '<p>' . $message . '</p>'
            . '<p><a href="/">Back to home</a></p></div></body></html>';

        $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse($status);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
