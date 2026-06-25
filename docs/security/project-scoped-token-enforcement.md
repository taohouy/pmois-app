# Project-Scoped Token Enforcement

**Document type:** Security Design  
**Document code:** SEC-project-scoped-token-enforcement  
**Version:** 1  
**Status:** Active  
**Review status:** Approved  
**Reviewed by:** CTO  
**Approval status:** Approved  
**Approved by:** CTO  
**Date:** 2026-06-25  
**Last updated:** 2026-06-25  
**Author:** Claude Code (on behalf of CTO)  
**Project:** PMOIS  
**Phase:** 4 — First Real Project Integration  
**Related migrations:** `0031_alter_api_tokens_add_project_id.sql`, `0032_seed_mju_asset_project.sql`  
**Related source files:** `src/Application/Middleware/ProjectScopeMiddleware.php`, `src/Application/Middleware/AuthTokenMiddleware.php`, `src/Infrastructure/Persistence/MySQL/MySqlApiTokenRepository.php`, `src/Domain/Auth/ApiTokenRepositoryInterface.php`, `src/Application/Http/Controllers/ApiTokenController.php`, `src/Config/routes.php`  
**Related documents:** [Verification Report](../reports/project-scoped-token-enforcement-verification.md)

---

## 1. Objective

Enable PMOIS to issue API tokens that are restricted to a single named project, so that an external system (beginning with MJU Asset) can integrate with PMOIS without obtaining read or write access to any other project or workspace-level resource.

The first integration target is **MJU Asset**, an internal project selected by the CTO as lower operational risk than StampPass before broader onboarding.

---

## 2. Background

PMOIS completed its pilot phase with a working `api_tokens` system where every token was scoped to an entire workspace. Any valid token could read and write all projects within that workspace.

This is acceptable during internal piloting but is not acceptable for real project integrations where least-privilege is required. A token issued to MJU Asset must not, under any circumstances, be usable against the PMOIS project or any other project.

The CTO requirement is:

> One API Token must belong to exactly one Project.  
> Token must only access its assigned project.  
> Preserve existing ADMIN token behaviour for internal administration.

---

## 3. Existing Architecture

### Token model (pre-Phase 4)

```
api_tokens
  id                  BIGINT PK
  workspace_id        BIGINT NOT NULL  ← only scope enforced
  created_by_user_id  BIGINT NOT NULL
  ai_consumer_id      BIGINT NULL
  token_name          VARCHAR(100)
  token_hash          VARCHAR(255)     ← SHA-256 of raw token; raw never stored
  scopes              VARCHAR(500) NULL
  status              ENUM('active','revoked')
  expires_at          TIMESTAMP NULL
  last_used_at        TIMESTAMP NULL
```

### Middleware chain (pre-Phase 4)

Execution order (last `->add()` runs first in Slim 4):

```
1. AuthTokenMiddleware        — validates bearer token, sets workspace_id / user_id / ai_consumer_id
2. AiAccessControlMiddleware  — deny-by-default for AI tokens (GET + allowlist only)
3. WorkspaceContextMiddleware — workspace context placeholder
4. AuditLoggingMiddleware     — writes audit_trails on successful mutating requests
   RequiresPermissionMiddleware (per-route) — checks role-permission table
```

### Permission model

`PermissionResolver` checks whether `created_by_user_id` holds the required permission in the workspace or project, via `workspace_members` and `project_members` tables. It is role-based and user-based — it has no awareness of which token was used.

---

## 4. Security Design

### Core principle: token carries its own project boundary

Rather than relying on caller-supplied project IDs or per-request configuration, the project restriction is embedded in the token row at creation time (`api_tokens.project_id`). The middleware reads this value from the database and enforces it before any business logic runs.

This means:
- A compromised token cannot be escalated to access other projects by changing the request URL.
- No application-level code in controllers or services needs to repeat the project check.
- The enforcement is uniform across every route in the API.

### Token classification

| `project_id` value | Token type | Scope |
|--------------------|------------|-------|
| `NULL` | Workspace-level (ADMIN) | All projects in the workspace — existing behaviour, unchanged |
| `N` (integer) | Project-scoped | Only routes where `{project_id}` route arg equals `N` |

### Denial rules for project-scoped tokens

A project-scoped token (`project_id IS NOT NULL`) is rejected with HTTP 403 in two situations:

1. **Route has no `{project_id}` argument** — the token is attempting to access a workspace-level resource (e.g., `GET /projects`, `GET /auth/tokens`, `GET /governance-records`). These are workspace-level routes and a project-scoped token may never access them.

2. **Route has `{project_id}` argument but it does not match `token.project_id`** — the token is attempting to access a different project.

All other middleware (permission resolver, AI access control, audit logging) continues to run as before on requests that pass the project scope check.

---

## 5. Database Changes

### Migration 0031 — `api_tokens` column addition

**File:** `database/migrations/0031_alter_api_tokens_add_project_id.sql`

```sql
ALTER TABLE api_tokens
    ADD COLUMN project_id BIGINT UNSIGNED NULL DEFAULT NULL
        AFTER workspace_id,
    ADD CONSTRAINT fk_tokens_project
        FOREIGN KEY (project_id) REFERENCES projects(id);
```

- Column is nullable. All existing tokens receive `NULL`, preserving workspace-level behaviour with no data migration required.
- Foreign key ensures referential integrity: a project-scoped token cannot reference a non-existent project.
- No index is added because the column is only read once per request (during auth) via the `token_hash` unique-key lookup, which already fetches the full row.

**Rollback:** `database/migrations/0031_alter_api_tokens_add_project_id.rollback.sql`

```sql
ALTER TABLE api_tokens
    DROP FOREIGN KEY fk_tokens_project,
    DROP COLUMN project_id;
```

### Migration 0032 — MJU Asset project seed

**File:** `database/migrations/0032_seed_mju_asset_project.sql`

Inserts the MJU Asset project into the JaideeDigital workspace (code `MJU-ASSET`). Uses `INSERT IGNORE` for idempotency. The project-scoped token for MJU Asset must be created via the API (see Section 9) after this migration runs, because raw tokens are never stored and cannot be pre-seeded in SQL.

---

## 6. Authorization Flow

### Request lifecycle for a project-scoped token

```
Client
  │
  │  Authorization: Bearer <raw_token>
  ▼
AuthTokenMiddleware
  │  1. Hash raw token → SHA-256
  │  2. SELECT id, workspace_id, project_id, created_by_user_id,
  │          ai_consumer_id, status, expires_at
  │     FROM api_tokens WHERE token_hash = :hash
  │  3. Validate status = 'active' and not expired
  │  4. container->set('current_workspace_id', workspace_id)
  │  5. Attach to request:
  │       api_token_id      = row.id
  │       workspace_id      = row.workspace_id
  │       user_id           = row.created_by_user_id
  │       ai_consumer_id    = row.ai_consumer_id (null if human)
  │       token_project_id  = row.project_id     (null if workspace-level)
  │       audit_context     = new AuditContext()
  ▼
ProjectScopeMiddleware                          ← NEW (Phase 4)
  │  If token_project_id == null:
  │    → pass through (workspace-level token)
  │  Else:
  │    routeProjectId = route arg 'project_id' (int or null)
  │    If routeProjectId == null:
  │      → 403 FORBIDDEN "Project-scoped token cannot access workspace-level resources"
  │    If routeProjectId != token_project_id:
  │      → 403 FORBIDDEN "Token is not authorized for this project"
  │    Else:
  │      → pass through
  ▼
AiAccessControlMiddleware
  │  (unchanged — only acts on ai_consumer_id != null)
  ▼
WorkspaceContextMiddleware
  ▼
AuditLoggingMiddleware
  ▼
RequiresPermissionMiddleware (per-route)
  │  Checks created_by_user_id has required permission
  │  via PermissionResolver (workspace_members / project_members)
  ▼
Controller
```

### Failure responses

| Condition | HTTP | Error code | Message |
|-----------|------|------------|---------|
| Missing / malformed `Authorization` header | 401 | `UNAUTHORIZED` | Missing or invalid Authorization header |
| Token not in database | 401 | `UNAUTHORIZED` | Token not found |
| Token revoked | 401 | `UNAUTHORIZED` | Token revoked |
| Token expired | 401 | `UNAUTHORIZED` | Token expired |
| Project-scoped token → workspace-level route | 403 | `FORBIDDEN` | Project-scoped token cannot access workspace-level resources |
| Project-scoped token → wrong project route | 403 | `FORBIDDEN` | Token is not authorized for this project |
| Insufficient permission (role-based) | 403 | `FORBIDDEN` | (from RequiresPermissionMiddleware) |

---

## 7. Permission Matrix

### MJU Asset project-scoped token

| Route | Result | Reason |
|-------|--------|--------|
| `POST /api/v1/projects/{mju_id}/status` | PASS | `project_id` arg matches `token.project_id` |
| `GET  /api/v1/projects/{mju_id}/status` | PASS | `project_id` arg matches `token.project_id` |
| `GET  /api/v1/projects/{mju_id}/status/history` | PASS | `project_id` arg matches `token.project_id` |
| `POST /api/v1/projects/{pmois_id}/status` | FAIL 403 | `project_id` arg ≠ `token.project_id` |
| `GET  /api/v1/projects/{pmois_id}/status` | FAIL 403 | `project_id` arg ≠ `token.project_id` |
| `GET  /api/v1/projects` | FAIL 403 | Route has no `project_id` arg (workspace-level) |
| `GET  /api/v1/auth/tokens` | FAIL 403 | Route has no `project_id` arg (workspace-level) |
| `GET  /api/v1/governance-records` | FAIL 403 | Route has no `project_id` arg (workspace-level) |
| Any route with wrong workspace token | FAIL 401 | Token belongs to a different workspace; `token_hash` lookup returns nothing valid |

### Workspace-level (ADMIN) token

| Condition | Result |
|-----------|--------|
| Any route, any project | PASS (unchanged from pre-Phase 4) — `token_project_id = null`, `ProjectScopeMiddleware` passes through immediately |

---

## 8. Middleware Changes

### New middleware: `ProjectScopeMiddleware`

**File:** `src/Application/Middleware/ProjectScopeMiddleware.php`  
**Class:** `App\Application\Middleware\ProjectScopeMiddleware`  
**Interface:** `Psr\Http\Server\MiddlewareInterface`  
**DI registration:** Auto-wired by PHP-DI (no constructor parameters)

**Position in group chain:** Added between `AuthTokenMiddleware` (outermost) and `AiAccessControlMiddleware`. In Slim 4 LIFO ordering, `ProjectScopeMiddleware` executes 2nd — immediately after `AuthTokenMiddleware` has authenticated the token and attached `token_project_id`.

**Route group registration** (`src/Config/routes.php`):

```php
->add(AuditLoggingMiddleware::class)
->add(WorkspaceContextMiddleware::class)
->add(AiAccessControlMiddleware::class)
->add(ProjectScopeMiddleware::class)         // Phase 4 addition
->add(new AuthTokenMiddleware($container->get(PDO::class), $container))
```

### Modified middleware: `AuthTokenMiddleware`

**File:** `src/Application/Middleware/AuthTokenMiddleware.php`

Added `project_id` to the `SELECT` query:

```php
'SELECT id, workspace_id, project_id, created_by_user_id, ai_consumer_id, status, expires_at
 FROM api_tokens WHERE token_hash = :hash LIMIT 1'
```

Added `token_project_id` attribute to the request:

```php
->withAttribute('token_project_id', $row['project_id'] !== null ? (int) $row['project_id'] : null)
```

No other changes to `AuthTokenMiddleware`. Authentication logic, workspace resolution, and DI container injection are unchanged.

---

## 9. API Behavior

### Token creation — `POST /api/v1/auth/tokens`

**Permission required:** `api_token.create` (ADMIN token or user with admin role)

**Request body (new optional field):**

```json
{
  "token_name": "MJU Asset Integration Token",
  "project_id": 42
}
```

- If `project_id` is omitted or `null`, the token is created as workspace-level (existing behaviour).
- If `project_id` is provided, the token is created as project-scoped. The value must be a valid project ID within the same workspace (enforced by the FK constraint on `api_tokens.project_id`).

**Response (201 Created):**

```json
{
  "success": true,
  "data": {
    "id": 7,
    "token_name": "MJU Asset Integration Token",
    "project_id": 42,
    "raw_token": "...",
    "warning": "เก็บ raw_token นี้ไว้ตอนนี้เท่านั้น จะไม่แสดงซ้ำอีก"
  },
  "error": null,
  "meta": { "timestamp": "..." }
}
```

The `raw_token` is shown exactly once. It is not stored. If lost, the token must be revoked and a new one created.

### Token listing — `GET /api/v1/auth/tokens`

`project_id` is now included in each token record in the listing response:

```json
{
  "success": true,
  "data": [
    {
      "id": 7,
      "project_id": 42,
      "token_name": "MJU Asset Integration Token",
      "status": "active",
      "expires_at": null,
      "last_used_at": "2026-06-25T10:00:00+00:00",
      "created_at": "2026-06-25T09:00:00+00:00"
    }
  ],
  ...
}
```

### Token revocation — `DELETE /api/v1/auth/tokens/{id}`

Unchanged. Revocation uses workspace-scoped update; `project_id` is not involved.

### Project status endpoints (MJU Asset integration target)

| Method | Path | Permission | Project-scoped token behaviour |
|--------|------|------------|-------------------------------|
| `POST` | `/api/v1/projects/{project_id}/status` | `project_status_update.create` | Allowed if `{project_id}` == `token.project_id` |
| `GET`  | `/api/v1/projects/{project_id}/status` | `project_status_update.view` | Allowed if `{project_id}` == `token.project_id` |
| `GET`  | `/api/v1/projects/{project_id}/status/history` | `project_status_update.view` | Allowed if `{project_id}` == `token.project_id` |

---

## 10. Response Envelope Compatibility

All error responses from `ProjectScopeMiddleware` use the standard `ApiResponse::error()` responder, which produces the same envelope format as all other middleware:

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "FORBIDDEN",
    "message": "Token is not authorized for this project",
    "details": []
  },
  "meta": {
    "timestamp": "2026-06-25T10:00:00+00:00"
  }
}
```

The response envelope is fully compatible with the existing API contract. No client-side envelope changes are required.

---

## 11. Audit Trail Impact

`ProjectScopeMiddleware` denials (HTTP 403) do **not** write audit trail records. This is consistent with the existing pattern:

- `AuthTokenMiddleware` rejections (HTTP 401) → no audit record
- `AiAccessControlMiddleware` rejections → **does** write `action='ai_access_denied'` (special case, per CTO decision Phase 3)
- `RequiresPermissionMiddleware` rejections (HTTP 403) → no audit record
- `ProjectScopeMiddleware` rejections (HTTP 403) → no audit record

The rationale is that audit trails record business events (entity state changes), not security enforcement events. Security events are captured in server access logs at the infrastructure layer.

`AuditLoggingMiddleware` does not run for blocked requests because `ProjectScopeMiddleware` short-circuits the pipeline and returns before calling `$handler->handle($request)`. `AuditLoggingMiddleware` only writes after a successful 2xx response.

---

## 12. Workspace Isolation Considerations

Project-scoped token enforcement adds a new isolation layer above the existing workspace isolation:

```
Platform
  └─ Workspace  ← enforced by workspace_id on every token (unchanged)
      └─ Project  ← NOW also enforced by project_id on project-scoped tokens (Phase 4)
```

The existing `BaseRepository` workspace isolation (`applyWorkspaceScope` + `assertWorkspaceMatch`) is unchanged and remains active for all database operations. A project-scoped token still belongs to exactly one workspace; `workspace_id` continues to be set by `AuthTokenMiddleware` and used by all repositories.

A project-scoped token therefore satisfies **both** isolation guarantees simultaneously:
1. It cannot access data from another workspace (workspace isolation, pre-existing).
2. It cannot access data from another project within the same workspace (project isolation, Phase 4).

Cross-workspace access is impossible regardless of token type, because `AuthTokenMiddleware` looks up the token by hash and unconditionally takes the `workspace_id` from the database row — there is no way for a caller to supply an alternative workspace.

---

## 13. Backward Compatibility

| Area | Impact |
|------|--------|
| Existing ADMIN tokens | None. `token_project_id = null`; `ProjectScopeMiddleware` passes through immediately. |
| Existing AI consumer tokens | None. `token_project_id = null`; `ProjectScopeMiddleware` passes through. `AiAccessControlMiddleware` logic is unchanged. |
| `POST /api/v1/auth/tokens` | Additive: new optional `project_id` field. Existing callers that omit `project_id` receive a workspace-level token as before. |
| `GET /api/v1/auth/tokens` | Additive: `project_id` field now included in each record (`null` for existing tokens). |
| All other endpoints | Unchanged. |
| Database | Migration 0031 adds a nullable column with a default of `NULL`. All existing rows are unaffected. No data migration required. |
| Response envelope | Unchanged format. |

---

## 14. Risks and Limitations

### No validation that `project_id` belongs to the token's workspace at creation time

When `POST /api/v1/auth/tokens` is called with `project_id`, the value is passed directly to the INSERT. The FK constraint on `api_tokens.project_id → projects.id` prevents referencing a non-existent project, but does not prevent referencing a project that belongs to a different workspace. A caller with `api_token.create` permission could theoretically create a token that references a project in another workspace.

**Mitigation:** In practice, `api_token.create` is an ADMIN-level permission. The risk is real but low. A future hardening step should add an explicit `WHERE workspace_id = :workspace_id` check on the `projects` table when validating the provided `project_id` during token creation.

### Route parameter name dependency

`ProjectScopeMiddleware` reads the route argument named exactly `project_id`. Routes that reference a project using a different parameter name (e.g., `{id}` as in `PUT /projects/{id}/close`) are treated as workspace-level routes by the middleware, and project-scoped tokens are denied. This is intentional for the current scope: the only routes a project-scoped integration token needs are the three Inbound Status API routes, which all use `{project_id}`.

### No per-route override mechanism

There is currently no way to grant a project-scoped token access to a specific workspace-level route. If such a requirement arises, a more granular scope system (e.g., a `scopes` allowlist) would need to be implemented. The existing `scopes` column on `api_tokens` is reserved for this purpose but is not yet populated or enforced.

---

## 15. Future Considerations

1. **Workspace validation on token creation** — add a query to verify that the supplied `project_id` belongs to the same workspace as the token being created, before inserting.

2. **Scopes column** — the `api_tokens.scopes` column exists but is unused. It could be used to further restrict what operations a project-scoped token may perform (e.g., read-only vs. read-write).

3. **Token listing per project** — `GET /api/v1/auth/tokens` currently lists all tokens in the workspace. A `?project_id=` filter could be added for administrative convenience.

4. **Token rotation policy** — project-scoped tokens are long-lived unless explicitly revoked. A recommended rotation cadence should be established for each integrated project.

5. **Broader project enforcement** — if future project-scoped tokens need access to routes other than Inbound Status (e.g., governance adoptions), the `ProjectScopeMiddleware` route-arg lookup is already generalised: any route with a `{project_id}` argument is automatically covered.

---

## Implementation Summary

### Files Added

| File | Description |
|------|-------------|
| `src/Application/Middleware/ProjectScopeMiddleware.php` | New middleware enforcing project isolation for project-scoped tokens |
| `database/migrations/0031_alter_api_tokens_add_project_id.sql` | Adds nullable `project_id` column and FK to `api_tokens` |
| `database/migrations/0031_alter_api_tokens_add_project_id.rollback.sql` | Rollback for migration 0031 |
| `database/migrations/0032_seed_mju_asset_project.sql` | Seeds MJU Asset project in JaideeDigital workspace |

### Files Modified

| File | Change |
|------|--------|
| `src/Application/Middleware/AuthTokenMiddleware.php` | Added `project_id` to SELECT query; attaches `token_project_id` attribute to request |
| `src/Domain/Auth/ApiTokenRepositoryInterface.php` | Added `?int $projectId = null` parameter to `create()` |
| `src/Infrastructure/Persistence/MySQL/MySqlApiTokenRepository.php` | Inserts `project_id` on token creation; exposes `project_id` in `listByWorkspace()` |
| `src/Application/Http/Controllers/ApiTokenController.php` | Passes `project_id` from request body to repository; includes it in create response |
| `src/Config/routes.php` | Registers `ProjectScopeMiddleware` in route group chain (executes 2nd, after `AuthTokenMiddleware`) |

### Database Migrations

| Migration | Description |
|-----------|-------------|
| `0031_alter_api_tokens_add_project_id.sql` | `ALTER TABLE api_tokens ADD COLUMN project_id BIGINT UNSIGNED NULL` with FK to `projects.id` |
| `0032_seed_mju_asset_project.sql` | `INSERT IGNORE INTO projects` — MJU Asset project (`code = 'MJU-ASSET'`) |

### Middleware Added

| Middleware | Class | Position in chain |
|------------|-------|------------------|
| `ProjectScopeMiddleware` | `App\Application\Middleware\ProjectScopeMiddleware` | 2nd (after `AuthTokenMiddleware`, before `AiAccessControlMiddleware`) |

### API Changes

| Endpoint | Change |
|----------|--------|
| `POST /api/v1/auth/tokens` | New optional request field: `project_id` (integer). Omitting it preserves existing workspace-level token behaviour. |
| `POST /api/v1/auth/tokens` | New response field: `project_id` (integer or null) |
| `GET /api/v1/auth/tokens` | New field in each token record: `project_id` (integer or null) |

---

## Related Documents

- [Verification Report — Project-Scoped Token Enforcement](../reports/project-scoped-token-enforcement-verification.md)
