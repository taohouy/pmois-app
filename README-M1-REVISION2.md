# PMOIS v2 — M1 Implementation Revision 2

**ตอบ CTO Review Result (REVISION REQUIRED)** — แก้ Critical §1 (LINE Login/Claim Security) และ Major §2 (Project Creation Atomicity) + §3 Automated Tests ครบ

## Package map
- `src/`, `database/`, `tests/`, `public/`, `composer.json` — source (vendor ไม่รวม: `composer install`)
- `docs/m1/CHANGELOG-M1-Revision2.md` — Changelog ตอบรายข้อ
- `docs/m1/REVIEW-NOTE-M1-R2.md` — Review Note (สิ่งที่แก้ + จุดให้ CTO ตรวจ)
- `docs/m1/TEST-RESULTS-M1-R2.txt` — Test Results: **110 tests / 225 assertions — OK**
- `docs/security/LINE-LOGIN-SECURITY-FLOW-M1-R2.md` — Updated Security / LINE Login Flow
- `docs/api/M1-API-Documentation.md` — Updated API Documentation (auth section R2)
- `docs/m1/DEPLOYMENT-NOTES-M1-R2.md` — Deployment delta (APP_SECRET บังคับ, cookie, smoke test)
- `docs/m1/M1-Implementation-Plan-Revision4.md` — Plan (R6 baseline)

## สรุปการแก้
1. §1.1 OAuth state — persisted hash-only, one-time, fingerprint-bound, TTL 10 min, nonce
2. §1.2 ID token — LINE verify endpoint + iss/aud/exp/iat/nonce (dev-only decode ถูกลบ)
3. §1.3 PMOIS auth — fail-closed resolve + user_sessions (hash-only) + session cookie auth + logout
4. §1.4 Claim — ลบ POST /claim (client-supplied line_user_id); bind เฉพาะ verified sub; กัน reuse/duplicate/tamper/expiry
5. §2 Creation pipeline — atomic transaction (SAVEPOINT เมื่อซ้อน), completeness persist, แก้ raw_token bug
6. §3 Tests — 18 LINE auth tests + 6 atomicity tests (rollback กลาง pipeline ทุกจุด)

## Migration เพิ่ม
0056 oauth_login_states • 0057 user_sessions • 0058 project_ai_assignments.workspace_id (rollback ทดสอบแล้ว)

## Quick verify
```
composer install
php database/migrate.php run
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
```
