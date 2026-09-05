# PMOIS v2 — Rollback Guide

## 1. Database rollback (ถอน installation ทั้งหมด)

⚠️ **Destructive** — ต้อง backup ก่อนทุกครั้ง:

```bash
mysqldump -h <host> -u <user> -p --single-transaction pmois > backup_$(date +%F).sql
mysql -h <host> -u <user> -p pmois < deploy/PMOIS_v2_Database_Rollback.sql
mysql -e "SHOW TABLES FROM pmois;"   # ต้องว่าง
```

**Validated:** rollback ลบครบ 45/45 ตาราง และติดตั้งใหม่ด้วย consolidated installer ได้ทันที (ผ่านการทดสอบจริง — ดู TEST-RESULTS-M9-R1.txt §4)

### Rollback บางส่วน (มี migration เดิมคงอยู่ใน repository)

Migration ทีละไฟล์ + `*.rollback.sql` คงไว้ใน `database/migrations/` สำหรับกรณีถอนเฉพาะชุด
(รัน rollback ย้อนลำดับเลขไฟล์) — สำหรับทีม dev ที่รู้จัก schema; ผู้ติดตั้ง production ใช้ไฟล์เดียวตามข้างบน

## 2. Application rollback

```bash
# คงสำเนา release ก่อน deploy ไว้เสมอ (symlink pattern)
ln -sfn /releases/pmois-<previous> /var/www/pmois
```

- โค้ด rollback = สลับ symlink กลับ release ก่อนหน้า (ไม่ต้องแก้ DB หาก schema ไม่ถูกแก้โดย release ใหม่)
- หาก release ใหม่เพิ่ม migration: รัน rollback ของ migrations นั้น (ไฟล์ `.rollback.sql`) ก่อนสลับ

## 3. หลัง rollback — ตรวจสอบ

1. `GET /api/v1/health` → status ok
2. `php bin/validate-integrations.php` → ALL CHECKS PASSED
3. Login ผ่าน LINE → ตรวจ dashboard

## 4. ข้อควรระวัง

- ห้ามรัน Database Rollback โดยไม่มี backup — ข้อมูล project ทั้งหมดหายถาวร
- `APP_SECRET` ต้องเหมือนเดิมหลัง rollback มิฉะนั้น invitation claim token เก่าจะ invalid (ผู้ใช้ต้อง invite ใหม่)
