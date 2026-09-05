# PMOIS v2 — Workspace Default Settings Design — Revision 6

**New design** (CTO Requirement #11). No R5 counterpart.

## 1. Model

`workspace_default_settings` [R6-NEW] — **1:1 with workspace** (`uq_wds_workspace`), typed columns with FKs for referential integrity. DDL in `R6-01` §2.11.

| Default | Column | Consumed by |
|---|---|---|
| Default CTO | `default_cto_user_id` | Project creation (team slot pre-fill) |
| Default Dev | `default_dev_user_id` | Project creation (team slot pre-fill) |
| Default Governance | `default_governance_version_id` | Governance auto-binding on creation |
| Default Git Provider | `default_git_provider_id` | First repository registration pre-fill |
| Default Permission | `default_permission_preset` (`standard`/`restricted`) | Which roles new projects pull in; preset map is code-level config |
| Default Project Template | `default_project_template_id` | Creation pipeline step 2 |
| Default Development Mode | `default_development_mode` | Creation pre-fill |

## 2. Resolution order (single rule, restated)

**Explicit request value → workspace default → template payload → system fallback.** Defaults are pre-fills, never hard locks — CEO can override any of them at creation time; the required CTO/Dev must resolve from request or defaults or creation fails validation.

## 3. API & UI

- `GET/PUT /api/v1/workspaces/{id}/default-settings` — permission `workspace.settings.manage` (ADMIN / `is_platform_admin` only; CTO excluded by design — this is a workspace-owner-level control).
- UI: `/workspaces/:id/settings` screen (R6-04 §1.2). Every FK field renders as a picker constrained to valid workspace members / published governance versions / active providers / active templates.

## 4. Validation rules (service layer)

- Default CTO/Dev user must hold an **active `workspace_members` row** in this workspace.
- Default governance version must be `published` and belong to a `governance_records` row in this workspace.
- Default template must be `active` and workspace-scoped; setting it as default also clears `is_default` from the previous template.
- `default_development_mode` must be one of the three enum values (DB-enforced).
- All writes → `audit_trails` (action=`WORKSPACE_DEFAULTS_UPDATED`, before/after JSON).

## 5. Effect on the "CEO fills minimal input" principle

With defaults configured, a CEO creating a project pre-selects only **Workspace (implicit), Name, Code** — CTO, Dev, Mode, Governance, and Template all arrive from workspace defaults, making the minimal-input principle operational rather than aspirational. Workspaces that skip configuration behave exactly as R5 (all 7 fields requested).
