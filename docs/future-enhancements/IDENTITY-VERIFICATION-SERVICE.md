# Future Enhancement — IdentityVerificationService

**ที่มา:** CTO Decision on M1 Implementation Revision 2 (APPROVED WITH OBSERVATION, 98/100) — Observation (Non-blocking)
**สถานะ:** บันทึกไว้ตามมติ — **ยังไม่ดำเนินการใน M2**
**Date:** 2026-09-05

---

## 1. เป้าหมาย

รองรับการเปลี่ยนแปลงของ LINE API ในอนาคต โดย**แยก Logic การ Verify Identity ออกจาก LINE Implementation** ผ่าน Service Layer กลาง

## 2. รูปร่างที่เสนอ

```
PmoisAuthenticationService
        │
        ▼
IdentityVerificationService        ← Layer กลาง (interface)
        │  verifyOAuthCallback(state, code, fingerprint): VerifiedIdentity
        │  verifyClaimBinding(...): VerifiedIdentity
        ▼
LineIdentityVerifier (implementation ปัจจุบัน)
   - state store + fingerprint + nonce
   - LINE verify endpoint + iss/aud/exp/iat/nonce
```

```php
interface IdentityVerifierInterface
{
    /** @return VerifiedIdentity{subject, displayName, pictureUrl, purpose} */
    public function verifyOAuthCallback(string $state, string $code, string $fingerprint): VerifiedIdentity;
}
```

- `PmoisAuthenticationService` จะพึ่ง interface แทน `LineLoginService` โดยตรง
- Provider ใหม่ในอนาคต (ถ้ามีนโยบายเปลี่ยน) = implementation ใหม่ของ interface เดิม — **ไม่ใช่** การเพิ่ม IdP ใหม่ (ยังคง Constraint #1: LINE Login only)

## 3. ขอบเขตเมื่อทำ

1. แตก verify logic จาก `PmoisAuthenticationService::completeCallback` ไป `LineIdentityVerifier`
2. ย้าย state/nonce/fingerprint mechanics เข้า verifier implementation
3. Tests ที่มีอยู่ (`LineAuthenticationTest`) ต้องผ่านโดยแก้ wiring เท่านั้น

## 4. เหตุผลที่ยังไม่ทำใน M2

- M2 เป็น read-only dashboard ไม่แตะ auth path
- Flow ปัจจุบันผ่าน security review แล้ว (M1 R2) — refactor เชิงโครงสร้างควรทำตอนมีเหตุผลจริง (LINE API เปลี่ยน / มี provider ที่สองตามนโยบายใหม่)
