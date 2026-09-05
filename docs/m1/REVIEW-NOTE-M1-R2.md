# PMOIS v2 — Review Note (M1 Implementation Revision 2)

**สำหรับ:** CTO Review Gate — ขออนุมัติเพื่อเริ่ม Phase 2
**อ้างอิง:** CTO Review Result on Revision 1 (REVISION REQUIRED — §1 LINE Security, §2 Atomic Creation, §3 Tests)

---

## 1. สรุปการแก้ตามมติ CTO

| ประเด็น CTO | การแก้ | หลักฐาน |
|---|---|---|
| §1.1 OAuth State | state hash-only, one-time, fingerprint-bound, TTL 10 นาที, ตรวจครบ 4 เงื่อนไขที่ callback | `oauth_login_states` (0056), `PmoisAuthenticationService` |
| §1.2 ID Token | verify ผ่าน LINE endpoint + iss/aud/exp/iat/nonce; ลบ dev-only decode | `LineLoginService::verifyIdToken` |
| §1.3 PMOIS Auth | resolve user → active check → membership check → fail-closed → PMOIS session (hash-only, HttpOnly cookie, 8h) + session auth ใน AuthTokenMiddleware + logout | `user_sessions` (0057), `AuthTokenMiddleware` |
| §1.4 Claim Flow | ลบ `POST /claim` (client-supplied line_user_id); bind เฉพาะ verified sub; กัน reuse/duplicate/tamper/expiry | `PmoisAuthenticationService::completeClaim`, `InvitationService` |
| §2 Atomic Creation | transaction ครอบทั้ง pipeline (+SAVEPOINT เมื่อซ้อนใน transaction ที่มีอยู่); completeness persist ใน transaction; แก้ `raw_token` key bug | `ProjectCreationPipeline` |
| §3 Tests | **110 tests / 225 assertions — OK**; LINE auth 18 tests, atomicity 6 tests (จำลอง failure กลาง pipeline ด้วยข้อจำกัดจริงของ DB) | `docs/m1/TEST-RESULTS-M1-R2.txt` |

## 2. จุดที่ CTO อาจตรวจเพิ่ม

1. **Claim token ใช้ HMAC-SHA256 แทน JWT lib** — เดิม R1 พยายามใช้ lcobucci/jwt แต่ไม่มีใน composer (เป็นสาเหตุที่ service เดิม fatal) การ sign/expiry ใช้หลักการเดียวกัน; ถ้า CTO ต้องการ JWT format มาตรฐาน ให้ `composer require lcobucci/jwt` แล้ว swap เฉพาะ encode/decode ของ `InvitationService` (public API ไม่เปลี่ยน) — **ต้องตั้ง `APP_SECRET` ใน production**
2. **`App\Infrastructure\Http\HttpClientInterface`** — ประกาศเองแทน `Psr\Http\Client\ClientInterface` เพราะ psr/http-client ไม่ได้อยู่ใน composer ของโปรเจกต์ (เจอจากการเขียน test — เป็น latent bug อีกตัวของ R1) รูปทรงเหมือนกัน (sendRequest) ใช้ PSR-7 message types
3. **Migration 0058** — เติม `workspace_id` ให้ `project_ai_assignments` ตาม convention (ตารางเดิมจาก 0040 ไม่มี — เจอจาก test) พร้อม backfill; rollback ทดสอบแล้ว
4. **Fingerprint cookie** — ผูก state กับ browser ด้วย random cookie per attempt (เก็บ hash ฝั่ง server) ครอบคลุม "ผูก state กับ browser/session" โดยยังไม่ต้องมี session infra ก่อน login
5. **Session ครอบ Web UI** — `AuthTokenMiddleware` รับ `pmois_session` cookie เป็น auth ทางเลือก (workspace resolve จาก active membership แรก — เมื่อมีหลาย workspace และต้องเลือกได้ จะเพิ่ม workspace switch ใน Phase ของ UI)

## 3. Migration Gate (รันจริง)

- apply 0001→0058 ✅ (MySQL 5.7.36)
- rollback 0058→0056 → re-apply ✅
- `php -l` ผ่านทุกไฟล์ src/tests

## 4. Scope ที่ยังไม่ทำ (ไม่ใช่การละเมิด — ตามแผน)

- Revision/Review/Commit workflow — Phase 2
- Notification (Telegram) — ยังไม่เริ่ม
- Web UI — ค้างที่ Q09

## 5. Gate

ขอมติ **APPROVED เพื่อเริ่ม Phase 2** (Revision workflow + hardening ต่อ) หาก Review ผ่าน
