# PMOIS v2 — Installation Guide (Production / UAT)

**สิ่งที่ต้องมี:** PHP 8.1+ (pdo_mysql, curl, openssl, mbstring), MySQL 5.7+/8.0, Composer (ครั้งเดียวตอน build)

---

## 1. ติดตั้งโค้ด

```bash
unzip PMOIS_v2_M9_Implementation_Revision1.zip -d /var/www/pmois
cd /var/www/pmois
composer install --no-dev --optimize-autoloader
```

## 2. ติดตั้งฐานข้อมูล (ไฟล์เดียว — ห้ามรัน migration ทีละไฟล์)

```sql
CREATE DATABASE pmois CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -h <host> -u <user> -p pmois < deploy/PMOIS_v2_Database_Install.sql
```

Installer รวมทุกอย่าง: 45 ตาราง + seed data (roles, permissions, AI providers, Git provider = GitLab, automation permissions) + บัญชี admin เริ่มต้น (`admin@pmois.local`, is_platform_admin=1)

## 3. ตั้งค่า Environment

```bash
cp deploy/.env.production.example .env
```

แก้ค่าที่จำเป็น (ดูรายการทั้งหมดในไฟล์ตัวอย่าง): `DB_*`, `APP_SECRET` (**บังคับ** — สุ่ม ≥32 ตัวอักษร), `LINE_CHANNEL_ID/SECRET/REDIRECT_URI`, `TELEGRAM_BOT_TOKEN/CHAT_ID` (ถ้าตั้งค่า), `APP_DEBUG=false`

## 4. สร้าง admin + ผูก LINE

```bash
# admin คนแรกถูกสร้างโดย installer แล้ว (admin@pmois.local) — สร้างเพิ่มได้:
php bin/create-admin.php admin@pmois.local "PMOIS Admin"

# ผูก LINE account กับ admin (one-time claim, อายุ 24 ชม.)
php bin/admin-claim-url.php admin@pmois.local --base-url=https://<host>
# เปิด URL ที่ได้ → Login with LINE → ผูกเรียบร้อย
```

## 5. ตรวจสอบความพร้อม (Integration Validation)

```bash
php bin/validate-integrations.php   # ต้องได้ ALL CHECKS PASSED
```

ตรวจ: PHP/extensions, DB, APP_SECRET, LINE config + connectivity, GitLab connectivity,
Telegram getMe (ถ้าตั้งค่า), storage writable, automation tables

## 6. Web server

- Document root → `public/`
- ตัวอย่าง cron ที่ต้องตั้ง (ดู Operations Guide):
  - `* * * * * php /var/www/pmois/bin/automation-worker.php --limit=20`
- Smoke test: `GET /api/v1/health` → `{"status":"ok","checks":{"database":true},...}`

## 7. UAT

- ผู้รับการทดสอบเข้าผ่าน LINE Login (admin สร้าง invitation ให้ก่อน)
- สถานการณ์แนะนำ: สร้าง project (template) → submit revision → CTO review → commit →
  release → deployment → ตรวจ dashboard/analytics/timeline/notifications
