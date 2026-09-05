# PMOIS v2 — Database Changes & Migration Plan — Revision 6

**Baseline:** migrations `0001`–`0042` applied (M1 Phase 1, Commit `5cbd184`). R6 migrations start at `0043`. Each migration ships with a `.rollback.sql` per repo convention.

## 1. Migration List

| Migration | Target | Type | Content | Ref |
|---|---|---|---|---|
| `0043_create_ai_providers` | `ai_providers` | NEW + SEED | Table + 5 provider rows (openai, anthropic, google, microsoft, human) | R6-01 §2.1 |
| `0044_alter_ai_consumers_add_provider_id` | `ai_consumers` | ALTER + BACKFILL | `provider_id` FK; backfill existing rows → `human` provider | R6-01 §2.2 |
| `0045_create_git_providers` | `git_providers` | NEW + SEED | Table + `gitlab` row | R6-01 §2.4 |
| `0046_alter_repositories_add_provider_rename_url` | `repositories` | ALTER | `git_provider_id` FK; `gitlab_url` → `repository_url` | R6-01 §2.5 |
| `0047_create_project_member_assignments` | `project_member_assignments` | NEW | Team assignment ledger | R6-01 §2.3 |
| `0048_create_project_technology_stack` | `project_technology_stack` | NEW | Tech stack registry | R6-01 §2.6 |
| `0049_create_project_environments` | `project_environments` | NEW | Environment registry | R6-01 §2.7 |
| `0050_create_project_dependencies` | `project_dependencies` | NEW | Dependency graph | R6-01 §2.8 |
| `0051_create_project_releases` | `project_releases` | NEW | Release registry | R6-01 §2.9 |
| `0052_create_project_templates` | `project_templates` | NEW | Project template | R6-01 §2.10 |
| `0053_alter_projects_add_source_template_id` | `projects` | ALTER | `source_template_id` FK | R6-01 §2.12 |
| `0054_create_workspace_default_settings` | `workspace_default_settings` | NEW | Workspace defaults | R6-01 §2.11 |
| `0055_seed_r6_permission_codes` | `role_permissions` | SEED | 7 new permission codes (below) | R6-03 §3 |

## 2. Order Constraints

```
0043 (ai_providers)   → 0044 (ai_consumers.provider_id)
0045 (git_providers)  → 0046 (repositories.git_provider_id) → 0054 (wds.default_git_provider_id)
0052 (project_templates) → 0053 (projects.source_template_id) → 0054 (wds.default_project_template_id)
0049 (project_environments) → 0051 (project_releases.environment_id)
0037 (repositories, existing) → 0051 (project_releases.repository_id)
0055 (permission seed) — last, no FK dependency but kept last by convention
```

All other migrations are independent and may run in listed order. **All 13 must run up/down cleanly** (M1 Plan §6 test gate).

## 3. New Permission Codes (`0055_seed_r6_permission_codes`)

Extends `12-Role-Permission-Matrix.md` §4 with the R6 registries. Same insert pattern as migration `0042` (`WHERE NOT EXISTS`, idempotent).

| Permission code | ADMIN | CTO | SENIOR_DEV | MEMBER | PMO_REVIEWER | VIEWER |
|---|---|---|---|---|---|---|
| `project.team.manage` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `project.dependency.manage` | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| `project.release.manage` | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| `project.environment.manage` | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| `project.techstack.manage` | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| `project.template.manage` | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `workspace.settings.manage` | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |

Read access to all new registries uses the existing `project.view` / `project.view_any` pattern — no separate `.view` codes needed. `ai_provider.manage` / `git_provider.manage` (global registries) are restricted to `is_platform_admin` in the service layer (same rule as `workspace.create`), **no `role_permissions` rows** — consistent with `PermissionResolver` rule 1.

> **Q11 note (already closed):** `project.create` remains ADMIN/CTO only (migration 0042). R6 does not reopen it — the CEO persona is `is_platform_admin`, which bypasses role checks for creation.

## 4. Data Migration / Backfill Notes

1. **`ai_consumers` backfill (0044):** all existing consumers → provider `human`. PMO/CTO reclassify real AI agents afterwards (operational step, documented in R6-06 §3).
2. **`repositories` backfill (0046):** `git_provider_id = gitlab` for all existing rows; `gitlab_url` renamed in place — no data loss.
3. **`project_members` → `project_member_assignments` bootstrap (optional, 0047 or post-deploy script):** insert one active assignment row per existing `project_members` row (`assignment_source='direct'`, `assigned_by = projects.owner_user_id`) so history starts complete. Recommended as a one-time post-migration script, not a hard migration dependency.
4. **No destructive changes.** The only column rename is `repositories.gitlab_url → repository_url`; application code that references `gitlab_url` (RepositoryController/RepositoryService from Phase 2 scope — not yet written) must use the new name. Nothing in the implemented Phase 1 code reads `repositories` columns (table creation only).

## 5. Rollback Order

Reverse numerical order (`0055` → `0043`), because FK dependencies point from higher-numbered migrations to lower ones.

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
