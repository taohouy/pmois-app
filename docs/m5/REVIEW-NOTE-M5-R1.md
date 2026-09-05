# PMOIS v2 — Review Note (M5 Implementation Revision 1)

**สำหรับ:** CTO Review — M5 API Platform
**อ้างอิง:** CTO Approval on M4 R1 (100/100)

---

## 1. Scope coverage (11/11)

REST API Platform ✅ • Project API Token Management ✅ • API Scope Management ✅ • API Authentication ✅ • API Authorization ✅ • Telegram Notification Integration ✅ • Audit API ✅ • API Rate Limiting ✅ • API Documentation ✅ (OpenAPI 3.0) • API Monitoring ✅ • API Health Check ✅

## 2. Verification evidence

- **Full Suite: 150 tests / 359 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0061)
- ApiPlatformM5Test (7): Telegram send สำเร็จ (ตรวจ request body ไป api.telegram.org), skipped เมื่อไม่ตั้ง env, failed แล้วไม่ throw (best-effort), event template, audit filters + workspace isolation, project token listing, scope mapping
- `php -l` ผ่านทุกไฟล์; migration 0061 additive (มี rollback)

## 3. Design decisions ให้ CTO ทราบ

1. **Scope enforcement แบบ backward compatible** — ยึด M0 R5 กฎเดิม (scopes informational) ต่อ: legacy token (ไม่ระบุ scopes) ผ่านทุก endpoint ไม่เปลี่ยนพฤติกรรม; token ใหม่ที่ประกาศ scopes ถูกจำกัดจริง ถ้า CTO ต้องการบังคับทุก token ให้ประกาศ scopes เป็น required (เปลี่ยนได้ที่ middleware เดียว)
2. **Rate limiting in-memory per worker** — ไม่ใช้ Redis (M0 constraint); ประกาศข้อจำกัด: single-node พอ, multi-node ต้องใช้ shared store (future enhancement)
3. **Telegram best-effort** — ธุรกรรมหลัก (revision/deployment) ไม่พังเพราะ notification; ทุกความพยายาม log ลง `notifications` (0061) เพื่อ monitoring/retry
4. **Health check** — คืน 200 เสมอแต่ `status: degraded` เมื่อ DB ล่ม (ให้ LB/monitoring ตัดสินจาก body)
5. **OpenAPI spec** — ครอบคลุม endpoint หลัก; เป็น living document ควรอัปเดตคู่กับ endpoint ใหม่

## 4. CTO Observation จาก M4 (บันทึกแล้ว)

Unified Project Timeline — `docs/future-enhancements/UNIFIED-PROJECT-TIMELINE.md` (ยังไม่ดำเนินการ)

## 5. Deliverable

`PMOIS_v2_M5_Implementation_Revision1.zip` — Source, Migration (0061), Tests, Test Results, API Documentation (M5 + OpenAPI), Plan, Changelog, Review Note
