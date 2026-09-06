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

---

## 7. UAT Runtime Fix Revision 3 (2026-09-06 — STATE_MISMATCH during CEO UAT)

### ปัญหาที่พบระหว่าง CEO UAT

Flow: Claim URL → redirect ไป LINE → LINE Login สำเร็จ → callback กลับ PMOIS → redirect ไป `/app/index.html?error=STATE_MISMATCH`

**สาเหตุ:** Cookie `pmois_oauth_fp` ไม่ survive cross-domain round-trip:
- PMOIS → `access.line.me` → PMOIS callback
- Cookie ที่สร้างโดย `cookieFlags()` ไม่มี `Domain` attribute → browser ส่ง cookie กลับเฉพาะเมื่อ host ตรงทุกด้าน
- ใน production (`https://pmo.jaideedigital.com`) ถ้า redirect URI หรือ reverse proxy เปลี่ยน host/port/path แม้เล็กน้อย cookie จะหาย → fingerprint ว่าง → `hash_equals('', hash('sha256', $fingerprint))` → `STATE_MISMATCH`
- ในทางตรงกันข้าม `SameSite=Lax` อนุญาตให้ cookie ส่งได้ใน top-level redirect (เหมาะสำหรับ OAuth) แต่ถ้าไม่มี `Domain` attribute browser อาจไม่ส่ง cookie ข้าม subdomain

### แก้ไข

| ไฟล์ | การเปลี่ยน |
|---|---|
| `src/Application/Http/Controllers/LineLoginController.php` | `cookieFlags()` เพิ่ม `Domain` attribute จาก env `OAUTH_COOKIE_DOMAIN` (ว่าง = ไม่ใส่ สำหรับ dev/localhost) |
| `deploy/.env.production.example` | เพิ่ม `OAUTH_COOKIE_DOMAIN=pmo.jaideedigital.com` + `LINE_REDIRECT_URI` แก้เป็นโดเมนจริง + คำอธิบายว่าทำไมจำเป็น |
| `tests/Integration/HttpRuntimeTest.php` | เพิ่ม 2 เคส: `testNormalLoginFullOAuthRoundTripStatePersists` + `testClaimFlowFullOAuthRoundTripStatePersists` — ทดสอบ state และ fingerprint cookie ทั้ง redirect และ callback |

### สิ่งที่ตรวจสอบและยืนยันว่าถูกต้องแล้ว (ไม่ต้องแก้)

1. **State Generation** — `bin2hex(random_bytes(32))` cryptographically secure, unique ต่อ login attempt ✅
2. **State Persistence** — เก็บใน `oauth_login_states` DB table เป็น `sha256` hash ก่อน redirect ไป LINE (`beginLogin` / `beginClaim`) ✅
3. **State validation** — `findByStateHash` + `hash_equals` timing-safe + one-time use (`markUsed`) ✅
4. **Claim flow ใช้ mechanism เดียวกับ normal login** — `beginClaim` สร้าง state ผ่าน `stateRepository->create` เหมือน `beginLogin` (purpose='claim') callback ผ่าน `completeCallback` ที่เดียว ✅
5. **`SameSite=Lax`** — เหมาะสำหรับ OAuth top-level redirect (อนุญาต cookie ข้าม site ใน redirect) ✅
6. **`HttpOnly`** — cookie ไม่ถูก JS อ่าน ✅
7. **`Secure`** — HTTPS-only ใน production (APP_DEBUG=false) ✅
8. **`Max-Age=600`** — oauth fingerprint cookie หมดอายุใน 10 นาที (พอสำหรับ OAuth round-trip) ✅

### สิ่งที่ต้องตั้งค่าใน production

```env
# .env (production)
OAUTH_COOKIE_DOMAIN=pmo.jaideedigital.com
LINE_REDIRECT_URI=https://pmo.jaideedigital.com/auth/line/callback
```

ถ้าไม่ตั้ง `OAUTH_COOKIE_DOMAIN` ใน production → cookie จะไม่มี Domain attribute → browser อาจไม่ส่ง cookie กลับหลัง redirect จาก LINE → `STATE_MISMATCH`

### ผลทดสอบ

- **HttpRuntimeTest:** 12 tests, 0 failures ✅ (เพิ่ม 2 เคส OAuth round-trip)
- **Full Suite:** 192 tests, 490 assertions — OK (`TEST-RESULTS-UAT-RUNTIME-FIX-3.txt`)

---

## 8. UAT Runtime Fix Revision 4 (2026-09-06 — STATE_MISMATCH ยังพบหลัง deploy Rev 3 + RFC 7230 error)

### Root Cause ที่พบ (จาก Runtime Evidence ของ CTO)

Nginx/PHP log: `Header name must be an RFC 7230 compatible string` — มาจาก `Slim\Psr7\Headers::validateHeaderName()` ซึ่งถูกเรียกจาก **`CurlHttpClient::sendRequest()`**

**บั๊กเดิมใน `src/Infrastructure/Http/CurlHttpClient.php`:**

```php
// เดิม: ตั้ง CURLOPT_RETURNTRANSFER=true → curl_exec() คืน "body เท่านั้น"
$rawBody = curl_exec($ch);
// แต่โค้ดคิดว่าคืน "headers + body" แล้วตัดเอา headerSize ไบต์แรกของ BODY มา parse เป็น header
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr((string) $rawBody, 0, $headerSize);   // ← ตัดจาก BODY ไม่ใช่ HEADERS!
```

**ผลใน production:** LINE token endpoint ตอบ JSON `{"access_token":"..."}`
→ ไบต์แรก ~200 ไบต์ของ JSON ถูก parse เป็น header → ชื่อ header กลายเป็น `{"access_token"`
→ `withAddedHeader()` โยน **"Header name must be an RFC 7230 compatible string"** ทุกครั้ง
→ `exchangeCodeForTokens()` ล้มเหลว → callback จบที่ error (catch-all `\Throwable`)
→ **ทุก login ล้มเหลวใน production**

**ทำไม unit test ไม่จับ:** tests ทั้งหมดใช้ fake HTTP client (ไม่ได้รัน cURL จริง) — แก้แล้วโดยเพิ่ม `CurlHttpClientTest` ที่รันผ่าน **HTTP จริง** (local server child process + cURL จริง)

### สิ่งที่แก้

| ไฟล์ | การเปลี่ยน |
|---|---|
| `src/Infrastructure/Http/CurlHttpClient.php` | **แก้ root cause**: ใช้ `CURLOPT_HEADERFUNCTION` จับ header จริงทีละบรรทัด (skip status line รวม interim 100-continue); `parseHeaderLine()` validate ชื่อ header ตาม RFC 7230 token charset ก่อนใส่ response — บรรทัดผิดรูปแบบถูก **ข้าม ไม่ throw**; body จาก curl_exec โดยตรง |
| `src/Application/Http/Controllers/ClaimController.php` | แก้บั๊ก error path: เดิมเรียก `redirect($response, $string)` ซึ่งเป็น route handler (TypeError บน strict_types) → เปลี่ยนเป็น `withHeader('Location', ...)` ตรง ๆ + diagnostic log |
| `src/Application/Http/Controllers/LineLoginController.php` | Diagnostic log ทั้ง `redirect` / `callback` / `apiCallback`: state hash prefix, fingerprint cookie present, cookie domain/secure/samesite (ห้าม log token/secret/raw fingerprint) |
| `src/Domain/Auth/PmoisAuthenticationService.php` | Diagnostic log ใน `completeCallback`: state found, purpose, used_at, expires_at, fingerprint match (ห้าม log raw fingerprint) |
| `bin/diagnose-auth.php` | **ใหม่** — runtime diagnostic script: ทดสอบ full OAuth round-trip (begin → persist → callback → session), mismatch/empty fingerprint ต้องถูกปฏิเสธ, วิเคราะห์ cookie flags |
| `tests/Integration/CurlHttpClientTest.php` | **ใหม่** — 8 เคสรันผ่าน HTTP จริง (child-process server): JSON response parse ถูกต้อง (เคสที่เคยพัง), status line ไม่กลายเป็น header, JSON body line ถูก skip, multiple Set-Cookie คงอยู่, interim 100-continue ไม่พัง, POST body ส่งครบ |

### Diagnostic Logging Format (production-safe)

```
[PMOIS auth DIAG] claim/start: claim_token_present=yes fingerprint_generated=yes
[PMOIS auth DIAG] claim/start: redirect to LINE auth_url=... set_cookie_count=1
[PMOIS auth DIAG] callback: state_present=yes state_hash_prefix=xxxxxxxx code_present=yes fingerprint_cookie_present=yes
[PMOIS auth DIAG] completeCallback: state_found=yes purpose=claim used_at=no expires_at=... fingerprint_match=yes
[PMOIS auth DIAG] callback: SUCCESS purpose=claim user_id=...
```

**ห้าม log:** access token, id_token, APP_SECRET, raw claim token, raw fingerprint — ทุก log ใช้ hash prefix / yes-no เท่านั้น

### Runtime Evidence (จาก `bin/diagnose-auth.php` — `RUNTIME-DIAGNOSTIC-REV4.txt`)

```
1. beginLogin        → state generated + auth_url มี state/nonce param       ✅
2. State persisted   → DB found, purpose=login, used_at=no, fp match         ✅
3. completeCallback  → SUCCESS, session_token 64 chars                       ✅
4. State marked used → used_at=yes (one-time)                                ✅
Mismatched fingerprint → ถูกปฏิเสธ STATE_MISMATCH                             ✅
Empty fingerprint (cookie หาย) → ถูกปฏิเสธ STATE_MISMATCH                     ✅
```

### หมายเหตุ Production Deployment

1. Deploy โค้ด Revision 4 นี้ — token exchange จะทำงานถูกต้อง
2. ตั้งค่าใน `.env` (ยังไม่มีใน production ปัจจุบัน):
   ```env
   OAUTH_COOKIE_DOMAIN=pmo.jaideedigital.com
   LINE_CHANNEL_ID=<channel id>
   LINE_CHANNEL_SECRET=<channel secret>
   LINE_REDIRECT_URI=https://pmo.jaideedigital.com/auth/line/callback
   APP_DEBUG=false
   ```
3. หลัง deploy ให้รัน `php bin/diagnose-auth.php` เพื่อยืนยัน round-trip บน server จริง
4. ตรวจ PHP error log หา `[PMOIS auth DIAG]` — fingerprint_cookie_present=yes หลัง callback แสดงว่า cookie survive round-trip

### ผลทดสอบ

- **CurlHttpClientTest:** 8 tests, 33 assertions — OK (รันผ่าน HTTP จริง)
- **Full Suite:** 200 tests, 523 assertions — OK (`TEST-RESULTS-UAT-RUNTIME-FIX-4.txt`)
