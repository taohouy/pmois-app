<?php

declare(strict_types=1);

namespace App\Application\Http;

/**
 * AuditContext
 *
 * ⚠️ พบปัญหานี้ระหว่างเขียน Controller จริง (ไม่ใช่ design ที่วางแผนไว้แต่แรก):
 * PSR-7 Request เป็น immutable — ถ้า Controller เรียก $request->withAttribute(...)
 * จะได้ Request object ใหม่ที่ AuditLoggingMiddleware (ซึ่งถือ reference ของ Request
 * ตัวเดิมไว้ตั้งแต่ก่อนเรียก $handler->handle($request)) "มองไม่เห็น" การเปลี่ยนแปลงนั้นเลย
 * เพราะมันคือ object คนละตัวกัน
 *
 * วิธีแก้: ใช้ object ที่ mutable (คลาสนี้) แทนการพึ่ง request attribute string-based
 * แนบ AuditContext เป็น attribute ตัวเดียวตอนต้น pipeline (ใน AuthTokenMiddleware) —
 * เพราะ "ตัว object" ถูกแนบเป็น reference เดียวกันตลอด แม้ Request เปลี่ยน identity
 * ไปเรื่อยๆ ตาม withAttribute() ของ key อื่น Controller ก็ยัง mutate object ตัวเดิมได้
 * และ AuditLoggingMiddleware อ่านค่าล่าสุดจาก object เดียวกันนี้ได้เสมอ
 */
final class AuditContext
{
    private ?string $entityType = null;
    private ?int $entityId = null;
    private ?string $action = null;
    /** @var array<string, mixed>|null */
    private ?array $beforeValue = null;
    /** @var array<string, mixed>|null */
    private ?array $afterValue = null;

    public function record(
        string $entityType,
        int $entityId,
        ?array $afterValue = null,
        ?array $beforeValue = null,
        ?string $action = null
    ): void {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->afterValue = $afterValue;
        $this->beforeValue = $beforeValue;
        $this->action = $action; // null = ให้ AuditLoggingMiddleware ใช้ default mapping ตาม HTTP method
    }

    public function hasEntity(): bool
    {
        return $this->entityType !== null;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getBeforeValue(): ?array
    {
        return $this->beforeValue;
    }

    public function getAfterValue(): ?array
    {
        return $this->afterValue;
    }
}
