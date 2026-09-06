<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Authentication/Authorization exception ที่แบก error code แยกจาก message
 *
 * หลักการ (CTO Runtime Review):
 *  - AUTH_FAILED          = login ไม่สำเร็จ (state/token/exchange ล้มเหลว)
 *  - UNAUTHORIZED_IDENTITY= login สำเร็จแต่ไม่มีสิทธิ์เข้าใช้งาน (authorization)
 *  - code ต้องไม่ถูก "กลืน" ด้วย message matching — controller อ่าน $e->errorCode ตรง
 */
final class AuthException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $detail = ''
    ) {
        parent::__construct($detail !== '' ? $detail : $errorCode);
    }
}
