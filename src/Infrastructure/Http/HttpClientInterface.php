<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal HTTP client port ของระบบ (PSR-7 shapes)
 *
 * เหตุผลที่ไม่ใช้ Psr\Http\Client\ClientInterface: psr/http-client ไม่ได้อยู่ใน
 * composer.json/composer.lock ของโปรเจกต์ และเราไม่เพิ่ม dependency โดยไม่จำเป็น —
 * ใช้รูปทรงเดียวกัน (sendRequest) แต่ประกาศ interface เอง
 * ใช้โดย LineLoginService (LINE Login OIDC — CTO Constraint #1)
 */
interface HttpClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
