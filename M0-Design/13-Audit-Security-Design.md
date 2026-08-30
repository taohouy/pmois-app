# PMOIS v2 — Audit / Security Design (M0 Item 13)

**Revision 1** — grounded in the existing `audit_trails` table (`0010_create_audit_trails.sql`) and the existing `AuthTokenMiddleware` / `PermissionResolver` implementation. This file was missing from the initial M0 package; created fresh for this revision.

## 1. Design Principle

No new dedicated audit table is introduced. `audit_trails` is already generic (`entity_type`, `entity_id`, `action`, `before_value` JSON, `after_value` JSON, immutable — no `updated_at`). M0-specific events (LINE login, structure change, CTO review decision, token lifecycle) all write into this **one** table using consistent `action` / `entity_type` values. This keeps one query surface for "show me everything that happened," per the CTO instruction not to invent parallel structures without justification.

`project_structure_history` (see `02-MySQL-ER-Diagram-Database-Table-Design.md` §3.3) is the one exception — kept as a specialized index for fast hierarchy-timeline lookups, and every write to it is also mirrored into `audit_trails`.

---

## 2. Audit Event Catalogue (all use existing `audit_trails` columns)

| `action` | `entity_type` | Written when | `before_value` / `after_value` |
|---|---|---|---|
| `LOGIN` | `user` | LINE Login success | `null` / `{line_user_id, ip}` |
| `LOGIN_DENIED` | `user` | LINE user has no active `workspace_members` row | `null` / `{line_user_id, reason}` |
| `TOKEN_CREATED` | `api_token` | New token issued | `null` / `{scopes, project_id, expires_at}` |
| `TOKEN_REVOKED` | `api_token` | Token revoked | `{status:'active'}` / `{status:'revoked'}` |
| `PROJECT_CREATED` | `project` | CEO/PMO creates project | `null` / full initial row |
| `STRUCTURE_CHANGE` | `project` | Move workspace / change parent / promote | old vs new `{workspace_id, parent_project_id}` (mirrors `project_structure_history`) |
| `PROGRESS_UPDATED` | `project` | CTO/PMO updates `progress_percent` or `health` | old vs new value |
| `MILESTONE_CLOSED` / `MILESTONE_OPENED` | `milestone` | CTO decision | old vs new `status` |
| `REVISION_SUBMITTED` | `revision` | Dev submits revision | `null` / revision snapshot |
| `REVISION_REVIEWED` | `revision` | CTO approves/rejects | `{status:'submitted'}` / `{status:'cto_approved'|'cto_rejected'}` |
| `REVISION_COMMITTED` | `revision` | Dev records commit/push | old vs new `{commit_hash, branch, push_status}` |
| `GOVERNANCE_ADOPTED` | `governance_adoption` | New project bound to governance baseline | `null` / adoption row |
| `AI_ASSIGNED` / `AI_REVOKED` | `project_ai_assignment` | CTO/PMO manages AI assignment | old vs new |

All rows populate the **existing** columns only: `workspace_id`, `user_id`, `action`, `entity_type`, `entity_id`, `before_value`, `after_value`, `ip_address`, `user_agent`, `created_at`. No schema change to `audit_trails` is required.

---

## 3. Authentication Security (LINE Login)

### 3.1 Flow

```
1. User clicks "Login with LINE" (Web UI)
2. Redirect to LINE OIDC authorize endpoint (scope: openid profile email)
3. LINE redirects back with authorization code
4. Backend exchanges code → id_token + access_token (server-side only)
5. Validate id_token signature (LINE public key), audience, expiry
6. Lookup users.line_user_id = sub claim
   - Found + has active workspace_members row → issue PMOIS session
   - Found but no active workspace_members row → 403, audit_trails: LOGIN_DENIED
   - Not found → 403, audit_trails: LOGIN_DENIED (no auto-provisioning without an
     existing workspace_members invitation — fail-closed, per M0 Item 13)
```

### 3.2 Fail-closed rule (exact mechanism)

There is **no separate `authorized_users` table**. "Authorized" = a `users` row whose `line_user_id` is set **and** who has at least one `workspace_members` row with `status='active'`. An admin (CEO/PMO, `is_platform_admin=1`) pre-creates the `users` row (or the invite flow does) and adds the `workspace_members` row **before** the person's first LINE login; login itself never grants access on its own.

### 3.3 Session vs. API Token — kept distinct, not merged

- **Web UI session** (after LINE login): server-side session cookie (HttpOnly, Secure, SameSite=Lax) identifying `user_id`. Used for browser requests only.
- **API access** (Dev/CTO/AI scripts, existing `api_tokens` table): unchanged — Bearer token, `token_hash` lookup, exactly as implemented today in `AuthTokenMiddleware`. LINE Login does **not** replace or reissue `api_tokens`; it is additive (Web UI auth only), per constraint "LINE ใช้เพื่อ Authentication เท่านั้น."

---

## 4. Authorization Security

Per CTO Review §6, authorization is **not** scope-based. Restated here for the audit/security document:

- `api_tokens.scopes` is present in schema but is **informational only** — never the deciding factor for whether an action is permitted.
- Every protected action resolves permission through `PermissionResolver::can(userId, workspaceId, projectId, permissionCode)`:
  1. `permission_code = 'workspace.create'` → shortcut, checks `users.is_platform_admin` only.
  2. Else, if `projectId` given → check `project_members` (override).
  3. Else fall back to `workspace_members`.
  4. Resolve `role_id → role_permissions.permission_code`.
- New M0 permission codes needed (added to `role_permissions` seed data, no schema change): `project.structure.update`, `project.progress.update`, `project.promote`, `milestone.close`, `revision.review`, `repository.manage`, `ai_assignment.manage`. Full grant matrix in revised `12-Role-Permission-Matrix.md`.

See `05-Auth-Authorization-Design.md` (revised) for the full Authentication vs Authorization split.

---

## 5. Token Lifecycle Security (existing `api_tokens`, unchanged mechanics)

| Property | Existing Behaviour |
|---|---|
| Storage | `token_hash` (SHA-256 of raw token) — raw token never stored |
| Binding | `workspace_id` always; `project_id` optional (NULL = workspace-level); `ai_consumer_id` optional (NULL = human) |
| Expiration | `expires_at`, checked in `AuthTokenMiddleware` |
| Revocation | `status='revoked'`, checked in `AuthTokenMiddleware` |
| Last used | `last_used_at`, updated on every authenticated request |
| Isolation | `ProjectScopeMiddleware` — project-scoped token can only hit routes whose `{project_id}` route arg matches `token_project_id` |

No changes proposed here — this mechanism already satisfies M0 Item 11 ("Token ต้องรองรับ Project Binding / Expiration / Revocation / Last Used"). Rotation is a manual "revoke + create new" today; automatic rotation is listed as a Phase 2 proposal (Section 7), not a M0 requirement.

---

## 6. Secrets Handling

- GitLab credentials: `repositories.credential_reference` stores a pointer only (see `02-...md` §3.5); the actual secret lives outside MySQL (environment variable at minimum for M0; a dedicated secret manager is an optional future proposal, not a M0 dependency — see `16-Proposed-Technology-Stack-with-Rationale.md`).
- Webhook signature secrets (if/when GitLab webhooks are implemented) are environment variables, never DB rows.
- No plaintext password fallback: `users.password_hash` for `auth_provider='line'` accounts stays `NULL`.

---

## 7. Explicitly Marked as Future Proposal (not M0 mandatory)

Per CTO Review §7/§8, the following are **not** committed baseline for M0/M1 — listed here only as candidates for later phases:

| Item | Status |
|---|---|
| Fixed 7-year audit retention | Proposal — actual retention period is a CEO/Legal decision, not fixed by this design |
| Fixed RPO/RTO/SLA numbers | Proposal — depends on actual hosting decision, not yet made |
| Dedicated secret manager (Vault, AWS Secrets Manager) | Proposal — environment variables sufficient for M0 |
| Automatic token rotation | Proposal — manual revoke/reissue sufficient for M0 |
| Enterprise observability stack (OpenTelemetry/Grafana/Loki) | Proposal — see revised Technology Stack doc |

---

*Document Version: 1.0 (created in Revision 1 — file was missing from initial package)*
*Status: Revised per CTO Review*
*Date: 2026-08-30*