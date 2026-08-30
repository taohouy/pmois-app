# PMOIS v2 — M1 Implementation Plan (Revision 3)

**Reference:** M0 Design Package Revision 5 (Commit: `7adb6bb`)

**Status:** CTO APPROVED — Ready for Phase 1 Coding

---

## 1. M1 Scope (from Approved M0 Design)

Per `14-Milestone-Roadmap-for-Implementation.md` (Revision 2) and design documents:

| Area | Source Document | M1 Deliverable |
|------|----------------|----------------|
| **Database** | `02-MySQL-ER-Diagram-Database-Table-Design.md` §3 | Migration files for all `[ALTER]` and `[NEW]` tables |
| **LINE Login** | `05-Auth-Authorization-Design.md` §2 | OIDC integration, `users` ALTER, invitation/claim flow |
| **Project CRUD + Hierarchy** | `06-Project-Creation-Move-Promote-Flows.md` | POST/PATCH `/api/v1/projects`, structure endpoints |
| **CTO Review Workflow** | `07-CTO-Review-Commit-PMO-Update-Flow.md` | `revisions`, `revision_reviews` endpoints + state machine |
| **Governance Auto-Binding** | `08-Governance-Template-Design.md` | Auto-bind on project creation, API read endpoints |
| **AI Assignment** | `09-AI-Assignment-Design.md` | `project_ai_assignments` CRUD, `revisions.dev_ai_consumer_id` |
| **GitLab Repository Registry** | `10-GitLab-Repository-Design.md` | Manual registration + read-only sync (no auto-provision) |
| **Permission Codes** | `12-Role-Permission-Matrix.md` §4 | Seed data for **8** new permission codes |
| **Audit/Security** | `13-Audit-Security-Design.md` | Extend `audit_trails` for M0 events, LINE login audit |
| **Milestones & Revisions** | `07-...md`, `02-...md` §3.4, §3.6 | `milestones`, `revisions`, `revision_reviews` services |

**Explicitly NOT in M1 (per M0 Revision 2):**
- GitLab webhook handlers, auto-create groups/branches
- Docker/Kubernetes deployment, Redis, Vault, observability stack
- Web UI (blocked by Q09 decision)
- Fixed SLA/RPO/RTO, mandatory rate limits

---

## 2. Existing Components to Reuse (Zero New Code)

| Component | File / Location | Reuse Strategy |
|-----------|-----------------|----------------|
| `BaseRepository` | `src/Infrastructure/Persistence/MySQL/BaseRepository.php` | Extend for all new repositories |
| `AuthTokenMiddleware` | `src/Application/Middleware/AuthTokenMiddleware.php` | No changes — handles `api_tokens` auth |
| `ProjectScopeMiddleware` | `src/Application/Middleware/ProjectScopeMiddleware.php` | No changes — enforces project binding |
| `RequiresPermissionMiddleware` | `src/Application/Middleware/RequiresPermissionMiddleware.php` | Use with new `permission_code`s |
| `PermissionResolver` | `src/Domain/Identity/PermissionResolver.php` | No changes — new codes added via seed |
| `AuthTokenRepository` / `ProjectRepository` etc. | Existing repositories | Extend pattern for new tables |
| `ApiResponse` | `src/Application/Http/Responders/ApiResponse.php` | Use exact existing envelope |
| `audit_trails` table + `AuditContext` | Existing | Write M0 events via same pattern |

---

## 3. New Files / Components to Create

### 3.1 Database Migrations (new files in `database/migrations/`)

| Migration | Table | Type | Reference |
|-----------|-------|------|-----------|
| `0033_alter_users_add_line_fields` | `users` | ALTER | `02-...md` §3.1 |
| `0034_alter_projects_add_hierarchy_profile` | `projects` | ALTER | `02-...md` §3.2 |
| `0035_create_project_structure_history` | `project_structure_history` | NEW | `02-...md` §3.3 |
| `0036_create_milestones` | `milestones` | NEW | `02-...md` §3.4 |
| `0037_create_repositories` | `repositories` | NEW | `02-...md` §3.5 |
| `0038_create_revisions` | `revisions` | NEW | `02-...md` §3.6 |
| `0039_create_revision_reviews` | `revision_reviews` | NEW | `02-...md` §3.6 |
| `0040_create_project_ai_assignments` | `project_ai_assignments` | NEW | `02-...md` §3.7 |
| `0041_alter_projects_add_current_milestone_fk` | `projects` | ALTER (FK) | After `0036` |
| `0042_seed_m0_permission_codes` | `role_permissions` | SEED | `12-Role-Permission-Matrix.md` §4 |

**Order constraint:** `0036` (milestones) before `0041` (FK); `revisions` before `revision_reviews` (FK); `repositories` independent.

---

### 3.2 Domain Services (new files in `src/Domain/`)

| Service | File | Responsibility |
|---------|------|----------------|
| `ProjectStructureService` | `src/Domain/Project/ProjectStructureService.php` | Move workspace, change parent, promote, structure history |
| `MilestoneService` | `src/Domain/Project/MilestoneService.php` | Milestone CRUD, open/close (CTO only) |
| `RevisionService` | `src/Domain/Project/RevisionService.php` | Submit, commit, status transitions |
| `RevisionReviewService` | `src/Domain/Project/RevisionReviewService.php` | CTO approve/reject |
| `AiAssignmentService` | `src/Domain/Project/AiAssignmentService.php` | `project_ai_assignments` CRUD |
| `RepositoryService` | `src/Domain/Project/RepositoryService.php` | Manual registration, read-only sync |
| `LineLoginService` | `src/Domain/Auth/LineLoginService.php` | **OIDC flow + existing user lookup + LINE account binding** — Normal LINE Login must NOT auto-create user/membership. Placeholder user creation only via Invitation/Account Claim Flow (see `InvitationService`). |
| `InvitationService` | `src/Domain/Auth/InvitationService.php` | Create claim token, deliver link, handle claim |
| `GovernanceAutoBindService` | `src/Domain/Governance/GovernanceAutoBindService.php` | Auto-bind on project creation |
| `ProjectStatusUpdater` | `src/Domain/Project/ProjectStatusUpdater.php` | Create `project_status_updates` on commit |

---

### 3.2 Controllers (new files in `src/Application/Http/Controllers/`)

| Controller | Endpoints | Permission |
|------------|-----------|------------|
| `ProjectStructureController` | `PATCH /api/v1/projects/{id}/structure` | `project.structure.update` |
| `ProjectStructureHistoryController` | `GET /api/v1/projects/{id}/structure-history` | `project.view` |
| `MilestoneController` | CRUD `/api/v1/projects/{id}/milestones`, `PATCH /milestones/{id}/close` | `milestone.*` |
| `RevisionController` | `POST /api/v1/revisions`, `PATCH /api/v1/revisions/{id}/commit` | `revision.create` |
| `RevisionReviewController` | `POST /api/v1/revisions/{id}/review` | `revision.review` |
| `RepositoryController` | CRUD `/api/v1/repositories` | `repository.view` / `repository.manage` |
| `AiAssignmentController` | CRUD `/api/v1/projects/{id}/ai-assignments` | `ai_assignment.view` / `ai_assignment.manage` |
| `LineLoginController` | `GET /auth/line`, `GET /auth/line/callback` | Guest |
| `InvitationController` | `POST /invitations`, `GET /claim/{token}` | `is_platform_admin` / Guest |
| `GovernanceAdoptionController` | `GET /api/v1/projects/{id}/governance` | `governance.read` |

---

### 3.3 Middleware (new)

| Middleware | Purpose |
|------------|---------|
| `InvitationClaimMiddleware` | Validate claim token on `GET /claim/{token}` |

---

### 3.5 Repositories (new, extending `BaseRepository`)

| Repository | Table |
|------------|-------|
| `MySqlProjectStructureHistoryRepository` | `project_structure_history` |
| `MySqlMilestoneRepository` | `milestones` |
| `MySqlRepositoryRepository` | `repositories` |
| `MySqlRevisionRepository` | `revisions` |
| `MySqlRevisionReviewRepository` | `revision_reviews` |
| `MySqlProjectAiAssignmentRepository` | `project_ai_assignments` |

---

## 4. Database Migration Details (Key Points)

### 4.1 `users` ALTER (§3.1 of 02-...md)
```sql
ALTER TABLE users
  ADD COLUMN line_user_id VARCHAR(64) NULL AFTER email,
  ADD COLUMN line_display_name VARCHAR(150) NULL AFTER line_user_id,
  ADD COLUMN avatar_url VARCHAR(500) NULL AFTER line_display_name,
  ADD COLUMN auth_provider ENUM('local','line') NOT NULL DEFAULT 'local' AFTER avatar_url,
  MODIFY COLUMN password_hash VARCHAR(255) NULL,
  ADD UNIQUE KEY uq_users_line_user_id (line_user_id);
```

### 4.2 `projects` ALTER
```sql
ALTER TABLE projects
  ADD COLUMN parent_project_id BIGINT UNSIGNED NULL AFTER workspace_id,
  ADD COLUMN abbreviation VARCHAR(20) NULL AFTER code,
  ADD COLUMN development_mode ENUM('manual','ai_assisted','ai_dev_auto') NOT NULL DEFAULT 'manual' AFTER status,
  ADD COLUMN current_milestone_id BIGINT UNSIGNED NULL AFTER development_mode,
  ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER current_milestone_id,
  ADD COLUMN health ENUM('green','yellow','red') NOT NULL DEFAULT 'green' AFTER progress_percent,
  ADD COLUMN profile_completeness_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER health,
  ADD COLUMN archived_at TIMESTAMP NULL AFTER end_date,
  ADD CONSTRAINT fk_projects_parent FOREIGN KEY (parent_project_id) REFERENCES projects(id);
```

### 4.3 Permission Seed (`0042_seed_m0_permission_codes`) — **Per 12-Role-Permission-Matrix.md §4**

**8 permission codes total:**
- `project.structure.update` → ADMIN, CTO
- `project.progress.update` → ADMIN, CTO, PMO_REVIEWER
- `milestone.close` → ADMIN, CTO
- `milestone.open` → ADMIN, CTO
- `revision.create` → ADMIN, CTO, SENIOR_DEV, MEMBER
- `revision.review` → ADMIN, CTO
- `repository.manage` → ADMIN, CTO, SENIOR_DEV, MEMBER
- `ai_assignment.manage` → ADMIN, CTO

**Q11 Decision Applied:** `project.create` restricted to ADMIN, CTO only — existing grants for MEMBER, SENIOR_DEV must be removed.

```sql
-- Migration 0042_seed_m0_permission_codes
-- Seed new M0 permission codes per 12-Role-Permission-Matrix.md §4
-- Also enforces Q11: project.create restricted to ADMIN, CTO only (remove MEMBER/SENIOR_DEV grants)

-- Step 1: Insert new M0 permission grants per matrix
INSERT INTO role_permissions (role_id, permission_code)
SELECT r.id, pc.code
FROM (
  -- Permission code -> allowed role codes mapping
  SELECT 'project.structure.update' AS perm_code, 'ADMIN' AS role_code UNION ALL
  SELECT 'project.structure.update', 'CTO' UNION ALL
  SELECT 'project.progress.update', 'ADMIN' UNION ALL
  SELECT 'project.progress.update', 'CTO' UNION ALL
  SELECT 'project.progress.update', 'PMO_REVIEWER' UNION ALL
  SELECT 'milestone.close', 'ADMIN' UNION ALL
  SELECT 'milestone.close', 'CTO' UNION ALL
  SELECT 'milestone.open', 'ADMIN' UNION ALL
  SELECT 'milestone.open', 'CTO' UNION ALL
  SELECT 'revision.create', 'ADMIN' UNION ALL
  SELECT 'revision.create', 'CTO' UNION ALL
  SELECT 'revision.create', 'SENIOR_DEV' UNION ALL
  SELECT 'revision.create', 'MEMBER' UNION ALL
  SELECT 'revision.review', 'ADMIN' UNION ALL
  SELECT 'revision.review', 'CTO' UNION ALL
  SELECT 'repository.manage', 'ADMIN' UNION ALL
  SELECT 'repository.manage', 'CTO' UNION ALL
  SELECT 'repository.manage', 'SENIOR_DEV' UNION ALL
  SELECT 'repository.manage', 'MEMBER' UNION ALL
  SELECT 'ai_assignment.manage', 'ADMIN' UNION ALL
  SELECT 'ai_assignment.manage', 'CTO'
) pc
JOIN roles r ON r.code = pc.role_code
WHERE NOT EXISTS (
  SELECT 1 FROM role_permissions rp
  WHERE rp.role_id = r.id AND rp.permission_code = pc.perm_code
);

-- Q11: Restrict project.create to ADMIN, CTO only
-- Remove existing grants for MEMBER, SENIOR_DEV, PMO_REVIEWER, VIEWER
DELETE FROM role_permissions
WHERE permission_code = 'project.create'
  AND role_id IN (
    SELECT id FROM roles WHERE code IN ('MEMBER', 'SENIOR_DEV', 'PMO_REVIEWER', 'VIEWER')
  );
```

> **Note:** Each permission code is granted exactly per `12-Role-Permission-Matrix.md` §4. **Q11 Decision Applied:** `project.create` restricted to ADMIN, CTO only — existing grants for MEMBER, SENIOR_DEV, PMO_REVIEWER, VIEWER are removed in the same migration. The INSERT uses `WHERE NOT EXISTS` to avoid duplicate key errors on re-run.

---

## 5. API Endpoints Summary (New in M1)

| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| `POST` | `/api/v1/projects` | `ProjectController` (existing) | `project.create` |
| `PATCH` | `/api/v1/projects/{id}/structure` | `ProjectStructureController` | `project.structure.update` |
| `GET` | `/api/v1/projects/{id}/structure-history` | `ProjectStructureHistoryController` | `project.view` |
| `GET`/`POST` | `/api/v1/projects/{id}/milestones` | `MilestoneController` | `milestone.view` / `milestone.create` |
| `PATCH` | `/api/v1/milestones/{id}/close` | `MilestoneController` | `milestone.close` |
| `POST` | `/api/v1/revisions` | `RevisionController` | `revision.create` |
| `PATCH` | `/api/v1/revisions/{id}/commit` | `RevisionController` | `revision.create` (own) |
| `POST` | `/api/v1/revisions/{id}/review` | `RevisionReviewController` | `revision.review` |
| `GET`/`POST` | `/api/v1/repositories` | `RepositoryController` | `repository.view` / `repository.manage` |
| `GET`/`POST` | `/api/v1/projects/{id}/ai-assignments` | `AiAssignmentController` | `ai_assignment.view` / `ai_assignment.manage` |
| `GET` | `/auth/line` | `LineLoginController` | Guest |
| `GET` | `/auth/line/callback` | `LineLoginController` | Guest |
| `POST` | `/invitations` | `InvitationController` | `is_platform_admin` |
| `GET` | `/claim/{token}` | `InvitationController` | Guest |
| `GET` | `/api/v1/projects/{id}/ai-assignments` | `AiAssignmentController` | `ai_assignment.view` |

---

## 6. Implementation Phases (No Fixed Durations — Gate-Based)

> **No fixed week/month estimates.** Each phase completes when its Gate criteria are satisfied, confirmed by Dev Test + CTO Review. No fixed-week estimates are committed.

### Phase 1 — Foundation
**Gate:** All migrations run cleanly; permission codes seeded per matrix; `LineLoginService` + `LineLoginController` OIDC flow working; `ProjectStructureService` + Controller for move/parent/promote; `MilestoneService` + Controller CRUD; `LineLoginController` OIDC flow (no auto-create user).

**Exit Criteria:**
- All 10 migrations run up/down cleanly
- Permission seed matches `12-Role-Permission-Matrix.md` exactly
- LINE Login OIDC flow works (normal login + invitation claim)
- Project structure CRUD works; milestones CRUD works
- Dev test passes; CTO Review Gate passed

---

### Phase 2 — Core Workflow
**Gate:** Revision workflow end-to-end works; Governance auto-bind works; AI assignment works.

**Scope:**
- `InvitationService` + `InvitationController` (claim token flow, no LINE Notify, no `pending_invitation` status)
- `RevisionService` + `RevisionReviewService` + Controllers (submit → CTO approve/reject → commit → PMO update)
- `RepositoryService` + Controller (manual registration, read-only sync)
- `AiAssignmentService` + Controller
- `GovernanceAutoBindService` (auto-bind on project creation)
- `InvitationController` + `InvitationClaimMiddleware` (claim token flow, no `pending_invitation` status, no LINE Notify)

**Exit Criteria:**
- Dev submits revision → CTO approve → Dev commit → PMO update works end-to-end
- Governance auto-binds on project creation
- AI assignment CRUD works
- Repository registration/sync works
- Invitation flow: admin creates placeholder + active membership + claim token → user claims via LINE → `line_user_id` bound
- Dev test passes; CTO Review Gate passed

---

### Phase 3 — Integration & Audit
**Gate:** All audit events logged; end-to-end test passes.

**Scope:**
- `ProjectStatusUpdater` (on revision committed → `project_status_updates`)
- `ProjectStructureHistory` audit writes
- `ProjectStatusUpdater` on revision commit
- `ProjectStructureController` (move/change parent/promote)
- Audit trail writes for all new events
- `InvitationController` claim token validation

**Exit Criteria:**
- All new events appear in `audit_trails` with correct `before/after`
- End-to-end CTO Review workflow tested
- Migration up/down test clean
- Dev test passes; CTO Review Gate passed

---

### Phase 4 — Hardening
**Gate:** All tests green; documentation updated; no open blockers.

**Scope:**
- Integration tests for all new endpoints
- Migration up/down test
- Permission matrix validation against seed
- Fail-closed LINE login test
- CTO review workflow end-to-end test
- Documentation update

**Exit Criteria:**
- All tests green
- No open blockers
- CTO Review Gate passed → M1 Complete

---

## 6. Test Strategy

| Test Type | Coverage |
|-----------|----------|
| **Unit** | Services (permission checks, state transitions), `PermissionResolver` with new codes |
| **Integration** | Full API request/response for each new endpoint, middleware chain |
| **Migration** | Up/down for all 10 new migrations, FK constraints, seed data |
| **E2E** | CTO Review workflow end-to-end: Dev submit → CTO approve → commit → PMO update |
| **Security** | Fail-closed LINE login, project-scoped token isolation, permission denial |

**Test Data:** Use existing `phpunit` setup; no new test framework.

---

## 7. Open Decisions & Blockers

| ID | Decision | Blocks | Status | Owner |
|----|----------|--------|--------|-------|
| **Q09** | Frontend framework (SPA vs server-rendered) | **Blocks M1 UI work only** — backend can proceed | Open (Deferred) | CTO |
| **Q10** | Single-CEO enforcement | **CLOSED** — Operational rule, no DB constraint | **CLOSED** | CTO |
| **Q11** | Restrict `project.create` to admin-only (seed change) | **CLOSED** — ADMIN, CTO only | **CLOSED** | CTO |
| **Q12** | CTO review SLA/escalation/override mechanism | Not implemented in M1 — open proposal | Open (Not M1 blocker) | CTO |
| **Hosting/Infra** | TLS/TDE, backup, RPO/RTO | Not blocking code | Open (Deployment-dependent) | CTO/Infra |

**Decisions blocking Phase 1 start:** **None** — Q11 is now CLOSED, Migration 0042 can be finalized.

**Decisions that can be deferred past Phase 1:**
- Q09 (frontend) — only blocks UI work, not backend
- Q12 (review SLA) — not implemented in M1
- Hosting/Infra — deployment-dependent

---

## 7. Gate

**No coding begins until CTO approves this M1 Implementation Plan.**

Upon approval, work begins in Phase 1 order. Any design conflict, schema conflict, or undecided requirement encountered during implementation → **stop and report to CTO** (per governance).

---

*Document Version: 3.0 (Revision 3)*
*Reference: M0 Revision 5 (Commit `7adb6bb`)*
*Date: 2026-08-31*
*Status: CTO APPROVED*