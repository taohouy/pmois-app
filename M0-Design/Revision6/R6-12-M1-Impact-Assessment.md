# PMOIS v2 — M1 Impact Assessment — Revision 6

**Evaluates:** CTO Decision R6 requirements against M1 Implementation Plan Revision 3 (Commit `1995e7d`) and the already-implemented M1 Phase 1 (Commit `5cbd184`).

---

## 1. Verdict on already-implemented Phase 1 — **no rework, no breaking changes**

| Implemented (0033–0042, services, controllers) | R6 impact |
|---|---|
| LINE Login fields (0033) | None |
| Projects hierarchy/profile ALTER (0034) — incl. `development_mode`, `progress_percent`, `profile_completeness_percent` | **Positive**: CTO Requirements #3/#5 land on existing columns; only the completeness formula (service layer) is added |
| `project_structure_history` (0035) | None |
| `milestones` (0036), `revisions`/`revision_reviews` (0038/0039) | None — Release Registry is a separate table; revision workflow untouched |
| `repositories` (0037) | One follow-up ALTER (0046): + `git_provider_id`, rename `gitlab_url`→`repository_url`. Phase 1 only created the table; no implemented code reads the column (RepositoryService was Phase 2 scope) |
| `project_ai_assignments` (0040) | None — assignment still references `ai_consumers`; only the consumer registry gains `provider_id` |
| Permission seed (0042) | Additive seed 0055; Q11 rule preserved |

**Conclusion: suspend-and-revise cost is confined to additive migrations + new code. Nothing implemented needs to be rewritten.**

## 2. New work introduced by R6

### 2.1 Migrations 0043–0055 (13 files, see `R6-02`)

### 2.2 New domain services (`src/Domain/`)

| Service | Responsibility |
|---|---|
| `ProjectTeamAssignmentService` | Ledger + `project_members` projection sync |
| `AiProviderService` | Global provider registry (admin-managed) |
| `GitProviderService` | Global git provider registry |
| `ProjectTechStackService` | Tech stack registry CRUD + completeness triggers |
| `ProjectEnvironmentService` | Environment registry CRUD (secret-free validation) |
| `ProjectDependencyService` | Edge validation (cycle/self/workspace), graph query |
| `ProjectReleaseService` | Release state machine |
| `ProjectTemplateService` | Template CRUD, payload validation, `apply(projectId)` pipeline |
| `WorkspaceDefaultSettingsService` | Defaults CRUD + resolution helpers |
| `ProfileCompletenessCalculator` | Weighted checklist → `profile_completeness_percent` |

### 2.3 New controllers (`src/Application/Http/Controllers/`)

`AiProviderController`, `GitProviderController`, `TeamAssignmentController`, `TechStackController`, `EnvironmentController`, `DependencyController`, `ReleaseController`, `ProjectTemplateController`, `WorkspaceDefaultSettingsController` — all following the existing middleware chain (`AuthTokenMiddleware` → `RequiresPermissionMiddleware` → controller) and `ApiResponse` envelope.

### 2.4 Modified existing code

| File | Change |
|---|---|
| `ProjectController` (create) | Template + workspace-defaults resolution, extended response (R6-05 §1.3) |
| `AiConsumerController` (existing pattern) | Require `provider_id` on write |
| `RepositoryController`/`RepositoryService` (Phase 2 scope) | `git_provider_id` + `repository_url` field names |
| Migration runner config | Register 0043–0055 |

## 3. Re-plan of remaining M1 phases (gate-based, no durations)

| Phase | Content | Delta from M1 Plan R3 |
|---|---|---|
| Phase 1 | (implemented) | Closed as-is; sign off with a re-verification run of migrations 0033–0042 |
| **Phase 1.5 (new)** | Migrations 0043–0055 + provider registries + workspace defaults + template engine + creation pipeline update | **New** — the R6 schema foundation |
| Phase 2 | Revision workflow, governance auto-bind, AI assignment, repository registration, invitations | Repository controller updated for provider model; AI consumer writes require provider; **everything else unchanged** |
| Phase 3 | Audit integration + `ProjectStatusUpdater` | Add audit events for R6 registries (`TEAM_ASSIGNED`, `DEPENDENCY_*`, `RELEASE_*`, `WORKSPACE_DEFAULTS_UPDATED`, `PROJECT_FROM_TEMPLATE`) |
| Phase 4 | Hardening | Add tests: dependency cycle rejection, template application, completeness calculator, provider backfill, release state machine |

## 4. Risks / open items carried into R6

| ID | Item | Status |
|---|---|---|
| Q09 | Frontend framework | Still open — blocks UI work only; R6 UI sitemap is framework-agnostic |
| R6-Q1 | `default_permission_preset` concrete preset contents (`standard`/`restricted` role maps) | Seed-level config, finalized during Phase 1.5 with PMO sign-off |
| R6-Q2 | Whether AI agents may hold `CTO` role assignments (policy, not technical) | Open — flagged in R5 §09-5, unchanged by R6 |
| R6-Q3 | `ai_consumers.provider_id` — keep nullable column vs backfill-then-tighten to NOT NULL | Recommendation: backfill in 0044 then `MODIFY provider_id NOT NULL` in a later migration once data is reclassified; decision deferred to Phase 1.5 |

## 5. Gate

**No coding resumes until CTO approves Revision 6 (Design Freeze).** Upon approval, Phase 1.5 begins per §3. Any conflict found during implementation → stop and report to CTO per governance.

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
