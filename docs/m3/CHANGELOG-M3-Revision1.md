# PMOIS v2 — M3 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M2 R1 (99/100) — เริ่ม M3 Project Management ได้ทันที
**Date:** 2026-09-05

---

## 1. Database

- `0059_create_project_deployments` (+rollback) — deployment tracking (FKs: project_releases, project_environments) — additive เท่านั้น

## 2. New Source Code

**Revision Management / CTO Review Workflow / Commit Tracking**
- `src/Domain/Project/Revision.php` + `RevisionRepositoryInterface` + `MySqlRevisionRepository` (รวม review history)
- `src/Domain/Project/RevisionService.php` — state machine `submitted → cto_approved/cto_rejected → committed`; กฎ `MILESTONE_NOT_OPEN`, `REVISION_NOT_APPROVED`, `REVISION_ALREADY_REVIEWED`, dev_user XOR dev_ai
- `ProjectStatusUpdater` — implement จริง: commit → PMO timeline row (`project_status_updates`, idempotency `revision-commit-{id}`, test failed → at_risk)
- `RevisionController` / `RevisionReviewController` + routes

**Deployment Tracking**
- `ProjectDeployment` + `ProjectDeploymentRepositoryInterface` + `MySqlProjectDeploymentRepository`
- `DeploymentService` — state machine `pending → in_progress → deployed → rolled_back/failed`
- `DeploymentController` + routes

**Project Activity History**
- `MySqlDashboardRepository::projectActivities` + `DashboardService::projectActivities` + endpoint `GET /projects/{id}/activities` (fail-closed ต่าง workspace)

## 3. Web UI (ตอบ Observation ของ M2)

Static web app ใต้ `public/app/` — vanilla HTML/CSS/JS, ใช้ session cookie + API เดิมทั้งหมด ไม่มี business logic ซ้ำใน UI:
- `index.html` — LINE Login
- `dashboard.html` — Workspace Dashboard + Portfolio (admin) + summaries
- `projects.html` — project list + Create Project (CEO กรอกน้อยที่สุด)
- `project.html` — project dashboard, submit revision, commit tracking, deployments, timeline, activity history
- `reviews.html` — CTO review queue (approve/reject)
- `LineLoginController::callback` — browser (Accept: text/html) ถูก redirect เข้า app; API clients ยังได้ JSON (Backward Compatible)

## 4. Test Results

**Full Suite: 136 tests / 318 assertions — OK**
- RevisionWorkflowTest (8): submit defaults, MILESTONE_NOT_OPEN, dev XOR ai, REVISION_NOT_APPROVED, happy path + PMO timeline row, reject terminal, review history, double review
- DeploymentTrackingTest (4): create pending, happy path (deployed_at/by), invalid transition, rolled_back terminal

## 5. Principles

API First (UI ยิง API เดิม) • Backward Compatible (additive migration, JSON contract ไม่เปลี่ยน) • Configuration over Hardcode (state machine เป็น const) • CEO กรอกน้อยที่สุด (create form 7 fields)
