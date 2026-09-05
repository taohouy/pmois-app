# PMOIS v2 — M1 Implementation — Changelog (Revision 1)

**Baseline:** M0 Design Freeze Revision 6 (CTO approved) + M1 Implementation Plan Revision 3
**Scope:** M1 Phase 1 (+ R6 Phase 1.5) per CTO Coding Scope
**Date:** 2026-09-05

---

## 1. Database Migrations (new: `0043`–`0055`)

| Migration | Content |
|---|---|
| `0043_create_ai_providers` | AI Provider registry (global) + seed: openai, anthropic, google, microsoft, human |
| `0044_alter_ai_consumers_add_provider_id` | Agent→Provider FK + backfill existing agents → `human` |
| `0045_create_git_providers` | Git Provider registry + seed: **gitlab only** (CTO Constraint) |
| `0046_alter_repositories_add_provider_rename_url` | `repositories.git_provider_id` + rename `gitlab_url`→`repository_url` + backfill → gitlab |
| `0047_create_project_member_assignments` | Team assignment ledger (history) + bootstrap from existing `project_members` |
| `0048_create_project_technology_stack` | Technology Stack registry |
| `0049_create_project_environments` | Environment registry (Dev/UAT/Prod, no secrets) |
| `0050_create_project_dependencies` | Dependency graph (depends_on / blocked_by) |
| `0051_create_project_releases` | Release registry (alpha→hotfix) |
| `0052_create_project_templates` | Project Template (payload JSON) |
| `0053_alter_projects_add_source_template_id` | Project provenance |
| `0054_create_workspace_default_settings` | Workspace defaults (1:1) |
| `0055_seed_r6_permission_codes` | 7 new permission codes |

**Verified:** full chain 0001→0055 applied on MySQL 5.7.36; rollback 0055→0043 then re-apply OK.

## 2. New Source Code

**Domain — `src/Domain/Registry/`:** `AiProvider`, `GitProvider`, `ProjectTemplate`, `WorkspaceDefaultSettings` (entities), their repository interfaces, `ProjectTemplateService` (payload contract validation — agents must resolve via Registry), `WorkspaceDefaultSettingsService` (resolution order: request → workspace default → template → fallback), ports `AiConsumerCodeResolverInterface` / `WorkspaceMemberCheckerInterface` / `GovernanceVersionCheckerInterface`.

**Domain — `src/Domain/Project/`:** `ProjectAiAssignment` + `AiAssignmentService` (Registry-referencing AI assignment, history preserved), `ProjectMemberAssignment` + `ProjectTeamAssignmentService` (2-layer ledger/projection sync), `RepositoryRegistry` + `RepositoryRegistryService` (GitLab-only manual registration), `ProjectTechStack` + `TechStackService`, `ProjectEnvironment` + `EnvironmentService` (secret-rejecting validation), `ProjectDependency` + `ProjectDependencyService` (directed cycle detection, graph payload), `ProjectRelease` + `ProjectReleaseService` (state machine), `ProfileCompletenessCalculator` (9-item weighted checklist), `ProjectCreationPipeline` (14-step creation per R6-05).

**Domain — other:** `GovernanceAutoBindService` rewritten from placeholder to real implementation (explicit version → workspace default → latest published).

**Infrastructure — `src/Infrastructure/Persistence/MySQL/`:** 11 new repositories (AiProvider, GitProvider, ProjectTemplate, WorkspaceDefaultSettings, ProjectAiAssignment, ProjectMemberAssignment, RepositoryRegistry, TechStack, Environment, Dependency, Release) + `MySqlProfileCompletenessProvider` + 3 port adapters (AiConsumerCodeResolver, WorkspaceMemberChecker, GovernanceVersionChecker).

**Infrastructure — `src/Infrastructure/Http/CurlHttpClient.php`:** minimal PSR-18 client (cURL) for LINE Login OIDC — composer has no PSR-18 implementation; no new dependency added.

**Controllers (new):** AiProvider, GitProvider, ProjectTemplate, WorkspaceDefaultSettings, TeamAssignment, TechStack, Environment, Dependency, Release, AiAssignment, Repository.
**Controllers (rewritten to match Slim/ApiResponse conventions):** Milestone, ProjectStructure, ProjectStructureHistory, LineLogin, Invitation, Claim.
**Controllers (updated):** ProjectController (creation now runs through the pipeline), AiConsumerController (provider_id required — `PROVIDER_REQUIRED`).

## 3. Fixes to previously committed Phase 1 code (important for reviewers)

The prior "M1 Phase 1 Complete" commit contained code that could not run:

1. **Routes missing** — no M1 endpoints were registered in `routes.php` (milestones, structure, LINE auth, etc. were unreachable). All routes are now registered with their permission middleware.
2. **Wrong `ApiResponse` signature** — Milestone/Structure/Auth controllers called `ApiResponse::success(data, message)` / `error(code, ...)` while the real envelope is `success(Response, data, meta, status)` / `error(Response, code, message, details, status)`. Rewritten.
3. **Workspace context never set** — `WorkspaceContextMiddleware` was pass-through, so every workspace-scoped repository would resolve with `workspace_id = null`. It now sets `current_workspace_id` in the DI container per request.
4. **`InvitationService` depended on `lcobucci/jwt`** (not installed) — replaced with HMAC-SHA256 signed claim tokens (24h expiry, `APP_SECRET` from env) and the claim now actually binds `line_user_id` via `UserRepository::updateLineInfo`.
5. **`ProjectController::create` called `ProjectRepository::create` without `workspace_id`** (fatal at runtime) — replaced by `ProjectCreationPipeline`.
6. **`MySqlUserRepository` had a duplicated `findByLineUserId`** (fatal parse error) — deduplicated.
7. **`dependencies.php` had dangling DI entries** referencing non-existent classes (RevisionService etc.) — cleaned; revision workflow remains Phase 2 scope.
8. `AuditTrailTest` strict JSON string comparison fixed to decoded comparison.

## 4. CTO Constraints applied

1. **Authentication: LINE Login only** — no other IdP designed or scaffolded.
2. **Source control: GitLab only** — `git_providers` seeded with gitlab only; non-GitLab URLs rejected at registration.
3. **Notification: Telegram only (future)** — no notification code in Phase 1; when built it will be Telegram-only. No multi-provider abstraction was added.

## 5. Test Results

Full suite: **86 tests, 162 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0055 applied).
See `docs/m1/TEST-RESULTS-M1-R1.txt`.

## 6. Not in this package (Phase 2 scope, unchanged)

Revision/Review workflow (`revisions` tables exist, services/endpoints deferred to Phase 2), web UI (Q09 still open), notifications, GitLab API automation.
