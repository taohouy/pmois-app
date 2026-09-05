# PMOIS v2 — Operations Guide

## 1. Cron jobs (บังคับตั้งใน production)

```cron
# Automation worker — รัน due jobs ทุกนาที (timeline/project updates, gitlab sync, notifications, ai_dev_auto)
* * * * * php /var/www/pmois/bin/automation-worker.php --limit=20 >> /var/log/pmois/worker.log 2>&1

# เก็บกวาดรายวัน (02:00) — OAuth states/session หมดอายุ
0 2 * * * mysql -u <user> -p<pwd> pmois -e "DELETE FROM oauth_login_states WHERE expires_at < NOW(); DELETE FROM user_sessions WHERE expires_at < NOW();" >> /var/log/pmois/cleanup.log
```

## 2. Health & Monitoring

| Endpoint | ใช้ทำ |
|---|---|
| `GET /api/v1/health` | LB/uptime probe — `status: ok\|degraded` (degraded = DB ล่ม) |
| `GET /api/v1/platform/metrics` | admin — active tokens/sessions, audit today, notifications sent/failed |
| `SELECT status, COUNT(*) FROM notifications GROUP BY status` | ติดตาม Telegram ส่งพลาด |
| `SELECT status, COUNT(*) FROM automation_jobs GROUP BY status` | queue backlog/failed jobs |

แนะนำ alert: notifications.failed เพิ่มขึ้นต่อเนื่อง, automation_jobs.status='failed' > 0, health ≠ ok

## 3. งานประจำ

| งาน | คำสั่ง / ตำแหน่ง |
|---|---|
| สร้าง admin เพิ่ม | `php bin/create-admin.php <email> [name]` |
| ผูก LINE ให้ admin | `php bin/admin-claim-url.php <email> --base-url=...` |
| ตรวจ integrations | `php bin/validate-integrations.php` (รันหลังแก้ env ทุกครั้ง) |
| จัดการ automation | `/app/automation.html` หรือ `/api/v1/automation/*` |
| Backup | `mysqldump --single-transaction pmois > backup_$(date +%F).sql` (รายวัน + เก็บนอกเครื่อง) |

## 4. การจัดการผู้ใช้

- เพิ่มผู้ใช้: admin สร้าง invitation (`POST /api/v1/invitations`) → ส่ง claim URL → ผู้ใช้กดแล้ว login ด้วย LINE
- ระงับผู้ใช้: `UPDATE users SET status='suspended' WHERE id=X` — session/token ถูกปฏิเสธทันทีที่ request ถัดไป (fail-closed)
- ผู้ใช้ถูก suspend แล้ว session ยังอยู่? ไม่ — middleware ตรวจ user.status ทุก request

## 5. Troubleshooting

| อาการ | ตรวจ |
|---|---|
| Login ไม่ผ่าน (STATE_*) | cookie pmois_oauth_fp ถูกบล็อก? SameSite/Secure ตาม HTTPS? |
| notifications failed เยอะ | TELEGRAM_BOT_TOKEN/CHAT_ID ถูกต้อง? network ออก api.telegram.org ได้? |
| queue มี failed ค้าง | `/app/automation.html` → Retry หลังแก้สาเหตุ |
| 429 RATE_LIMITED เยอะ | เพิ่ม RATE_LIMIT_PER_MINUTE หรือ client ยิงผิดปกติ |
| 500 ทั่วไป | `storage/` writable? DB up? `php bin/validate-integrations.php` |
