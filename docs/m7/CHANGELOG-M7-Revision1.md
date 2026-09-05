# PMOIS v2 — M7 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M6 R1 (100/100) — เริ่ม M7 Portfolio Analytics ได้ทันที
**Date:** 2026-09-06

---

## 1. Database

**ไม่มี migration ใหม่** — M7 เป็น read model บนตารางเดิมทั้งหมด (Backward Compatible เต็มที่)

## 2. New Source Code

- `MySqlAnalyticsRepository` — read-model SQL: milestone stats (on-time/late/overdue), monthly closed trend, upcoming per project, reviewer stats (count + avg turnaround), developer stats (submissions/commits/AI-attributed/cycle time), at-risk projects พร้อม signals, dependency leaders, portfolio workspace KPIs
- `AnalyticsService` — KPI definitions รวมศูนย์ (on-time rate, review turnaround, commit rate), at-risk assembly, dependency analytics (ผ่าน `ProjectDependencyService::graph()`), portfolio analytics, report
- `AnalyticsController` — 8 endpoints:
  - `GET /analytics/kpis` — KPI Dashboard
  - `GET /analytics/milestones?months=` — milestone analytics + trend
  - `GET /analytics/productivity` — CTO/Dev productivity
  - `GET /analytics/health` — health distribution + at-risk projects with signals
  - `GET /analytics/dependencies` — graph + leaders
  - `GET /analytics/workspace` — workspace analytics (รวมทุกอย่าง)
  - `GET /analytics/report` — full report (export/print)
  - `GET /analytics/portfolio` — Portfolio Analytics Dashboard (platform admin)
- `public/app/analytics.html` [NEW] — Charts UI: SVG bar charts (health distribution, monthly trend, dependency leaders), KPI cards, productivity tables, at-risk table — ไม่พึ่ง chart library
- Permissions: workspace-level = `workspace.view`; portfolio = is_platform_admin (pattern เดิม)

## 3. KPI ที่วัดได้

- **Milestone on-time rate %** — closed ตรงเวลา / closed ทั้งหมด (null เมื่อไม่มีข้อมูล)
- **Avg review turnaround (h)** — submit → review decision
- **Avg dev cycle time (h)** — submit → commit (เฉพาะ committed)
- **Commit rate** — committed / submissions ต่อ developer (รวม AI-attributed count)
- **At-risk signals** — overdue open milestones, critical/high known issues, failed deployments ต่อ project yellow/red

## 4. Test Results

**Full Suite: 167 tests / 406 assertions — OK**
- AnalyticsM7Test (8): milestone on-time rate (50% จาก fixture), null เมื่อไม่มี closed, review turnaround 4h, developer stats/commit rate, at-risk signals (overdue/critical issue), dependency leaders (A7-BASE depended-on 2), monthly trend, portfolio cross-workspace

## 5. CTO Observation (recorded — M8/M9)

AI/CTO/Dev Context Exports จาก Knowledge Center — `docs/future-enhancements/CONTEXT-EXPORTS.md` (ไม่ทำใน M7 ตามมติ)

## 6. Principles

API First • Configuration over Hardcode (KPI สูตรรวมศูนย์, trend months ผ่าน query) • Backward Compatible (read-only, ไม่มี migration) • LINE/GitLab/Telegram constraints ไม่เปลี่ยน
