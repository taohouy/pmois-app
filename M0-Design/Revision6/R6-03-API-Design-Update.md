# PMOIS v2 — API Endpoint Design Update — Revision 6

**Supersedes:** `M0-Design/04-API-Endpoint-Design.md` §4 (endpoint summary only). Response envelope, auth model, error-code style, idempotency rules of R5 §1–3, §5–6 remain authoritative.

- Base path stays `/api/v1` (no v2 — per R5 §1 and ADR-0001).
- Envelope stays `{success, data, error{code,message,details}, meta{timestamp}}` (exact existing `ApiResponse.php`).
- Authorization stays `permission_code` via `RequiresPermissionMiddleware` → `PermissionResolver`; token scopes stay informational.

## 1. New Endpoints (R6 resources only)

### 1.1 Registries (global, platform-admin managed)

| Method | Endpoint | permission | Notes |
|---|---|---|---|
| `GET` | `/api/v1/ai-providers` | any authenticated | List providers (for Agent form dropdown) |
| `POST`/`PATCH` | `/api/v1/ai-providers` | `is_platform_admin` | Manage global registry |
| `GET` | `/api/v1/git-providers` | any authenticated | List git providers |
| `POST`/`PATCH` | `/api/v1/git-providers` | `is_platform_admin` | Manage global registry |

`GET/POST/PATCH /api/v1/ai-consumers` (existing pattern) now **requires `provider_id`** on write — new `VALIDATION_ERROR` detail otherwise.

### 1.2 Project Team Registry

| Method | Endpoint | permission | Notes |
|---|---|---|---|
| `GET` | `/api/v1/projects/{id}/team-assignments` | `project.view` | Active + history (`?include=revoked`) |
| `POST` | `/api/v1/projects/{id}/team-assignments` | `project.team.manage` | Assign human member (role from mapped roles) |
| `PATCH` | `/api/v1/team-assignments/{id}/revoke` | `project.team.manage` | Revoke (row retained) |

Writes here maintain the `project_members` authorization projection (R6-06 §2 rule).

### 1.3 Project Technical Registries

| Method | Endpoint | permission |
|---|---|---|
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/v1/projects/{id}/tech-stack` | `project.view` / `project.techstack.manage` |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/v1/projects/{id}/environments` | `project.view` / `project.environment.manage` |
| `GET`/`POST`/`DELETE` | `/api/v1/projects/{id}/dependencies` | `project.view` / `project.dependency.manage` |
| `GET` | `/api/v1/dependencies/graph?workspace_id=` | `project.view` | Portfolio dependency graph (nodes + edges) |
| `GET`/`POST`/`PATCH` | `/api/v1/projects/{id}/releases` | `project.view` / `project.release.manage` |
| `GET` | `/api/v1/projects/{id}/profile-completeness` | `project.view` | Computed checklist + percent |

### 1.4 Templates & Workspace Defaults

| Method | Endpoint | permission | Notes |
|---|---|---|---|
| `GET`/`POST`/`PATCH` | `/api/v1/project-templates` | `project.view` (ws) / `project.template.manage` | Scoped to workspace |
| `POST` | `/api/v1/project-templates/{id}/preview` | `project.template.manage` | Dry-run: what creation would generate |
| `GET`/`PUT` | `/api/v1/workspaces/{id}/default-settings` | `project.view` (ws) / `workspace.settings.manage` | 1:1 with workspace |

### 1.5 Updated existing endpoint

`POST /api/v1/projects` — body extended (all new fields optional, see R6-05):

```json
{
  "workspace_id": 1, "name": "...", "code": "...", "abbreviation": "...",
  "description": null, "parent_project_id": null,
  "development_mode": "manual",
  "cto_user_id": 7, "dev_user_ids": [12, 15],
  "template_id": 3
}
```

Response `data` now also includes `profile_completeness_percent` and `source_template_id`.

## 2. Repository endpoint update

`POST /api/v1/repositories` body: `git_provider_id` replaces the GitLab assumption; `repository_url` replaces `gitlab_url` (R6-07). Old `gitlab_url` field name returns `VALIDATION_ERROR` with a migration hint in `details`.

## 3. New Error Codes

| Error code | HTTP | Condition |
|---|---|---|
| `TEMPLATE_NOT_FOUND` | 404 | `template_id` not in this workspace / inactive |
| `TEMPLATE_PAYLOAD_INVALID` | 422 | Template payload fails contract validation (R6-05 §3) |
| `DEPENDENCY_CIRCULAR` | 409 | `depends_on` chain would create a cycle |
| `DEPENDENCY_SELF` | 409 | project depends on itself |
| `DEPENDENCY_WORKSPACE_MISMATCH` | 409 | Related project in a different workspace |
| `ASSIGNMENT_ALREADY_ACTIVE` | 409 | Active assignment exists for (project, user) |
| `PROVIDER_REQUIRED` | 422 | `ai_consumers` write without `provider_id` |
| `WORKSPACE_SETTINGS_LOCKED` | 403 | Non-admin attempts `workspace.settings.manage` |

## 4. New Permission Codes

See `R6-02-Database-Changes.md` §3 (7 codes: `project.team.manage`, `project.dependency.manage`, `project.release.manage`, `project.environment.manage`, `project.techstack.manage`, `project.template.manage`, `workspace.settings.manage`).

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
