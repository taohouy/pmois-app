# PMOIS v2 — API Endpoint Design (M0 Item 4)

**Revision 1** — Response envelope corrected to match the existing implementation (`src/Application/Http/Responders/ApiResponse.php`) and `docs/templates/api-design-template.md`. Endpoint authorization corrected to use `permission_code` (checked via `PermissionResolver`) instead of token scopes.

## 1. API Overview

- **Base URL:** `/api/v1` today; a `v2` prefix is introduced only for genuinely new/breaking resources added by M0 (projects hierarchy fields, milestones, revisions, repositories, AI assignment). Existing `v1` endpoints (`project_status_updates`, governance, RFC, decision register, knowledge, attachments) are **not** renumbered — no forced breaking change to existing integrations (see `docs/adr/ADR-0001-pmois-api-v1.0-freeze.md`).
- **Authentication:** Bearer token → `api_tokens.token_hash` lookup (unchanged, see `05-Auth-Authorization-Design.md`).
- **Content-Type:** `application/json`, UTF-8, ISO 8601 dates — matches `docs/templates/api-design-template.md`.

---

## 2. Response Envelope (matches real `ApiResponse.php` — do not deviate without a separate ADR)

**Success:**
```json
{
  "success": true,
  "data": { "id": 892 },
  "error": null,
  "meta": { "timestamp": "2026-08-30T10:30:00+00:00" }
}
```

**Error:**
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human-readable description",
    "details": []
  },
  "meta": { "timestamp": "2026-08-30T10:30:00+00:00" }
}
```

> **Note on CTO Review §5:** the CTO Review instructed the standard to be `code / message / data` and explicitly rejected `success / error / meta / links`. Checking the actual source (`ApiResponse.php`) and `docs/templates/api-design-template.md`, the real production envelope is `{success, data, error{code,message,details}, meta{timestamp}}` — every error already carries `code` and `message`, and every success carries `data`. This revision adopts that **exact existing envelope** rather than either the previous draft's invented `links`/pagination wrapper (removed) or a new flat `{code,message,data}` structure, so that no existing `v1` consumer breaks. If a flat top-level `{code,message,data}` structure is truly intended as a new standard, that is a breaking change to the current implementation and should be raised as its own Architecture Decision Record for explicit CTO approval before any endpoint adopts it.

**Pagination** (when needed) lives inside `meta`, not as a new top-level `links` object:
```json
"meta": { "timestamp": "...", "page": 1, "limit": 20, "total": 150 }
```

---

## 3. Authorization Model for Endpoints

Every mutating/protected endpoint declares a `permission_code` (existing convention, `resource.action` — e.g. `project.create`, `milestone.update`) checked via `RequiresPermissionMiddleware` → `PermissionResolver`, **not** a token scope list. Token scopes (`api_tokens.scopes`) remain informational only (see `05-Auth-Authorization-Design.md`).

---

## 4. Endpoint Summary (new M0 resources only — existing v1 endpoints unchanged)

| Method | Endpoint | `permission_code` | Notes |
|---|---|---|---|
| `POST` | `/api/v1/projects` | `project.create` | Minimal fields per M0 Item 3; auto-creates `governance_adoptions` baseline row |
| `PATCH` | `/api/v1/projects/{id}/structure` | `project.structure.update` | Move workspace / change parent / promote; writes `project_structure_history` |
| `PATCH` | `/api/v1/projects/{id}/progress` | `project.progress.update` | CTO/PMO only — see §5 of `03-Key-Relationships-Constraints.md` |
| `GET` | `/api/v1/projects/{id}/structure-history` | `project.view` | |
| `GET`/`POST` | `/api/v1/projects/{id}/milestones` | `milestone.view` / `milestone.create` | |
| `PATCH` | `/api/v1/milestones/{id}/close` | `milestone.close` | CTO only |
| `POST` | `/api/v1/revisions` | `revision.create` | Dev submits (Step 1–2 of unified workflow, see `07-...md`) |
| `POST` | `/api/v1/revisions/{id}/review` | `revision.review` | CTO approve/reject |
| `PATCH` | `/api/v1/revisions/{id}/commit` | `revision.create` | Dev records commit hash after CTO approval |
| `GET`/`POST` | `/api/v1/repositories` | `repository.view` / `repository.manage` | |
| `GET`/`POST` | `/api/v1/projects/{id}/ai-assignments` | `ai_assignment.view` / `ai_assignment.manage` | |
| `GET` | `/api/v1/ai-consumers` | `ai_consumer.view` | Existing table, existing endpoint pattern |

Existing (unchanged) endpoint families kept as-is: `/api/v1/projects/status` (project status updates), `/api/v1/governance-records`, `/api/v1/governance-versions`, `/api/v1/governance-adoptions`, `/api/v1/rfcs`, `/api/v1/decision-registers`, `/api/v1/knowledge-articles`, `/api/v1/attachments`, `/api/v1/api-tokens`.

---

## 5. Error Codes (extends existing list in `api-design-template.md`)

| Error code | HTTP | Condition |
|---|---|---|
| `UNAUTHORIZED` | 401 | Missing/invalid/revoked/expired token (existing) |
| `FORBIDDEN` | 403 | Missing permission (existing) |
| `NOT_FOUND` | 404 | Resource not in this workspace (existing) |
| `VALIDATION_ERROR` | 422 | Request body invalid (existing) |
| `PROJECT_CODE_CONFLICT` | 409 | New — Move Workspace target already has same `code` |
| `CIRCULAR_HIERARCHY` | 409 | New — proposed parent is a descendant of the project |
| `MILESTONE_NOT_OPEN` | 409 | New — revision submitted against a closed milestone |
| `REVISION_NOT_APPROVED` | 409 | New — commit recorded before CTO approval |

---

## 6. Idempotency & Rate Limiting

- Idempotency: reuse the existing pattern from `project_status_updates.idempotency_key` for any new endpoint that risks duplicate submission (`POST /revisions`).
- Rate limiting: **not implemented today** (matches `api-design-template.md` §10 default: "Not implemented. Planned for a later phase."). No fixed numeric rate limit is mandated by M0 — removed from this revision per CTO Review §8 (do not invent unconfirmed requirements).

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review*
*Date: 2026-08-30*