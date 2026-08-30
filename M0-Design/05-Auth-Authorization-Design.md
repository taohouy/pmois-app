# PMOIS v2 — Authentication & Authorization Design (M0 Item 5, 7)

**Revision 1** — corrects the core architecture error flagged in CTO Review §6: token scopes are **not** the authorization mechanism. Authentication and Authorization are two separate, existing mechanisms in PMOIS; this revision documents and extends them without silently replacing either.

## 1. The Two Separate Concerns (do not merge)

| Concern | Mechanism | Where enforced |
|---|---|---|
| **Authentication** — "who/what is making this request" | LINE Login (Web UI) → session; `api_tokens.token_hash` (API/scripts/AI) → `user_id`/`workspace_id`/`project_id`/`ai_consumer_id` resolved | `AuthTokenMiddleware` (existing, unchanged) |
| **Authorization** — "is this identity allowed to do this action" | `PermissionResolver::can(userId, workspaceId, projectId, permissionCode)` against `project_members` (override) → `workspace_members` (fallback) → `role_permissions` | `RequiresPermissionMiddleware` (existing, unchanged) |

`api_tokens.scopes` (VARCHAR, existing column) is **not** read by either middleware today and must **not** become the authority decision in M0/M1. It remains available as an optional *additional* narrowing layer for a future phase (e.g. "this AI token may only call `revision.create`, even though the underlying role would allow more") — but that is a Phase 2 proposal, not part of this baseline, and would need its own ADR before implementation.

---

## 2. LINE Login (Web UI Authentication Only)

### 2.1 Flow

```
1. User clicks "Login with LINE"
2. Redirect → LINE OIDC authorize (scope: openid profile email)
3. LINE redirects back with authorization code
4. Backend exchanges code → id_token + access_token
5. Validate id_token (signature, aud, exp)
6. Lookup users.line_user_id = sub
   → found + active workspace_members row → create session
   → otherwise → 403, fail-closed (see 13-Audit-Security-Design.md §3.2)
```

### 2.2 Schema change required

`users` table gets `line_user_id`, `line_display_name`, `avatar_url`, `auth_provider`; `password_hash` becomes nullable — see `02-MySQL-ER-Diagram-Database-Table-Design.md` §3.1. Existing password-based accounts (`auth_provider='local'`) are untouched.

### 2.3 What LINE Login does *not* do

- Does not determine role or permission — that is entirely `workspace_members.role_id` / `project_members.role_id`, set by an admin, independent of LINE.
- Does not replace `api_tokens` — API/AI access continues exactly as today.

---

## 2.4 Account Linking / Invitation Flow (NEW — addresses CTO Review §2)

**Problem:** Fail-closed requires `line_user_id` + active `workspace_members` row *before* first login. How does admin/PMO get the user's LINE `sub` to pre-create the `users` row?

**Solution: Invitation Flow (Admin-initiated, no LINE Notify dependency)**

**Mechanism: Secure Claim Link (signed JWT) — works with existing schema**

The existing `workspace_members` status enum only has `active` / `removed` (migration `0005`). No `pending_invitation` state exists. To avoid schema changes in M0, the invitation uses a **claim token** that binds the LINE `sub` at claim time:

```
1. Admin/PMO (is_platform_admin=1) creates an invitation:
   - Creates `users` row with placeholder `line_user_id` = NULL, `auth_provider='line'`
   - Creates `workspace_members` row with `status='active'`, role assigned by admin
   - Generates a secure **claim token** (signed JWT: workspace_id, user_id, expiry=24h)
   - Delivers claim URL to user via existing channels (email, internal message, manual share — no LINE Notify)

2. User clicks claim link → LINE Login (OIDC)
   - User completes LINE Login → LINE returns `sub` (line_user_id) + profile
   - Backend validates claim token (signature, expiry, workspace_id, user_id match)
   - Backend updates `users` row: `line_user_id` = sub, `auth_provider='line'`, fills profile from LINE
   - Backend records audit_trails: action='LINE_LINK', entity_type='user'
   - User now has valid `line_user_id` + active `workspace_members` → normal LINE login works
```

**Key constraints maintained:**
- Fail-closed unchanged: user must have `line_user_id` + active `workspace_members` row to login
- No auto-grant: membership (`workspace_members`) must be explicitly created by admin before claim works
- Authorization still solely from PMOIS membership/role (`workspace_members` + `project_members`)
- No parallel authorization table — reuses existing `workspace_members` + `project_members`
- No LINE Notify dependency — claim link can be shared via any channel (email, chat, manual share)
- No new `status` enum value needed — uses existing `active` status

---

## 2.3 What LINE Login does *not* do

## 3. API Authentication (unchanged mechanism, restated)

```
Authorization: Bearer {raw_token}
```

`AuthTokenMiddleware`:
1. SHA-256 hash the raw token → look up `api_tokens.token_hash`.
2. Check `status='active'` and `expires_at`.
3. Attach `user_id`, `workspace_id`, `token_project_id` (nullable), `ai_consumer_id` (nullable) to the request.
4. Update `last_used_at`.

`ProjectScopeMiddleware` then enforces: if `token_project_id` is set, the requested route's `{project_id}` must match exactly (existing, unchanged, satisfies M0 Item 11's "Project Binding").

**This revision does not change any of the above** — it only removes the *incorrect* idea (from the initial draft) that scopes listed on the token decide what the token may do.

---

## 4. Authorization (existing `PermissionResolver`, extended with new permission codes)

```php
PermissionResolver::can(userId, workspaceId, projectId, permissionCode): bool
// 1. permissionCode === 'workspace.create' → users.is_platform_admin only
// 2. projectId given → project_members override
// 3. else → workspace_members fallback
// 4. resolve role_id → role_permissions.permission_code
```

### 4.1 New permission codes introduced by M0 (added to `role_permissions` seed data — no schema change)

| Permission code | Purpose |
|---|---|
| `project.structure.update` | Move workspace / change parent / promote |
| `project.progress.update` | Update `progress_percent` / `health` |
| `milestone.close` / `milestone.open` | CTO milestone decision |
| `revision.create` | Dev submits revision / records commit |
| `revision.review` | CTO approve/reject |
| `repository.manage` | Register/update GitLab repository metadata |
| `ai_assignment.manage` | Assign/revoke AI agent on a project |

### 4.2 Role → Permission Mapping (see full matrix in `12-Role-Permission-Matrix.md`)

Real roles are `ADMIN`, `MEMBER`, `SENIOR_DEV`, `VIEWER`, `CTO`, `PMO_REVIEWER` (plus the `users.is_platform_admin` flag). There is no literal `CEO` role row; CEO/Portfolio-Owner authority is `is_platform_admin=1` combined with `PMO_REVIEWER`-equivalent grants. See `12-Role-Permission-Matrix.md` for the exact grant table — this document only states the mechanism, not the full grant list, to avoid duplicated/inconsistent copies.

---

## 5. Fail-Closed Enforcement Summary

| Layer | Fail-closed behaviour |
|---|---|
| LINE Login | No matching `users.line_user_id` + active `workspace_members` → 403, no session issued |
| API token | Invalid/expired/revoked hash → 401 (existing) |
| Authorization | No `role_permissions` row for the resolved role + `permission_code` → 403 (existing) |
| Project scope | Project-scoped token hitting a mismatched/absent `project_id` route arg → 403 (existing) |

---

## 6. If a Different Architecture Is Desired

If Product/CTO later decides that token scopes *should* become a primary or additional authorization gate (e.g. for finer-grained AI agent restriction), that is a legitimate future direction — but it is an **Architecture Decision** requiring its own ADR and explicit CTO approval, not something this M0 revision changes silently. This document intentionally stops at "document + extend the existing mechanism."

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — corrects Auth vs Authz architecture error*
*Date: 2026-08-30*