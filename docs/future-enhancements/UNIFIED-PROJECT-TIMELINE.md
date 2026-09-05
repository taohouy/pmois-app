# Future Enhancement — Unified Project Timeline View

**ที่มา:** CTO Decision on M4 Implementation Revision 1 (APPROVED WITH OBSERVATION) — Observation (Non-blocking)
**สถานะ:** บันทึกไว้ตามมติ — **ยังไม่ดำเนินการใน M4**
**Date:** 2026-09-05

---

## 1. เป้าหมาย

ปรับ Project Detail ให้แสดง **Timeline แบบต่อเนื่อง** ที่เชื่อม
**Revision → Review → Commit → Deployment → Release** ไว้ในหน้าจอเดียว

## 2. รูปร่างที่เสนอ

- ต่อยอด `DashboardService::TIMELINE_SOURCES` (config-driven แล้ว) ด้วย correlation: revision_id เป็น anchor เชื่อม
  - revision.submitted_at (submitted)
  - revision_reviews.reviewed_at (review decision)
  - revisions.committed_at (commit)
  - project_deployments.deployed_at (deployment — ต่อ environment)
  - project_releases.released_at (release)
- UI: vertical timeline ใน project detail พร้อม filter ตาม event_type + ลิงก์ข้าม object

## 3. เหตุผลที่ยังไม่ทำใน M4

- M4 โฟกัส Governance — event sources ปัจจุบัน (status_update / milestone_closed / release / revision) ให้ข้อมูลพอสำหรับ MVP
- ต้องรอ UX feedback จาก Web UI ที่เพิ่งเริ่มใน M3 ก่อนออกแบบจอเดียวแบบรวม
