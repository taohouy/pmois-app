# PMOIS v2 — Security Review (M9)

**ขอบเขต:** ทบทวนมาตรการความปลอดภัยทั้งหมดที่ implement แล้ว (M1 R2 + M5) ก่อนเข้า UAT
**Date:** 2026-09-06

---

## 1. Authentication

| มาตรการ | สถานะ | อ้างอิง |
|---|---|---|
| LINE Login only (ไม่มี IdP อื่น) | ✅ | CTO Constraint #1 |
| OAuth state: CSPRNG, persisted hash-only, one-time, fingerprint-bound, TTL 10 นาที | ✅ (test 13 เคส) | `oauth_login_states`, LineAuthenticationTest |
| ID token verify ผ่าน LINE endpoint + iss/aud/exp/iat/nonce | ✅ | `LineLoginService::verifyIdToken` |
| Fail-closed: ไม่มี bound user / suspended / ไม่มี membership → ปฏิเสธ | ✅ (test) | `PmoisAuthenticationService` |
| Claim flow: bind จาก verified sub เท่านั้น; กัน reuse/duplicate/tamper/expiry | ✅ (test 6 เคส) | `POST /claim` ถูกลบ — ไม่มี endpoint รับ line_user_id |
| Session: hash-only storage, HttpOnly cookie, TTL 8h, revoke ได้, ตรวจ user.status ทุก request | ✅ | `user_sessions`, AuthTokenMiddleware |

## 2. Authorization

- `PermissionResolver`: project override → workspace → role_permissions; fail-closed (ไม่มีสมาชิก = ไม่มีสิทธิ์)
- Workspace isolation ทุก query (BaseRepository + assertWorkspaceMatch; ตรวจด้วย test หลายชุด)
- Cross-workspace resource access → 404 (ไม่บอกข้อมูลว่ามีอยู่)
- Global registries / portfolio / metrics → is_platform_admin

## 3. Secrets & tokens

- API token / session / OAuth state / automation — เก็บ **hash เท่านั้น** (raw ไม่เคยถูกเก็บ)
- Claim token: HMAC-SHA256 + APP_SECRET (24h) — **APP_SECRET บังคับตั้งใน production**
- Repository/environment credentials: เก็บ pointer (`credential_reference`) เท่านั้น — ไม่มี secret ใน DB
- raw API token แสดงครั้งเดียวตอนสร้าง; ห้ามเก็บ clear text ทุกกรณี

## 4. Input handling

- Prepared statements ทุก query (ไม่มี string interpolation ของค่า input)
- output escaping ใน Web UI (`esc()` ทุกจุดแสดงผล)
- Environment registry reject secret-like fields; Telegram/notification best-effort ไม่ expose error แก่ client

## 5. API hardening (M5)

- Rate limiting ต่อ identity (default 120/min) + 429 + headers
- Scope management: legacy token ผ่านเหมือนเดิม; token ที่ประกาscopes ถูกจำกัด (read/write/ai_context)
- AI token access จำกัดด้วย AiAccessControlMiddleware (allowlist เดิม)

## 6. ความเสี่ยงคงเหลือ (ยอมรับ/เฝ้าดู)

| ความเสี่ยง | การยอมรับ |
|---|---|
| Rate limiter in-memory per worker | single-node พอ; ต้อง shared store เมื่อ scale หลาย node |
| HTTPS บังคับ | ตั้งผ่าน APP_DEBUG=false (Secure cookies) — ต้อง terminate TLS ที่ LB |
| Session ไม่ rotate ทุก request | TTL 8h + revoke; ต่ำกว่าเกณฑ์ความเสี่ยงระบบภายใน |

## 7. สรุป

ไม่พบ blocker — พร้อมเข้า UAT ภายใต้เงื่อนไข: APP_SECRET ตั้งค่า, HTTPS, รัน validate-integrations ผ่านทั้งหมดใน production network
