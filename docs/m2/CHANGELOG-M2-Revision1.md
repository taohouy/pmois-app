# PMOIS v2 — M2 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M1 Revision 2 — เริ่ม M2 Portfolio Dashboard ได้ทันที
**Date:** 2026-09-05

---

## 1. New Source Code (read model — ไม่มี schema change, ไม่มี mutation)

| File | Content |
|---|---|
| `src/Infrastructure/Persistence/MySQL/MySqlDashboardRepository.php` | Read-model SQL: workspace/portfolio/project aggregates, timeline sources, recent activities (workspace-scoped ด้วยพารามิเตอร์ทุก query; portfolio แยกเมธอดสำหรับ platform admin) |
| `src/Domain/Dashboard/DashboardService.php` | Payload assembly: workspace dashboard, project dashboard (+parent children roll-up), timeline merge (config-driven sources), progress/health summary, statistics, portfolio dashboard |
| `src/Application/Http/Controllers/DashboardController.php` | `workspace` / `portfolio` (admin check) / `progressSummary` / `healthSummary` / `statistics` / `recentActivities` |
| `src/Application/Http/Controllers/ProjectDashboardController.php` | project `show` + `timeline` (fail-closed ต่าง workspace → 404) |
| `src/Config/routes.php` | 8 new GET endpoints (ทั้งหมดอยู่ใต้ /api/v1 เดิม) |
| `src/Config/dependencies.php` | DI wiring ใหม่ |

## 2. M2 Scope coverage

| Scope item | Where |
|---|---|
| Portfolio Dashboard | `GET /dashboards/portfolio` (totals + per-workspace + dependencies + cross-workspace activities) |
| Workspace Dashboard | `GET /dashboards/workspace` |
| Parent Project Dashboard | children + children_rollup ใน project dashboard |
| Project Dashboard | `GET /projects/{id}/dashboard` |
| Timeline | `GET /projects/{id}/timeline` + workspace timeline ใน workspace dashboard |
| Recent Activities | `GET /dashboards/recent-activities` + ใน dashboard payloads |
| Progress Summary | `GET /dashboards/progress-summary` (buckets 0-25/26-50/51-75/76-100) |
| Health Summary | `GET /dashboards/health-summary` (แยกจาก progress) |
| Portfolio Statistics | `GET /dashboards/statistics` + portfolio totals |
| Dashboard API | ทั้งหมดข้างบน — API First |

## 3. Principles

- **API First** — payload ชุดเดียวกับ Web UI ในอนาคต (Q09 ยังเปิด)
- **Configuration over Hardcode** — timeline sources เป็น config (`DashboardService::TIMELINE_SOURCES`), limits ผ่าน query (clamp 1–50)
- **Backward Compatible** — ไม่มี migration ใหม่, ไม่มีการแก้ endpoint/ตารางเดิม, endpoint ใหม่เป็น GET ทั้งหมด

## 4. CTO Observation (recorded)

IdentityVerificationService — บันทึกเป็น Future Enhancement: `docs/future-enhancements/IDENTITY-VERIFICATION-SERVICE.md` (ไม่ดำเนินการใน M2 ตามมติ)

## 5. Test Results

**Full Suite: 124 tests / 286 assertions — OK** (รวม M2 Dashboard 14 tests: aggregation, workspace isolation, parent roll-up, timeline ordering, fail-closed)
