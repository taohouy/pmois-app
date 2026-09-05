# PMOIS v2 — Deployment Notes (M1 Revision 2)

> เพิ่มเติมจาก Revision 1 (`DEPLOYMENT-NOTES-M1-R1.md`) — หัวข้อนี้คือ delta ด้าน security ที่ต้องตั้งค่า

## 1. สิ่งที่เพิ่มใน Revision 2

- Migrations ใหม่: `0056_create_oauth_login_states`, `0057_create_user_sessions`, `0058_alter_project_ai_assignments_add_workspace_id` (รวมถึง 0043–0055 จาก R1)
- ตาราง auth: `oauth_login_states`, `user_sessions`

## 2. Environment (ต้องตั้งใน production)

```env
APP_SECRET=<random-32+-chars>   # ⚠️ บังคับ — ใช้ sign invitation claim tokens (HMAC-SHA256)
LINE_CHANNEL_ID=...             # LINE Login only
LINE_CHANNEL_SECRET=...
LINE_REDIRECT_URI=https://<host>/auth/line/callback
APP_DEBUG=false                 # false = cookie มี Secure flag (ต้องใช้ HTTPS)
```

> ถ้าไม่ตั้ง `APP_SECRET` ระบบจะใช้ fallback สำหรับ dev — **ห้าม** ใช้ใน production เพราะ claim token ปลอมได้

## 3. Cookie requirements

- `pmois_session` (HttpOnly, SameSite=Lax, Secure เมื่อ APP_DEBUG=false) — PMOIS session หลัง LINE Login (8 ชม.)
- `pmois_oauth_fp` — OAuth state fingerprint (TTL 10 นาที)
- HTTPS บังคับใน production (เพราะ Secure flag + LINE redirect)

## 4. Post-deploy smoke test (security-focused)

1. `GET /auth/line` → ได้ `auth_url` + cookie `pmois_oauth_fp`; ตรวจว่า `oauth_login_states` มีแถวใหม่ (state เก็บเป็น hash)
2. Login สำเร็จ → cookie `pmois_session`; เรียก `GET /api/v1/projects` **โดยไม่ส่ง Bearer** (ใช้ cookie) → ต้องได้ข้อมูล
3. ยิง callback ซ้ำด้วย state เดิม → ต้องได้ `401 STATE_REUSED`
4. ยิง callback โดยไม่มี/เปลี่ยน cookie fingerprint → `401 STATE_MISMATCH`
5. เรียก `POST /claim/{token}` → ต้องได้ 404/405 (route ถูกลบ — binding ทำที่ callback เท่านั้น)
6. Claim token ที่ expire แล้ว → `GET /claim/{token}` → `400 CLAIM_TOKEN_INVALID`

## 5. Operational

- ล้าง state/session เก่า: `DELETE FROM oauth_login_states WHERE expires_at < NOW();` และ `DELETE FROM user_sessions WHERE expires_at < NOW();` (cron รายวัน — แนะนำ)
- ผู้ใช้ที่ถูก suspend จะถูกตัด session ทันทีที่ request ถัดไป (middleware ตรวจ user.status ทุกครั้ง)
