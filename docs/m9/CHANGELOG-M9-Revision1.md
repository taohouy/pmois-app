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
