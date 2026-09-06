# PMOIS v2 — M9 Runtime Fix Revision 2 — Handoff Note

**Date:** 2026-09-06
**Commit:** `3175a1a` (pushed to GitLab `pmois-app` master)
**Package:** `PMOIS_v2_M9_RuntimeFix_Revision2.zip` (2.4 MB)

---

## สถานะรวม

| ขั้นตอน | สถานะ | หมายเหตุ |
|---|---|---|
| Test suite | ✅ **190 tests, 472 assertions, 0 failures** | `TEST-RESULTS-UAT-RUNTIME-FIX-2.txt` |
| Changelog | ✅ Updated | Section 6 added to `CHANGELOG-M9-Revision1.md` |
| Git commit | ✅ `3175a1a` | Push complete |
| Zip package | ✅ Created | `PMOIS_v2_M9_RuntimeFix_Revision2.zip` |
| Temp MySQL | ✅ Stopped & cleaned | `.tmp-mysql/data` removed |

---

## ปัญหาที่แก้ (CTO Conditional Pass — 2 Defects)

### Issue 1: Successful Login Callback → `AUTH_FAILED` แทน Dashboard
**สาเหตุ:** Controller ใช้ `safeErrorCode()` parse error code จาก exception message string → catch-all `\Throwable` กลืน error ทุกอย่างเป็น `AUTH_FAILED`

### Issue 2: Unauthorized Identity → แสดง `AUTH_FAILED` แทน `UNAUTHORIZED_IDENTITY`
**สาเหตุ:** `\DomainException` / `\InvalidArgumentException` ไม่มี error code property; Controller ต้อง string-match message

---

## แนวทางแก้: AuthException พร้อม `errorCode` Property

```php
// src/Domain/Auth/AuthException.php
final class AuthException extends \RuntimeException {
    public function __construct(
        public readonly string $errorCode,  // <-- แยกออกจาก message
        string $detail = ''
    ) { parent::__construct($detail !== '' ? $detail : $errorCode); }
}
```

Controller อ่าน `$e->errorCode` โดยตรง — ไม่ต้อง parse string:

```php
// LineLoginController::callback()
} catch (AuthException $e) {
    return $response->withHeader('Location', "/app/index.html?error={$e->errorCode}")->withStatus(302);
} catch (\Throwable $e) {
    error_log('[AUTH] ' . $e->getMessage());  // server-side log
    return $response->withHeader('Location', '/app/index.html?error=AUTH_FAILED')->withStatus(302);
}
```

---

## Error Code แยกชัดเจน

| Code | ความหมาย | ที่เกิด |
|---|---|---|
| `AUTH_FAILED` | Catch-all (login failed / unexpected) | Controller catch `\Throwable` |
| `UNAUTHORIZED_IDENTITY` | Login สำเร็จแต่ไม่มี permission | PmoisAuthenticationService |
| `ID_TOKEN_INVALID` | ID token verify ไม่ผ่าน | LineLoginService |
| `OAUTH_EXCHANGE_FAILED` | code→token exchange ล้มเหลว | LineLoginService |
| `STATE_INVALID` / `STATE_REUSED` / `STATE_EXPIRED` / `STATE_MISMATCH` | state validation | PmoisAuthenticationService |
| `CLAIM_TOKEN_INVALID` / `CLAIM_ALREADY_USED` / `LINE_ALREADY_BOUND` | claim flow | PmoisAuthenticationService / InvitationService |

---

## ไฟล์ที่เปลี่ยน (18 ไฟล์)

### ใหม่
- `src/Domain/Auth/AuthException.php`
- `docs/m9/TEST-RESULTS-UAT-RUNTIME-FIX-2.txt`

### แก้ไข
| ไฟล์ | เปลี่ยนหลัก |
|---|---|
| `LineLoginService.php` | `\InvalidArgumentException` → `AuthException('ID_TOKEN_INVALID')`; `\RuntimeException('OAUTH_EXCHANGE_FAILED')` → `AuthException('OAUTH_EXCHANGE_FAILED')` |
| `PmoisAuthenticationService.php` | `\DomainException` / `\InvalidArgumentException` → `AuthException` ทุกจุด (8 error codes) |
| `InvitationService.php` | throws ทั้งหมด → `AuthException` |
| `LineLoginController.php` | `callback()`/`apiCallback()` catch `AuthException` → `$e->errorCode`; ลบ `safeErrorCode()`; `errorMessage()`/`errorStatus()` รับ errorCode string; `redirect()` → `redirectTo()` (private) |
| `ClaimController.php` | `start()` catch `AuthException` → 302 with `$e->errorCode` |
| `HttpRuntimeTest.php` | เพิ่ม `POST /auth/logout` route; `testSessionPersistsAcrossRequests`; `testLogoutRevokesSession`; `testIdTokenInvalidKeepsDistinctErrorCode`; timezone-safe seed (`DATE_ADD NOW 8h`) |
| `LineAuthenticationTest.php` | `expectException` → `\App\Domain\Auth\AuthException`; catch block → `$e->errorCode` assertion |

---

## Tests เพิ่มใน HttpRuntimeTest

| Test | ตรวจสอบ |
|---|---|
| `testSessionPersistsAcrossRequests` | Session cookie valid หลาย request ติดกัน |
| `testLogoutRevokesSession` | `POST /auth/logout` → 302, session revoked → root redirect `/auth/line` |
| `testIdTokenInvalidKeepsDistinctErrorCode` | LINE verify error → `ID_TOKEN_INVALID` (ไม่ใช่ `AUTH_FAILED`) |

---

## ยังไม่เสร็จ — งานที่ต้องส่งต่อให้ dev ถัดไป

### 1. UI Screenshots / Video Demo (CTO Required)
CTO กำหนด: **screenshots 7 หน้า** (Login, Dashboard, Project List, Project Detail, Analytics, Automation, Settings) **หรือ 3-5 นาที video demo**

**วิธีทำ:**
```bash
# 1. Start built-in PHP server
php -S 0.0.0.0:8080 -t public

# 2. Seed test session (run in separate terminal)
php -r "
require 'vendor/autoload.php';
\$db = new PDO('mysql:host=127.0.0.1;port=3307;dbname=pmois_test', 'root', '');
\$stmt = \$db->prepare('INSERT INTO user_sessions (id, user_id, created_at, expires_at) VALUES (:id, 1, NOW(), DATE_ADD(NOW(), INTERVAL 8 HOUR))');
\$stmt->execute(['id' => 'test-session-' . bin2hex(random_bytes(16))]);
echo 'Session ID: ' . \$stmt->execute(['id' => \$id]) . PHP_EOL;
"
# Copy session ID, set as cookie 'pmois_session' in browser

# 3. Use browser automation (Playwright/Puppeteer) or manual capture
```

**หมายเหตุ:** ต้องมี MySQL instance วิ่งที่ port 3307 ก่อน (`D:\wamp64\bin\mysql\mysql5.7.36\bin\mysqld --defaults-file=D:\wamp64\bin\mysql\mysql5.7.36\my.ini`)

### 2. Production Deployment Gate (CEO UAT)
- ยังไม่ได้ Tag Release / Production Baseline / Production Deployment
- รอ CEO UAT ผ่าน → จึงทำต่อ

---

## วิธีรัน Test Suite อีกครั้ง (ถ้าต้องการ)

```bash
cd D:\Projects\pmois-app

# Start temp MySQL (port 3307) - ใช้ mysqld standalone หรือ WAMP
# D:\wamp64\bin\mysql\mysql5.7.36\bin\mysqld --defaults-file=D:\wamp64\bin\mysql\mysql5.7.36\my.ini --port=3307 &

# Recreate test DB from installer
mysql -h 127.0.0.1 -P 3307 -u root -e "DROP DATABASE IF EXISTS pmois_test; CREATE DATABASE pmois_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -h 127.0.0.1 -P 3307 -u root pmois_test < deploy/PMOIS_v2_Database_Install.sql

# Run tests
export TEST_DB_DSN="mysql:host=127.0.0.1;port=3307;dbname=pmois_test;charset=utf8mb4"
export TEST_DB_USER=root
export TEST_DB_PASS=
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php tests
```

---

## ข้อสังเกตสำหรับ Dev ถัดไป

1. **AuthException namespace:** อยู่ใน `App\Domain\Auth` เดียวกับ service classes → ไม่ต้อง `use` import ภายใน namespace เดียวกัน; แต่ **test files** ต้องใช้ FQCN: `\App\Domain\Auth\AuthException::class`

2. **Server-side logging:** Production (APP_DEBUG=false) ทุก auth failure log ผ่าน `error_log()` — ต้องตรวจสอบ PHP error log path ใน production (php.ini `error_log`)

3. **Timezone-safe seed:** HttpRuntimeTest ใช้ `DATE_ADD(NOW(), INTERVAL 8 HOUR)` สำหรับ expires_at เพราะ MySQL temp instance อาจ timezone ต่างจาก PHP

4. **Vendor tracked:** Repo commit vendor (composer dump-autoload -o output) — ไม่ต้อง `composer install` บน CI/CD

5. **Route split:** Web routes (302) และ API routes (JSON) แยกใน `Config/routes.php` — อย่าผสม

---

## Next Actions Checklist

- [ ] Capture 7 screenshots / 3-5 min video demo
- [ ] Send screenshots/video to CTO
- [ ] Wait for CEO UAT pass
- [ ] Tag Release / Production Baseline
- [ ] Production Deployment