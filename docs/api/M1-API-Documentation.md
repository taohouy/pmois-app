# PMOIS v2 — M1 API Documentation (Phase 1 / R6 Phase 1.5)

Base URL `/api/v1` • Bearer token auth (`api_tokens`) • Envelope: `{success, data, error{code,message,details}, meta{timestamp}}` • Authorization ผ่าน `permission_code` (token scopes เป็น informational เท่านั้น)

Guest endpoints (ไม่ต้องมี token): `/auth/line*`, `/auth/error`, `/claim/{token}` (GET), `/api/v1/health`

---

## 1. Auth — LINE Login only (CTO Constraint #1) — M1 R2 Security

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/auth/line` | Guest | สร้าง one-time state (persisted, fingerprint-bound, TTL 10 นาที) + nonce → `{auth_url, state}` + Set-Cookie `pmois_oauth_fp` |
| GET | `/auth/line/callback?code=&state=` | Guest + cookie | Verify state (exists/one-time/not-expired/fingerprint) → exchange code → **verify id_token ผ่าน LINE verify endpoint + iss/aud/exp/iat/nonce** → resolve PMOIS user (fail-closed: active user + active membership) → สร้าง PMOIS session → Set-Cookie `pmois_session` (HttpOnly, 8h) |
| POST | `/auth/logout` | Session | Revoke session + เคลียร์ cookie |
| GET | `/auth/error` | Guest | 403 fail-closed page |
| POST | `/api/v1/invitations` | `workspace.create` (platform admin) | สร้าง placeholder user + active membership + claim token (HMAC-signed, 24h, `APP_SECRET`) |
| GET | `/claim/{token}` | Guest + cookie | **เริ่ม claim**: validate token → persist claim state → `{auth_url, state}` (binding เกิดที่ callback ด้วย **verified sub เท่านั้น** — ไม่มี endpoint รับ `line_user_id` จาก client) |

**Auth error codes:** `STATE_INVALID` / `STATE_REUSED` / `STATE_EXPIRED` / `STATE_MISMATCH` (401) • `ID_TOKEN_INVALID` / `OAUTH_EXCHANGE_FAILED` / `CLAIM_TOKEN_INVALID` / `CLAIM_ALREADY_USED` / `LINE_ALREADY_BOUND` (401) • `UNAUTHORIZED_IDENTITY` (403)

**การยืนยันตัวตนต่อ API:** Bearer `api_tokens` (เดิม) **หรือ** `pmois_session` cookie (session จาก LINE Login — ตรวจ hash + expiry + revoked + user active + active membership ทุก request)

## 2. Projects & Hierarchy

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/projects` | `project.view` |
| POST | `/api/v1/projects` | `project.create` (ADMIN/CTO) |
| PUT | `/api/v1/projects/{id}/close` | `project.close` |
| PATCH | `/api/v1/projects/{id}/structure` | `project.structure.update` — body: `{action: move_workspace\|change_parent\|promote, new_workspace_id?, new_parent_id?, reason?}` |
| GET | `/api/v1/projects/{id}/structure-history` | `project.view` |

**POST /projects body (CEO กรอกน้อยที่สุด):** `name`, `code` (required); `cto_user_id`, `dev_user_id`, `development_mode`, `template_id`, `parent_project_id`, `abbreviation`, `description`, `start_date` (optional — resolve จาก Workspace Defaults เมื่อไม่ส่ง)
**Response 201:** project data + `profile_completeness_percent` + `applied{template, workspace_defaults_used, governance_adoption_id}` + `project_token` (ครั้งเดียว เมื่อ template ระบุ `auto_create_project_token`)

## 3. Milestones

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/projects/{project_id}/milestones` | `project.view` |
| POST | `/api/v1/projects/{project_id}/milestones` | `milestone.create` — `{code, title, planned_date?}` |
| PATCH | `/api/v1/milestones/{id}/title` | `milestone.update` — `{title}` |
| PATCH | `/api/v1/milestones/{id}/close` | `milestone.close` (CTO) |
| PATCH | `/api/v1/milestones/{id}/reopen` | `milestone.open` (CTO) |

## 4. Team Registry (history) & AI Assignment

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/projects/{project_id}/team-assignments` | `project.view` |
| POST | `/api/v1/projects/{project_id}/team-assignments` | `project.team.manage` — `{user_id, role_id, note?}` |
| PATCH | `/api/v1/team-assignments/{id}/revoke` | `project.team.manage` |
| GET | `/api/v1/projects/{project_id}/ai-assignments` | `project.view` |
| POST | `/api/v1/projects/{project_id}/ai-assignments` | `ai_assignment.manage` — `{ai_consumer_id, role_id, purpose?}` |
| PATCH | `/api/v1/ai-assignments/{id}/revoke` | `ai_assignment.manage` |

Revoke = mark history (row retained) + sync authorization projection (`project_members`).

## 5. GitLab Repository Registry (GitLab only)

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/projects/{project_id}/repositories` | `project.view` |
| POST | `/api/v1/projects/{project_id}/repositories` | `repository.manage` — `{repository_url (GitLab only), repository_name, repository_type?, default_branch?, development_branch?, release_branch?, production_branch?, credential_reference?}` |
| PATCH | `/api/v1/repositories/{id}` | `repository.manage` |

## 6. R6 Registries (Tech Stack / Environment / Dependency / Release)

| Method | Path | Permission |
|---|---|---|
| GET/POST/DELETE | `/api/v1/projects/{project_id}/tech-stack[/{id}]` | `project.view` / `project.techstack.manage` — `{layer, name, version?, notes?}` |
| GET/POST/DELETE | `/api/v1/projects/{project_id}/environments[/{id}]` | `project.view` / `project.environment.manage` — `{environment: development\|uat\|production, name, url?, runtime?, php_version?, database_engine?, deploy_path?, credential_reference?}` — **ไม่รับ secret-like fields** |
| GET/POST/DELETE | `/api/v1/projects/{project_id}/dependencies[/{id}]` | `project.view` / `project.dependency.manage` — `{related_project_id, dependency_type: depends_on\|blocked_by, note?}` |
| GET | `/api/v1/dependencies/graph` | `project.view` — `{nodes[], edges[], warnings[]}` |
| GET/POST | `/api/v1/projects/{project_id}/releases` | `project.view` / `project.release.manage` — `{release_type: alpha\|beta\|rc\|production\|hotfix, version_label, repository_id?, environment_id?, milestone_id?, release_notes?}` |
| PATCH | `/api/v1/releases/{id}/transition` | `project.release.manage` — `{status}` (state machine: planned→in_progress→released→rolled_back/cancelled) |

## 7. Templates & Workspace Defaults

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/project-templates[/{id}]` | `project.view` |
| POST/PUT | `/api/v1/project-templates[/{id}]` | `project.template.manage` — `{code, name, is_default?, payload}` |
| PUT | `/api/v1/project-templates/{id}/set-default` | `project.template.manage` |
| GET/PUT | `/api/v1/workspaces/{id}/default-settings` | `workspace.view` / `workspace.settings.manage` — `{default_cto_user_id?, default_dev_user_id?, default_governance_version_id?, default_git_provider_id?, default_project_template_id?, default_development_mode?, default_permission_preset?}` |

**Template payload contract:** `milestones[]{code,title,planned_offset_days?}`, `governance{governance_version_id|null}`, `ai_agents[]{ai_consumer_code (ต้องมีใน Registry), role, purpose?}`, `tech_stack[]{layer,name,version?}`, `environments[]`, `auto_create_project_token`, `module_settings{}`.

## 8. Global Registries

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/ai-providers`, `/api/v1/git-providers` | ใดๆ (auth) |
| POST/PUT | `/api/v1/ai-providers[/{id}/status]`, `/api/v1/git-providers[/{id}/status]` | is_platform_admin (check ใน controller) |
| POST | `/api/v1/ai-consumers` | `ai_consumer.manage` — **`provider_id` required** (`PROVIDER_REQUIRED`) |

## 9. New Error Codes

`PROVIDER_REQUIRED` 422 • `TEMPLATE_NOT_FOUND` 404 • `TEMPLATE_PAYLOAD_INVALID` 422 • `DEPENDENCY_CIRCULAR` / `DEPENDENCY_SELF` / `DEPENDENCY_WORKSPACE_MISMATCH` 409 • `ASSIGNMENT_ALREADY_ACTIVE` 409
