# PMOIS v2 — M2 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M1 Revision 2 (98/100, APPROVED WITH OBSERVATION) • M0 R6 Design Freeze • M1 R2 implementation
**Date:** 2026-09-05

---

## 1. M2 Scope (ตาม CTO letter)

| Area | Deliverable |
|---|---|
| Portfolio Dashboard | `GET /api/v1/dashboards/portfolio` (cross-workspace, platform admin) |
| Workspace Dashboard | `GET /api/v1/dashboards/workspace` |
| Parent Project Dashboard | children roll-up ใน project dashboard (`children` + `children_rollup`) |
| Project Dashboard | `GET /api/v1/projects/{id}/dashboard` |
| Timeline | `GET /api/v1/projects/{id}/timeline` + workspace timeline ใน workspace dashboard |
| Recent Activities | `GET /api/v1/dashboards/recent-activities` (+ ใน dashboard payloads) |
| Progress Summary | `GET /api/v1/dashboards/progress-summary` |
| Health Summary | `GET /api/v1/dashboards/health-summary` (แยกจาก progress ตาม M0 R6) |
| Portfolio Statistics | `GET /api/v1/dashboards/statistics` + `totals` ใน portfolio payload |
| Dashboard API | ทุก endpoint ข้างบน — API First, payload ชุดเดียวกับ Web UI ในอนาคต |

## 2. Principles applied

- **API First** — read model ทั้งหมดผ่าน REST API ด้วย envelope เดิม (`ApiResponse`), Web UI (เมื่อ Q09 ตัดสินใจ) จะใช้ endpoint ชุดนี้ตรงๆ
- **Configuration over Hardcode** — timeline event sources เป็น config (`DashboardService::TIMELINE_SOURCES`), เพิ่ม/ลด source ไม่ต้องแก้ controller; limits ผ่าน query param (clamp 1..50)
- **Backward Compatible** — **ไม่มี schema change, ไม่มี mutation** — M2 เป็น read model บนตารางเดิมทั้งหมด; endpoint ใหม่ทั้งหมดอยู่ใต้ `/api/v1` เดิม

## 3. Permission model

| Endpoint | Permission |
|---|---|
| `/dashboards/*` (workspace-level) | `workspace.view` |
| `/dashboards/portfolio` | `workspace.view` (route) + `is_platform_admin` (controller — มุมมอง CEO) |
| `/projects/{id}/dashboard`, `/projects/{id}/timeline` | `project.view` (ผ่าน PermissionResolver — project/workspace membership) |

Project scoping ตามปกติ: project ต่าง workspace → 404 fail-closed (test ครอบ)

## 4. Files

| File | Role |
|---|---|
| `src/Infrastructure/Persistence/MySQL/MySqlDashboardRepository.php` | Read-model SQL (aggregates, timeline sources) |
| `src/Domain/Dashboard/DashboardService.php` | Payload assembly + timeline merge + config |
| `src/Application/Http/Controllers/DashboardController.php` | Workspace/Portfolio/Summaries/Statistics/Activities |
| `src/Application/Http/Controllers/ProjectDashboardController.php` | Project dashboard + timeline |
| `tests/Integration/DashboardTest.php` | 14 tests — aggregation, isolation, parent roll-up, timeline ordering |

## 5. CTO Observation (recorded, non-blocking)

IdentityVerificationService — บันทึกเป็น Future Enhancement แล้ว ดู `docs/future-enhancements/IDENTITY-VERIFICATION-SERVICE.md` (ไม่ดำเนินการใน M2 ตามมติ)

## 6. Gate

Phase ถัดไป (M1 Phase 2 — Revision workflow) ยังค้างรอตามแผน — M2 ไม่ได้แทนที่ Phase 2 แต่ทำขนานตามมติ CTO
