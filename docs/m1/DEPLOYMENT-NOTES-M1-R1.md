# PMOIS v2 — Deployment Notes (M1 Revision 1)

## 1. Requirements

- PHP **8.1+** (extensions: pdo_mysql, curl, openssl, json, mbstring)
- MySQL **5.7+ / 8.0** (utf8mb4)
- Composer 2 (production: `composer install --no-dev --optimize-autoloader`)

## 2. Configuration (`.env`)

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pmois
DB_USERNAME=...
DB_PASSWORD=...

LINE_CHANNEL_ID=...          # LINE Login only (CTO Constraint #1)
LINE_CHANNEL_SECRET=...
LINE_REDIRECT_URI=https://<host>/auth/line/callback

APP_SECRET=<random-32+-chars>  # ใช้ sign invitation claim tokens (HMAC) — บังคับตั้งใน production
APP_DEBUG=false
STORAGE_BASE_PATH=/var/www/pmois/storage/uploads
```

> `APP_SECRET` ต้องตั้งใน production — ค่า fallback ในโค้ดใช้สำหรับ dev เท่านั้น
> ไม่มี config สำหรับ IdP อื่น / Git provider อื่น / notification provider (ตาม Constraint) — ไม่ต้องเตรียม

## 3. Migration steps

```bash
# ตรวจสถานะ
php database/migrate.php status
# รัน migrations (0001–0055; 0033–0042 idempotent ถ้าเคยรันแล้ว ให้ข้ามด้วยการเช็ค status)
php database/migrate.php run
```

- Migration `0043`/`0045`/`0047` มี seed + backfill — ต้องมี platform admin user (`users.is_platform_admin = 1`) อยู่ก่อนจึงจะ seed provider rows สำเร็จ หากยังไม่มี ให้สร้าง admin ก่อนรัน 0043
- `0044` backfill `ai_consumers.provider_id` → provider `human` — PMO/CTO ควร reclassify agent จริง (anthropic/openai/...) หลัง deploy ผ่าน `PUT /ai-consumers` flow
- `0046` rename `repositories.gitlab_url` → `repository_url` — ไม่มี data loss; โค้ดใดใช้ชื่อเก่าต้องเปลี่ยนเป็น `repository_url`
- Rollback: `*.rollback.sql` รันย้อนลำดับ 0055→0043 (verified)

## 4. Deploy

1. `composer install --no-dev --optimize-autoloader`
2. Document root → `public/`
3. `storage/uploads` writable
4. Verify: `GET /api/v1/health` → `{"status":"ok"}`

## 5. Smoke test checklist (post-deploy)

1. LINE Login redirect → callback (ด้วย LINE app จริง)
2. Invitation: admin สร้าง → user claim → login สำเร็จ (fail-closed: user ที่ไม่ claim ห้ามเข้า)
3. POST project (CEO fields น้อยที่สุด) → ตรวจ governance adoption + milestones จาก template ถูกสร้าง
4. ลงทะเบียน GitLab repo → ปรากฏใน `/projects/{id}/repositories`
5. สร้าง dependency A depends_on B, แล้วพยายาม B depends_on A → ต้องได้ `DEPENDENCY_CIRCULAR`

## 6. Operational notes

- Notification (Telegram-only เมื่อเริ่มทำในอนาคต) ยังไม่มีใน M1 Phase 1 — ไม่ต้องตั้งค่า
- GitLab integration เป็น manual metadata entry เท่านั้น — ไม่ต้อง provisioning PAT จนกว่าจะเปิด read-only sync
