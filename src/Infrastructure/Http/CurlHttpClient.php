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
 *
 * M9 UAT Runtime Fix Revision 4 — แก้ root cause ของ
 * "Header name must be an RFC 7230 compatible string":
 *  เดิมโค้ดคิดว่า curl_exec() คืน "headers + body" แล้วตัดเอา CURLINFO_HEADER_SIZE
 *  ไบต์แรกของ body มา parse เป็น header — แต่ CURLOPT_RETURNTRANSFER คืน "body เท่านั้น"
 *  ผลคือ JSON จาก LINE ({"access_token":...}) ถูกตัดช่วงต้นมา parse เป็น header
 *  แล้วชื่อ header กลายเป็น {"access_token" → โยน InvalidArgumentException ทุกครั้งใน production
 *  (unit test ไม่จับ เพราะ mock HTTP client)
 *
 * วิธีแก้: ใช้ CURLOPT_HEADERFUNCTION จับ header จริงทีละบรรทัด (skip status line),
 *  validate header name ตาม RFC 7230 ก่อนใส่ลง response — บรรทัดที่ผิดรูปแบบข้าม ไม่ throw
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

        /** @var list<array{0: string, 1: string}> $responseHeaders */
        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            // จับ response header จริงจาก cURL โดยตรง — ไม่ parse จาก body
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                $parsed = self::parseHeaderLine($line);
                if ($parsed !== null) {
                    $responseHeaders[] = $parsed;
                }

                return strlen($line); // ต้องคืนจำนวน byte ที่ consume เสมอ
            },
        ]);

        $body = (string) $request->getBody();
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // CURLOPT_RETURNTRANSFER → curl_exec คืน body เท่านั้น (headers มาทาง HEADERFUNCTION)
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }
        curl_close($ch);

        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($statusCode < 100 || $statusCode > 599) {
            // ไม่มี status จริง (เช่น connect ล้มเหลวแบบเงียบ) — fail-closed
            throw new RuntimeException('HTTP response has no valid status code');
        }

        $response = $this->responseFactory->createResponse($statusCode);

        foreach ($responseHeaders as [$name, $value]) {
            $response = $response->withAddedHeader($name, $value);
        }

        $response->getBody()->write((string) $responseBody);

        return $response;
    }

    /**
     * Parse response header line หนึ่งบรรทัด
     *
     * @return array{0: string, 1: string}|null [name, value] หรือ null ถ้าไม่ใช่ header
     *         (status line / บรรทัดว่าง / ชื่อ header ผิดรูปแบบ RFC 7230 — ข้าม ไม่ throw)
     */
    public static function parseHeaderLine(string $line): ?array
    {
        $line = trim($line, "\r\n");

        if ($line === '' || str_starts_with($line, 'HTTP/')) {
            // status line (รวม interim "HTTP/1.1 100 Continue") — ไม่ใช่ header
            return null;
        }

        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            return null; // ไม่มี colon — ไม่ใช่ header
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        // RFC 7230 token charset — เหมือนเกณฑ์ของ Slim PSR-7; ผิดรูปแบบ = ข้าม
        if ($name === '' || preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@D", $name) !== 1) {
            return null;
        }

        return [$name, $value];
    }
}
