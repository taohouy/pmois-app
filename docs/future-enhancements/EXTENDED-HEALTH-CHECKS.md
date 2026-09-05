# Future Enhancement — Extended Health Checks (M9)

**ที่มา:** CTO Decision on M6 Implementation Revision 1 — Future Enhancement (เก็บไว้สำหรับ M9)
**สถานะ:** บันทึกไว้ตามมติ — **ยังไม่ดำเนินการใน M6**
**Date:** 2026-09-05

---

## 1. เป้าหมาย

ขยาย `GET /api/v1/health` จากเช็ค 2 รายการ (api/database) ให้ครอบคลุม dependencies ทั้งหมด

## 2. Checks ที่จะเพิ่ม (M9)

| Check | วิธี |
|---|---|
| Database | (มีแล้วใน M5) `SELECT 1` |
| Redis | ถ้าเปิดใช้ cache — PING (ปัจจุบันไม่ใช้ Redis ตาม M0 constraint) |
| Queue | ถ้ามี job queue — liveness ของ worker |
| Telegram | ทดสอบ Bot API connectivity (getMe) — รายงานสถานะแม้ไม่ตั้งค่า |
| Storage | เขียน/อ่านไฟล์ทดสอบใน storage path |
| Disk | disk_free_space ของ storage partition เทียบ threshold |
| GitLab Connectivity | ตรวจ base_url ของ git_providers ที่ active (HEAD request — read-only) |

## 3. รูปร่าง

```json
{ "status": "ok|degraded", "checks": { "database": true, "telegram": true, "storage": true, "disk": true, "gitlab": true } }
```

ทุก check เป็น non-blocking (timeout สั้น) — health endpoint ต้องตอบเร็วเสมอ
