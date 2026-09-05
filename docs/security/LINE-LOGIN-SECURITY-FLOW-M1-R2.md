# PMOIS v2 — LINE Login Security Flow (M1 Revision 2)

**อ้างอิง:** CTO Review §1 (Critical — LINE Login / Claim Security) • M0 R6 Design Freeze • CTO Constraint #1 (LINE Login only)
**Date:** 2026-09-05

---

## 1. Login Flow (authenticated PMOIS session)

```
1. GET /auth/line
   → generate state  = 64 hex (CSPRNG, 32 bytes)
   → generate nonce  = 32 hex
   → persist oauth_login_states: state_hash=sha256(state), fingerprint_hash=sha256(cookie), nonce, expires_at=+10 min
   → Set-Cookie: pmois_oauth_fp=<fingerprint> (HttpOnly, SameSite=Lax, Secure ใน production)
   → return {auth_url (มี state + nonce), state}

2. User ยืนยันที่ LINE → redirect /auth/line/callback?code=&state=

3. GET /auth/line/callback
   → state verification (fail-closed ทั้งหมด):
       พบ state row (ตาม hash)      → ไม่ใช่ 401 STATE_INVALID
       used_at IS NULL               → ไม่ใช่ 401 STATE_REUSED   (one-time use)
       expires_at > ตอนนี้           → ไม่ใช่ 401 STATE_EXPIRED
       fingerprint ตรงกับ cookie     → ไม่ใช่ 401 STATE_MISMATCH (ผูกกับ browser/session)
   → exchange code → POST https://api.line.me/oauth2/v2.1/token
   → verify id_token → POST https://api.line.me/oauth2/v2.1/verify (LINE ตรวจ signature ด้วย key ของ LINE)
       แล้ว PMOIS ตรวจซ้ำ: iss == https://access.line.me
                           aud == LINE_CHANNEL_ID
                           exp > now, iat <= now + 300s skew
                           nonce == nonce ของ state row
   → mark state used (one-time) — ทำก่อน business logic
   → resolve PMOIS user: users.line_user_id = sub  (ไม่มี → 403 UNAUTHORIZED_IDENTITY — ไม่ auto-create)
   → user.status == 'active'                        (ไม่ใช่ → 403)
   → มี active workspace_members อย่างน้อย 1        (ไม่มี → 403 fail-closed)
   → create user_sessions (session_token_hash = sha256, expires_at = +8h)
   → Set-Cookie: pmois_session (HttpOnly) + เคลียร์ oauth cookie
```

Session cookie ใช้กับ API ได้: `AuthTokenMiddleware` fallback ไปที่ `pmois_session` cookie เมื่อไม่มี Bearer token — ตรวจ hash + expiry + revoked + user active + active membership ทุก request (fail-closed)

## 2. Claim Flow (bind verified LINE identity — client กำหนด line_user_id เองไม่ได้)

```
1. Admin: POST /api/v1/invitations → placeholder user (line_user_id ว่าง) + active membership + claim token (HMAC-signed, 24h)

2. GET /claim/{token}   (guest)
   → validate claim token (HMAC + expiry)
   → persist oauth state purpose='claim' + เก็บ raw claim token ฝั่ง server + fingerprint
   → return {auth_url, state} + Set-Cookie fingerprint

3. LINE Login → /auth/line/callback (state purpose='claim')
   → state verification (เหมือน login ทุกข้อ)
   → verify id_token → verified sub
   → validate claim token ซ้ำ (expiry/tamper)   → CLAIM_TOKEN_INVALID
   → user.line_user_id ว่างเท่านั้น              → CLAIM_ALREADY_USED  (กัน reuse)
   → ไม่มี user อื่นถือ sub นี้                   → LINE_ALREADY_BOUND  (กัน duplicate binding)
   → bind line_user_id = verified sub (ผ่าน InvitationService::processClaim)
   → create PMOIS session (claimed=true)
```

**Protection matrix**

| ภัยคุกคาม | การป้องกัน |
|---|---|
| state missing/unknown | `STATE_INVALID` 401 |
| state replay | one-time `used_at` → `STATE_REUSED` 401 |
| state จาก browser อื่น | fingerprint hash binding → `STATE_MISMATCH` 401 |
| state หมดอายุ | `STATE_EXPIRED` 401 (TTL 10 นาที) |
| id_token ปลอม/แก้ไข | LINE verify endpoint (signature) → `ID_TOKEN_INVALID` |
| id_token ออกให้ app อื่น | aud == channel id |
| id_token หมดอายุ | exp check |
| replay id_token ต่าง flow | nonce binding กับ state |
| LINE account ไม่ได้รับอนุญาต | ไม่มี bound user → 403 (ไม่ auto-create) |
| user suspended / ไม่มี membership | fail-closed 403 |
| client อ้าง line_user_id เอง | **ไม่มี endpoint รับ line_user_id อีกต่อไป** — bind จาก verified sub เท่านั้น (`POST /claim` ถูกลบ) |
| claim token หมดอายุ/ถูกแก้ | HMAC + exp → `CLAIM_TOKEN_INVALID` |
| claim token ถูกใช้ซ้ำ | `CLAIM_ALREADY_USED` |
| LINE account ผูกหลาย account | `LINE_ALREADY_BOUND` |

## 3. Claim token mechanics

- โครงสร้าง: `base64url(json payload) + '.' + base64url(HMAC-SHA256(payload, APP_SECRET))`
- payload: `workspace_id, project_id, user_id, role_code, exp` (24 ชม.)
- `APP_SECRET` **ต้องตั้งใน production** (fallback ในโค้ดสำหรับ dev เท่านั้น)
- หมายเหตุ: เดิมพยายามใช้ lcobucci/jwt แต่ lib ไม่ได้อยู่ใน composer — แทนด้วย HMAC แบบมาตรฐานเดียวกัน (สลับเป็น JWT lib ภายหลังได้โดยไม่กระทบ API)

## 4. Storage tables (migrations 0056–0057)

- `oauth_login_states` — เก็บ state **hash เท่านั้น** + nonce + fingerprint hash + raw claim token (server-side, ใช้ complete claim ตอน callback) + expires_at + used_at
- `user_sessions` — session token **hash เท่านั้น** + user + purpose + ip/ua + expires_at + revoked_at

## 5. Component map

| ส่วน | ไฟล์ |
|---|---|
| State/session store | `MySqlOAuthStateRepository`, `MySqlUserSessionRepository` |
| OAuth + ID token verify | `LineLoginService` (state+nonce ใน auth URL, verify ผ่าน LINE endpoint) |
| Orchestration + fail-closed | `PmoisAuthenticationService` |
| Claim token + binding | `InvitationService` (HMAC, duplicate checks) |
| HTTP endpoints | `LineLoginController`, `ClaimController` |
| Session → API auth | `AuthTokenMiddleware` (cookie fallback) |
