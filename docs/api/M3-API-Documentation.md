# PMOIS v2 — M3 API Documentation (Project Management additions)

Base URL `/api/v1` • Envelope/permission model เดิม • ส่วนที่ไม่กล่าวถึงยังใช้ได้เหมือนเดิม

---

## 1. Revision Management & Commit Tracking

| Method | Path | Permission |
|---|---|---|
| POST | `/revisions` | `revision.create` |
| GET | `/projects/{project_id}/revisions?status=` | `project.view` |
| GET | `/revisions/{id}` (รวม `reviews[]`) | `project.view` |
| PATCH | `/revisions/{id}/commit` | `revision.create` |
| POST | `/revisions/{id}/review` | `revision.review` (ADMIN/CTO) |

**POST /revisions body:** `{project_id*, summary*, milestone_id?, repository_id?, test_result?: passed|failed|skipped|pending, branch?, known_issue?, next_action?, dev_user_id? หรือ dev_ai_consumer_id? (ห้ามทั้งคู่ — default = submitter)}`
**State machine:** `submitted → cto_approved/cto_rejected → committed` • submit กับ closed milestone → `409 MILESTONE_NOT_OPEN` • commit ก่อน approved → `409 REVISION_NOT_APPROVED` • review ซ้ำ → `409 REVISION_ALREADY_REVIEWED`

**PATCH /revisions/{id}/commit body:** `{commit_hash*, branch?, push_status?: pending|success|failed}`
→ สำเร็จแล้วสร้าง PMO timeline row (`project_status_updates`, idempotency `revision-commit-{id}`) อัตโนมัติ

**POST /revisions/{id}/review body:** `{decision: approved|rejected, review_note?}`

## 2. Deployment Tracking

| Method | Path | Permission |
|---|---|---|
| GET | `/projects/{project_id}/deployments` | `project.view` |
| POST | `/projects/{project_id}/deployments` — `{release_id?, environment_id?, notes?}` | `project.release.manage` |
| PATCH | `/deployments/{id}/transition` — `{status}` | `project.release.manage` |

**State machine:** `pending → in_progress → deployed → rolled_back` และ `pending/in_progress → failed` (`deployed` ตั้ง deployed_by/deployed_at อัตโนมัติ) • transition ผิด → `422 VALIDATION_ERROR`

## 3. Project Activity History

| Method | Path | Permission |
|---|---|---|
| GET | `/projects/{project_id}/activities?limit=` | `project.view` |

คืน audit rows ของ project (ใหม่→เก่า) — project ต่าง workspace → `404 NOT_FOUND` (fail-closed)

## 4. Web UI (ใหม่ — M3)

Static app ที่ `/app/` (ไฟล์ใต้ `public/app/`) — ใช้ session cookie + Dashboard/Project APIs:

| Page | Path | Content |
|---|---|---|
| Login | `/app/index.html` | LINE Login (one-click) + error display |
| Dashboard | `/app/dashboard.html` | Workspace stats, progress/health summary, governance, upcoming milestones, releases, activities, Portfolio (admin) |
| Projects | `/app/projects.html` | Project list + Create (CEO 7 fields) |
| Project Detail | `/app/project.html?id=` | Dashboard, milestones, submit revision, commit, deployments, timeline, activity history |
| Reviews | `/app/reviews.html` | CTO review queue — approve/reject ทุก project |

Browser login: LINE callback redirect เข้า `/app/projects.html` อัตโนมัติ (ตรวจจาก `Accept: text/html`) — API clients ยังได้ JSON เหมือนเดิม
