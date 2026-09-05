# PMOIS v2 — M7 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M6 Revision 1 (100/100) • M0 R6 Design Freeze • CTO Constraints (LINE / GitLab / Telegram)
**Date:** 2026-09-06

---

## 1. M7 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| Portfolio Analytics Dashboard | `GET /analytics/portfolio` (cross-workspace KPIs, platform admin) | ✅ |
| KPI Dashboard | `GET /analytics/kpis` — avg progress, **milestone on-time rate**, avg review turnaround, overdue open milestones | ✅ |
| Dependency Graph | `GET /analytics/dependencies` — graph (จาก M2) + **leaders** (most depended-on / most depending) | ✅ |
| Workspace Analytics | `GET /analytics/workspace` — รวมทุก analytics ของ workspace | ✅ |
| Project Health Analytics | `GET /analytics/health` — distribution + **at-risk projects พร้อม signals** (overdue milestones, critical known issues, failed deployments) | ✅ |
| Milestone Analytics | `GET /analytics/milestones` — stats + on-time/late + monthly closed trend + upcoming per project | ✅ |
| CTO / Dev Productivity Metrics | `GET /analytics/productivity` — reviewers (count + avg turnaround), developers (submissions/commits/commit cycle/AI-attributed) | ✅ |
| Reports | `GET /analytics/report` — รวมทุก analytics เป็นชุดเดียว (export/print) | ✅ |
| Charts | `/app/analytics.html` — SVG bar charts (ไม่พึ่ง chart library) | ✅ |
| Analytics API | 8 endpoints ใหม่ ใต้ `/api/v1` | ✅ |

## 2. KPI definitions (config — ปรับสูตรที่ `AnalyticsService`)

| KPI | สูตร |
|---|---|
| Milestone on-time rate % | closed_on_time / (closed_on_time + closed_late) — null เมื่อไม่มี closed milestones |
| Avg review turnaround (h) | avg(revision_reviews.reviewed_at − revisions.submitted_at) |
| Avg dev cycle time (h) | avg(revisions.committed_at − submitted_at) เฉพาะ committed |
| Commit rate | committed / submissions (ต่อ dev — ใน productivity payload) |
| At-risk project | health yellow/red + signals: overdue open milestones, critical/high known issues, failed deployments |

## 3. Principles

- **API First** — analytics payload ชุดเดียวกับ UI; `analytics.html` อ่านจาก API เท่านั้น
- **Configuration over Hardcode** — KPI สูตรรวมศูนย์ใน service; จำนวนเดือนของ trend ปรับผ่าน query (`?months=`, clamp 1–24)
- **Backward Compatible** — **read-only ทั้งหมด, ไม่มี migration ใหม่, ไม่มี endpoint เดิมถูกแก้**; portfolio endpoint ใช้ pattern is_platform_admin เดิม

## 4. Files

| File | Role |
|---|---|
| `MySqlAnalyticsRepository` | read-model SQL (milestones/productivity/health/dependency/portfolio) |
| `AnalyticsService` | KPI assembly + at-risk signals + portfolio |
| `AnalyticsController` | 8 endpoints |
| `public/app/analytics.html` | Charts UI (SVG bars, trend, tables) |
| `tests/Integration/AnalyticsM7Test.php` | 8 tests |

## 5. CTO Observation (recorded — M8/M9)

AI Context Export / CTO Context Export / Dev Context Export จาก Knowledge Center โดยอัตโนมัติ — บันทึก `docs/future-enhancements/CONTEXT-EXPORTS.md` (ไม่ทำใน M7 ตามมติ)
