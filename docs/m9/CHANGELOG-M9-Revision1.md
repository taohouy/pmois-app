# PMOIS v2 — M9 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M8 R1 (100/100) — Production Hardening / UAT / Go-Live
**Date:** 2026-09-06

**Status:** ✅ CTO APPROVED (100/100) — Commit เป็น **M9 CTO-Approved Baseline**
**Next gate:** CEO UAT — Tag Release / Production Baseline / Production Deployment **ยังไม่ดำเนินการ** จนกว่า CEO UAT จะผ่าน

---

## 1. New Deliverables

| ไฟล์ | คำอธิบาย |
|---|---|
| `deploy/PMOIS_v2_Database_Install.sql` | **Consolidated installer ไฟล์เดียว** — 45 ตาราง + seed data (roles/permissions/AI providers/GitLab) + admin เริ่มต้น; รวม migrations 0001–0064 ยกเว้น workspace seeds (0029/0032) |
| `deploy/PMOIS_v2_Database_Rollback.sql` | FK-safe drop ทุกตาราง (generated จาก installed schema — 44/45 ตรวจแล้ว + เติม git_providers) |
| `deploy/.env.production.example` | Production configuration template (APP_SECRET/LINE/Telegram/Rate limit พร้อมคำอธิบาย) |
| `bin/create-admin.php` | สร้าง/promote platform admin (Installation step) |
| `bin/admin-claim-url.php` | พิมพ์ claim URL ผูก LINE account ของ admin (HMAC 24h) |
| `bin/validate-integrations.php` | Pre-UAT gate: PHP/extensions/DB/APP_SECRET/LINE/GitLab/Telegram/Storage/Automation tables |

## 2. Documentation (docs/production/)

- `INSTALLATION-GUIDE.md` — ติดตั้ง 7 ขั้น (โค้ด → DB ไฟล์เดียว → env → admin+LINE → validate → web server → UAT)
- `ROLLBACK-GUIDE.md` — DB rollback (destructive + backup-first) + app rollback (symlink pattern) + ข้อควรระวัง APP_SECRET
- `OPERATIONS-GUIDE.md` — cron (worker + cleanup), monitoring endpoints, งานประจำ, troubleshooting
- `SECURITY-REVIEW-M9.md` — authN/authZ/secrets/input/API hardening + ความเสี่ยงคงเหลือ
- `PERFORMANCE-REVIEW-M9.md` — ผลทดสอบจริง + index coverage + สิ่งที่ต้องเฝ้าดู
- `RELEASE-NOTES.md` — สรุปฟีเจอร์ M1–M8 + ข้อจำกัดที่ประกาศชัด

## 3. Validation ที่รันจริง (บน MySQL 5.7.36 temp instance)

1. **Fresh install จาก consolidated SQL** → 45 ตาราง + 5 providers + automation permissions + 1 admin — PASS
2. **Full regression suite บน installed DB** → 177 tests / 433 assertions — PASS
3. **Backup/Restore** → mysqldump → drop → restore → 45/45 ตาราง + seed intact — PASS
4. **Rollback SQL** → 0 ตาราง → re-install ด้วย installer → 45 ตาราง — PASS
5. **validate-integrations.php** → DB/Schema/APP_SECRET/Storage/Automation PASS; LINE/GitLab connectivity = unreachable ใน sandbox (ไม่มี internet) — ต้องรันซ้ำใน production network ก่อน UAT

## 4. Fixes ระหว่าง hardening

- Consolidated installer: เรียง admin seed **ก่อน** migration 0043 (seed providers ต้องการ admin)
- Database Rollback: สร้างจาก installed schema แทน hardcode (จับ table ที่ลืม — git_providers)

---

## 5. UAT Runtime Fix Revision 1 (2026-09-06 — จาก CEO UAT Defect Report)

| Issue | Fix |
|---|---|
| 1. `/auth/line` ตอบ JSON ให้ browser | Web route ตอบ **302 → LINE Authorization** ทันที (พร้อม state/fingerprint cookie) |
| 2. `GET /` ตอบ 404 | Root route ใหม่: ไม่ login → 302 `/auth/line`; login แล้ว (valid session) → 302 dashboard |
| 3. แยก Web/API routes | Web: `/auth/line`, `/auth/line/callback`, `/claim/{token}` (302 ทั้งหมด); API: `/api/v1/auth/line`, `/api/v1/auth/line/callback` (JSON) |
| 4. Production error handling | `AppErrorMiddleware` (outermost): APP_DEBUG=false → generic 404/500 (HTML/JSON ตาม Accept) ไม่มี stack trace/source path/internal message; details log server-side ผ่าน error_log; APP_DEBUG=true = dev mode rethrow |

**Tests:** `HttpRuntimeTest` (12 เคส) — root before/after login, web 302, API JSON, callback success → session cookie, fail → login?error=, generic 404/500 ไม่มี internals, debug mode rethrow
**Full Suite:** 187 tests / 465 assertions — OK (`TEST-RESULTS-UAT-RUNTIME-FIX-1.txt`)

---

## 6. UAT Runtime Fix Revision 2 (2026-09-06 — จาก CTO Conditional Pass)

### ปัญหาที่ CTO พบ (2 ข้อ)

| Issue | อาการ | สาเหตุ |
|---|---|---|
| 1 | Login สำเร็จ callback กลับไป `/app/index.html?error=AUTH_FAILED` แทน dashboard | Controller ใช้ `safeErrorCode()` สกัด error code จาก exception message string ทำให้ catch-all `\Throwable` กลืน error ทุกอย่างเป็น `AUTH_FAILED` |
| 2 | Unauthorized identity แสดง `AUTH_FAILED` แทน `UNAUTHORIZED_IDENTITY` | `\DomainException` / `\InvalidArgumentException` ไม่มี error code เป็นของตัวเอง Controller ต้อง parse message string เพื่อแยก Authentication vs Authorization |

### แนวทางการแก้ — AuthException พร้อม `errorCode` property

แยก error code ออกจาก message โดยใช้ class เฉพาะ `App\Domain\Auth\AuthException` ที่ carry `errorCode` เป็น property แยก (ไม่ต้อง parse message string):

```php
final class AuthException extends \RuntimeException {
    public function __construct(
        public readonly string $errorCode,
        string $detail = ''
    ) { parent::__construct($detail !== '' ? $detail : $errorCode); }
}
```

Controller อ่าน `$e->errorCode` โดยตรง — ไม่ต้อง string-match:

```php
} catch (AuthException $e) {
    return $response->withHeader('Location', "/app/index.html?error={$e->errorCode}")->withStatus(302);
} catch (\Throwable $e) {
    error_log('[AUTH] ' . $e->getMessage());
    return $response->withHeader('Location', '/app/index.html?error=AUTH_FAILED')->withStatus(302);
}
```

### ไฟล์ที่เปลี่ยน

| ไฟล์ | การเปลี่ยน |
|---|---|
| `src/Domain/Auth/AuthException.php` | **ใหม่** — class ใหม่ carry `errorCode` property |
| `src/Domain/Auth/LineLoginService.php` | `\InvalidArgumentException` → `AuthException('ID_TOKEN_INVALID', ...)`; `\RuntimeException('OAUTH_EXCHANGE_FAILED')` → `AuthException('OAUTH_EXCHANGE_FAILED')` |
| `src/Domain/Auth/PmoisAuthenticationService.php` | `\DomainException` / `\InvalidArgumentException` → `AuthException` ทั้งหมด (STATE_INVALID, STATE_REUSED, STATE_EXPIRED, STATE_MISMATCH, CLAIM_TOKEN_INVALID, CLAIM_ALREADY_USED, LINE_ALREADY_BOUND, UNAUTHORIZED_IDENTITY) |
| `src/Domain/Auth/InvitationService.php` | throws ทั้งหมด → `AuthException` |
| `src/Application/Http/Controllers/LineLoginController.php` | `callback()` / `apiCallback()` catch `AuthException` → ใช้ `$e->errorCode`; catch `\Throwable` → `AUTH_FAILED` + `error_log`; ลบ `safeErrorCode()`; `errorMessage()`/`errorStatus()` รับ errorCode string |
| `src/Application/Http/Controllers/ClaimController.php` | `start()` catch `AuthException` → 302 with `$e->errorCode` |
| `tests/Integration/LineAuthenticationTest.php` | `expectException(\DomainException)` / `\InvalidArgumentException` → `\App\Domain\Auth\AuthException`; catch block → `$e->errorCode` |
| `tests/Integration/HttpRuntimeTest.php` | เพิ่ม `POST /auth/logout` route; `testSessionPersistsAcrossRequests`; `testLogoutRevokesSession`; `testIdTokenInvalidKeepsDistinctErrorCode`; timezone-safe seed (DATE_ADD NOW 8h) |

### Error Code ที่แยกออกจากกันชัดเจน

| Code | ความหมาย | ที่เกิด |
|---|---|---|
| `AUTH_FAILED` | Login ล้มเหลว (catch-all) | Controller catch `\Throwable` |
| `UNAUTHORIZED_IDENTITY` | Login สำเร็จแต่ไม่มี permission | PmoisAuthenticationService |
| `ID_TOKEN_INVALID` | ID token verify ไม่ผ่าน | LineLoginService |
| `OAUTH_EXCHANGE_FAILED` | code → token exchange ล้มเหลว | LineLoginService |
| `STATE_INVALID` / `STATE_REUSED` / `STATE_EXPIRED` / `STATE_MISMATCH` | state validation ล้มเหลว | PmoisAuthenticationService |
| `CLAIM_TOKEN_INVALID` / `CLAIM_ALREADY_USED` / `LINE_ALREADY_BOUND` | claim flow errors | PmoisAuthenticationService / InvitationService |

### Server-side logging

Production (APP_DEBUG=false): ทุก auth failure ถูก log ผ่าน `error_log()` ที่ฝั่ง server (ไม่ leak ไป client); client เห็นแค่ error code string สั้นๆ ใน URL parameter

### ผลทดสอบ

- **HttpRuntimeTest:** 10 tests, 0 failures ✅
- **Full Suite:** 190 tests, 472 assertions — OK (`TEST-RESULTS-UAT-RUNTIME-FIX-2.txt`)
