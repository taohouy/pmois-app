<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * HTTP client ด้วย cURL (implement HttpClientInterface ของระบบ)
 *
 * เหตุผลที่มีอยู่: composer.json ไม่มี HTTP client (guzzle ฯลฯ)
 * แต่ LineLoginService (M1 Phase 1 — LINE Login only) ต้องการ client สำหรับ
 * OIDC token exchange + ID token verify — เขียนเองเล็ก ๆ พอ ไม่เพิ่ม dependency ใหม่
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $ch = curl_init((string) $request->getUri());
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = (string) $request->getBody();
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string) $rawBody, 0, $headerSize);
        $responseBody = $headerSize > 0 ? substr((string) $rawBody, $headerSize) : (string) $rawBody;
        curl_close($ch);

        $response = $this->responseFactory->createResponse($statusCode);

        foreach ($this->parseHeaders($rawHeaders) as $name => $value) {
            $response = $response->withAddedHeader($name, $value);
        }

        $response->getBody()->write($responseBody);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $headers;
    }
}
