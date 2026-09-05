# PMOIS v2 — Review Note (M2 Implementation Revision 1)

**สำหรับ:** CTO Review — M2 Portfolio Dashboard
**อ้างอิง:** CTO Approval on M1 R2 (APPROVED WITH OBSERVATION) + M2 Scope letter

---

## 1. Scope coverage (10/10)

Portfolio Dashboard ✅ / Workspace Dashboard ✅ / Parent Project Dashboard ✅ (children roll-up) / Project Dashboard ✅ / Timeline ✅ (project + workspace) / Recent Activities ✅ / Progress Summary ✅ / Health Summary ✅ / Portfolio Statistics ✅ / Dashboard API ✅

## 2. Verification evidence

- **Full Suite: 124 tests / 286 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0058)
- M2 Dashboard tests: 14 tests — aggregation ถูกต้อง, workspace isolation (project ต่าง workspace ไม่หลุด: dashboard 404 / timeline ว่าง / activities กรอง), parent children roll-up, timeline เรียงใหม่→เก่า, health summary ไม่มี progress fields (แยก concept)
- `php -l` ผ่านทุกไฟล์; ไม่มี migration ใหม่ (read-only M2)

## 3. Design decisions ที่ CTO ควรทราบ

1. **Read model ล้วน** — M2 ไม่แตะ mutation path ใดๆ, backward compatible เต็มที่ (ไม่มี schema change)
2. **Portfolio permission** — `is_platform_admin` เท่านั้น (มุมมอง CEO ตาม role matrix), ตรวจใน controller ตาม pattern เดิมของ global registries
3. **Parent roll-up** — รวมใน project dashboard payload (`children` + `children_rollup`) เฉพาะเมื่อมีโครงการลูก — endpoint เดียวใช้ได้ทั้ง leaf และ parent
4. **Timeline sources เป็น config** (`DashboardService::TIMELINE_SOURCES`) — เพิ่ม event source ใหม่ (เช่น revision workflow ของ Phase 2) แก้ที่เดียว
5. **`avg_progress` rounding** — MySQL ROUND (integer percent) เพื่อ payload สะอาด
6. **Observation บันทึกแล้ว** — `docs/future-enhancements/IDENTITY-VERIFICATION-SERVICE.md` (ไม่ทำใน M2 ตามมติ)

## 4. ไม่อยู่ใน M2 (ตามแผน)

- M1 Phase 2 (Revision workflow) — ยังค้างรอตาม M1 Plan
- Web UI dashboards — รอ Q09 (frontend)
- Notification (Telegram) — ยังไม่เริ่ม

## 5. Deliverable

`PMOIS_v2_M2_Implementation_Revision1.zip` — Source, Tests, Test Results, API Documentation, Plan, Changelog, Review Note, Future Enhancement doc
