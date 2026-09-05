# PMOIS v2 — M3 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M2 Revision 1 (99/100) • M0 R6 Design Freeze • M1 R2 + M2 implementations
**Date:** 2026-09-05

---

## 1. M3 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| Milestone Management | มีตั้งแต่ M1 Phase 1 (CRUD + close/reopen, CTO-only) — เพิ่ม UI | ✅ |
| Revision Management | `RevisionService` + `POST /revisions` + `GET /projects/{id}/revisions` + `GET /revisions/{id}` | ✅ |
| CTO Review Workflow | `POST /revisions/{id}/review` + reviews history + state machine | ✅ |
| Project Timeline Management | Timeline API (M2) + บันทึกเหตุการณ์ผ่าน workflow | ✅ |
| Commit Tracking | `PATCH /revisions/{id}/commit` (commit_hash/branch/push_status, เฉพาะหลัง approved) | ✅ |
| Deployment Tracking | migration 0059 `project_deployments` + endpoints + state machine | ✅ |
| Release Management | มีตั้งแต่ M1 R6 Phase 1.5 (releases registry) — เชื่อม deployment ผ่าน release_id | ✅ |
| Project Activity History | `GET /projects/{id}/activities` (audit-based, fail-closed) | ✅ |
| Review API | ใน Revision/Review endpoints + review history | ✅ |
| Timeline API | มีจาก M2 + เชื่อม workflow events | ✅ |
| **Web UI สำหรับ Project Management** | static web app `public/app/` (5 หน้า) — ตอบ Observation ของ M2 | ✅ |

## 2. Web UI decision (Q09 — resolved pragmatically)

CTO Observation ให้เริ่มพัฒนา Web UI ควบคู่ M3 เพื่อรับ UX feedback ตั้งแต่ต้น

**Decision: Static web app (vanilla HTML/CSS/JS) ภายใต้ `public/app/`** เพราะ:
- ใช้ Dashboard/Project APIs ที่มีอยู่ตรงๆ (API First — UI ไม่มี business logic เอง)
- ใช้ session cookie auth ที่ทำไว้ใน M1 R2 (`pmois_session`) — ไม่แตะโค้ด auth
- ไม่เพิ่ม dependency / build tooling / ไม่ตัดสิน framework ใหญ่ (Q09 เปิดต่อได้ — UI ชุดนี้เป็น MVP เพื่อรับ feedback)
- Backward Compatible: API clients ได้ JSON เหมือนเดิม (browser เท่านั้นที่ถูก redirect ผ่าน Accept header)

Pages: `index.html` (LINE Login) • `dashboard.html` (workspace + portfolio) • `projects.html` (list + create CEO-minimal) • `project.html` (dashboard/milestones/revisions/commit/deployments/timeline/activities) • `reviews.html` (CTO queue — approve/reject)

## 3. Backend additions

| File | Role |
|---|---|
| migration `0059_create_project_deployments` | Deployment tracking table (FKs: releases, environments) |
| `Revision` + `RevisionRepositoryInterface` + `MySqlRevisionRepository` | Revision read/write + review history |
| `RevisionService` | submit (MILESTONE_NOT_OPEN, dev XOR ai) → review (submitted only) → commit (approved only) + PMO timeline update |
| `ProjectStatusUpdater` (implemented จริงแล้ว) | commit → `project_status_updates` (idempotency: `revision-commit-{id}`) |
| `ProjectDeployment` + repo + `DeploymentService` | state machine pending→in_progress→deployed→rolled_back/failed |
| `RevisionController` / `RevisionReviewController` / `DeploymentController` | endpoints + audit |
| `MySqlDashboardRepository::projectActivities` + service/controller | Activity History |

## 4. State machines

```
Revision:   submitted ──review(approved)──▶ cto_approved ──commit──▶ committed
            submitted ──review(rejected)──▶ cto_rejected
Deployment: pending ──▶ in_progress ──▶ deployed ──▶ rolled_back
                          └──▶ failed
```

## 5. Principles

- API First — UI ทุก action ยิง API เดียวกับที่เปิดสาธารณะ; ไม่มี logic ซ้ำใน UI
- Backward Compatible — migration เดียว (0059, additive); callback ยังคืน JSON ให้ API clients (browser เท่านั้น redirect ผ่าน Accept header)
- Configuration over Hardcode — state machines เป็น const tables ใน services
- CEO กรอกน้อยที่สุด — create form ใน UI แสดงเฉพาะ 7 fields + hint ว่า defaults พร้อมใช้
