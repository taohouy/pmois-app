# PMOIS v2 — M1 Implementation — Changelog (Revision 2)

**อ้างอิง:** CTO Review Result on Revision 1 (REVISION REQUIRED) • M0 R6 Design Freeze
**Date:** 2026-09-05

---

## 1. Critical — LINE Login / Claim Security (CTO §1)

### 1.1 OAuth State ✅
- ตารางใหม่ `oauth_login_states` (migration 0056) — state เก็บเป็น sha256 hash เท่านั้น
- state = 64-hex CSPRNG, **one-time use** (`used_at`), **ผูกกับ browser** ผ่าน fingerprint cookie (HttpOnly) เก็บ hash, TTL 10 นาที
- callback ตรวจ: exists → not reused → not expired → fingerprint match → แล้ว mark used (fail-closed ทั้งหมด: `STATE_INVALID` / `STATE_REUSED` / `STATE_EXPIRED` / `STATE_MISMATCH`)
- เพิ่ม `nonce` ใน authorization URL และตรวจเทียบใน id_token

### 1.2 ID Token Verification ✅
- ลบการ decode แบบ development-only ออกทั้งหมด
- id_token ถูกส่งไป **LINE verify endpoint** (`POST /oauth2/v2.1/verify` — LINE ตรวจ signature ด้วย key ของ LINE) แล้ว PMOIS ตรวจซ้ำ: `iss` / `aud` (channel id) / `exp` / `iat` (skew 300s) / `nonce`
- จุดนี้พบ latent bug เพิ่มเติมจาก R1: `Psr\Http\Client\ClientInterface` ไม่มีอยู่ใน composer ทำให้ LINE Login เดิม fatal — แก้ด้วย `App\Infrastructure\Http\HttpClientInterface` (PSR-7 shapes, cURL client, ไม่เพิ่ม dependency)

### 1.3 PMOIS Authentication ✅
- ตารางใหม่ `user_sessions` (migration 0057) — session token เก็บ hash เท่านั้น
- หลัง callback สำเร็จ: resolve `line_user_id` → หา bound user → ตรวจ user active → ตรวจ active membership → fail-closed 403 (`UNAUTHORIZED_IDENTITY`) → สร้าง PMOIS session (HttpOnly cookie, 8 ชม.)
- `AuthTokenMiddleware` รองรับ session cookie (fallback จาก Bearer) — ตรวจ session + user + membership ทุก request
- เพิ่ม `POST /auth/logout` (revoke session)

### 1.4 Claim Flow ✅
- **ลบ `POST /claim/{token}` ที่รับ `line_user_id` จาก client ทิ้ง** — ไม่มี endpoint ใดรับ line_user_id อีกต่อไป
- Flow ใหม่: `GET /claim/{token}` (validate token → persist claim state) → LINE Login → callback (verify state + id_token) → bind **verified sub เท่านั้น** → mark claim used (line_user_id ถูกตั้ง = ใช้ซ้ำไม่ได้) → session
- การป้องกัน: expired / tampered (HMAC mismatch) / reused (`CLAIM_ALREADY_USED`) / duplicate LINE binding (`LINE_ALREADY_BOUND`) / claim token invalid
- `InvitationService::processClaim` เพิ่ม duplicate checks ซ้ำอีกชั้น (defense in depth)

## 2. Major — Project Creation Atomicity (CTO §2) ✅

- `ProjectCreationPipeline::create()` ถูกครอบด้วย **database transaction** ทั้ง pipeline (สร้าง project → team ledger + projection → AI assignment → governance binding → milestones → tech stack → API token → default config → completeness persist)
- failure ใดๆ → **ROLLBACK ทั้ง creation** (ใช้ SAVEPOINT เมื่อ caller มี transaction ค้างอยู่ เช่น ใน test)
- แก้เพิ่ม: completeness ตอนนี้ถูก **persist ภายใน transaction** (เดิมคำนวณแล้วทิ้ง) และแก้ bug อ่าน `raw_token` key ผิดจาก `ApiTokenRepository`

## 3. Automated Tests (CTO §3) ✅ — รันจริงบน PHP 8.1 / MySQL 5.7.36

**`tests/Integration/LineAuthenticationTest.php` (18 tests):** valid state persist / missing state / mismatched fingerprint / reused state / expired state / invalid id_token (LINE verify ปฏิเสธ) / expired id_token / wrong audience / nonce mismatch / unauthorized LINE account / suspended user / valid login สร้าง session (hash-only storage) / claim bind จาก verified identity / โครงสร้างไม่มี route รับ client line_user_id / claim reuse / claim expiry / tampered claim token / duplicate LINE binding

**`tests/Integration/ProjectCreationAtomicityTest.php` (6 tests):** success (ทุกตารางครบ + completeness persisted) / failure ตอน milestone creation → rollback / failure ตอน governance bind → rollback / failure ตอน AI assignment → rollback / failure ตอน token+default config → rollback (ไม่เหลือ orphan token) / failure ตอน tech stack → rollback — ทุกเคส assert **ไม่มี partial project / orphan rows**

**Full Suite: 110 tests / 225 assertions — OK** (`docs/m1/TEST-RESULTS-M1-R2.txt`)

## 4. Additional Migration

- `0058_alter_project_ai_assignments_add_workspace_id` — เติม `workspace_id` ให้ครบ convention + backfill จาก projects (repository ของ R6 Phase 1.5 อ่าน column นี้)
- Rollback ของ 0056→0058 ทดสอบแล้ว (rollback → re-apply OK)

## 5. Unchanged (Accepted in Principle ตาม CTO §4)

Migrations 0033–0055, Project Hierarchy, Milestone Foundation, Governance Auto Binding, AI/Git Provider Registry, Team Registry, GitLab-only Repository Registry, Tech Stack, Environment, Dependency, Release Registries, Project Template, Workspace Defaults, Profile Completeness, Permission Seed — ไม่มีการ redesign
