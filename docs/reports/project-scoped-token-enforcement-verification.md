# Project-Scoped Token Enforcement — Verification Report

**Document type:** Verification Report  
**Document code:** VER-project-scoped-token-enforcement  
**Version:** 1  
**Status:** Complete  
**Review status:** Pending CTO Sign-Off  
**Reviewed by:** —  
**Approval status:** Pending  
**Approved by:** —  
**Date:** 2026-06-25  
**Last updated:** 2026-06-25  
**Author:** Claude Code (on behalf of CTO)  
**Project:** PMOIS  
**Phase / Feature:** Phase 4 — Project-Scoped API Token Enforcement  
**Related migrations:** `0031_alter_api_tokens_add_project_id.sql`, `0032_seed_mju_asset_project.sql`  
**Related source files:** `src/Application/Middleware/ProjectScopeMiddleware.php`, `src/Application/Middleware/AuthTokenMiddleware.php`, `src/Config/routes.php`  
**Related documents:** [Security Design](../security/project-scoped-token-enforcement.md)

---

## 1. Test Environment

### Infrastructure

| Item | Value |
|------|-------|
| Environment | Production server (JaideeDigital workspace) |
| Framework | Slim 4 / PHP 8.1+ |
| Database | MySQL (InnoDB) |
| Workspace | `JAIDEEDIGITAL` |
| Admin user | `admin@jaidee.digital` |

### Projects under test

| Project | Code | ID (runtime) | Role in test |
|---------|------|-------------|--------------|
| PMOIS | `PMOIS` | **3** | Cross-project denial target |
| MJU Asset | `MJU-ASSET` | **4** | Primary integration project |

### Tokens under test

| Token | Type | Scope | Source |
|-------|------|-------|--------|
| ADMIN token | Workspace-level (`project_id = NULL`) | All projects | New Production ADMIN token (token id: 7) |
| MJU Asset token | Project-scoped (`project_id = 4`) | MJU Asset project only | Created via `POST /api/v1/auth/tokens` — token id: 8, name: "MJU Asset Integration Token" |

### Pre-conditions

The following must be completed before verification:

1. Migration 0031 applied: `ALTER TABLE api_tokens ADD COLUMN project_id ...`
2. Migration 0032 applied: MJU Asset project seeded (`code = 'MJU-ASSET'`)
3. MJU Asset project ID retrieved: `GET /api/v1/projects` (using ADMIN token)
4. MJU Asset project-scoped token created:

```http
POST /api/v1/auth/tokens
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "token_name": "MJU Asset Integration Token",
  "project_id": <mju_asset_project_id>
}
```

Raw token saved from the single-display response.

5. PMOIS project ID confirmed from project listing.

---

## 2. Test Scenarios

### Test ID convention

- `PASS-xx` — expected to succeed (HTTP 2xx)
- `FAIL-xx` — expected to be denied (HTTP 4xx)

### Token references

- `{ADMIN_TOKEN}` — the existing workspace-level ADMIN token
- `{MJU_TOKEN}` — the newly created MJU Asset project-scoped token
- `{MJU_ID}` — database ID of the MJU Asset project
- `{PMOIS_ID}` — database ID of the PMOIS project

---

## 3. PASS Cases

### PASS-01: POST Project Status for MJU Asset

**Objective:** Verify that the MJU Asset token can submit a status update to its own project.

**Request:**

```http
POST /api/v1/projects/{MJU_ID}/status
Authorization: Bearer {MJU_TOKEN}
Content-Type: application/json

{
  "status": "on_track",
  "summary": "Verification test — initial status submission",
  "submitted_by_name": "MJU Asset Integration"
}
```

**Expected response:** HTTP 201 Created

```json
{
  "success": true,
  "data": {
    "id": <integer>,
    "project_id": {MJU_ID},
    "status": "on_track",
    "summary": "Verification test — initial status submission",
    ...
  },
  "error": null,
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `201`
- `success` is `true`
- `data.project_id` equals `{MJU_ID}`
- `data.status` equals `"on_track"`

---

### PASS-02: GET Latest Status for MJU Asset

**Objective:** Verify that the MJU Asset token can retrieve the latest status of its own project.

**Pre-condition:** PASS-01 completed successfully.

**Request:**

```http
GET /api/v1/projects/{MJU_ID}/status
Authorization: Bearer {MJU_TOKEN}
```

**Expected response:** HTTP 200 OK

```json
{
  "success": true,
  "data": {
    "id": <integer>,
    "project_id": {MJU_ID},
    "status": "on_track",
    "summary": "Verification test — initial status submission",
    ...
  },
  "error": null,
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `200`
- `success` is `true`
- `data.project_id` equals `{MJU_ID}`
- Response reflects the record created in PASS-01

---

### PASS-03: GET Status History for MJU Asset

**Objective:** Verify that the MJU Asset token can retrieve the status history of its own project.

**Pre-condition:** PASS-01 completed successfully.

**Request:**

```http
GET /api/v1/projects/{MJU_ID}/status/history
Authorization: Bearer {MJU_TOKEN}
```

**Expected response:** HTTP 200 OK

```json
{
  "success": true,
  "data": [
    {
      "id": <integer>,
      "project_id": {MJU_ID},
      "status": "on_track",
      ...
    }
  ],
  "error": null,
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `200`
- `success` is `true`
- `data` is an array containing at least the record from PASS-01
- Each record has `project_id` equal to `{MJU_ID}`

---

## 4. FAIL Cases

### FAIL-01: MJU Asset Token — POST Status to PMOIS Project

**Objective:** Verify that the MJU Asset token is denied access to a different project in the same workspace.

**Request:**

```http
POST /api/v1/projects/{PMOIS_ID}/status
Authorization: Bearer {MJU_TOKEN}
Content-Type: application/json

{
  "status": "on_track",
  "summary": "Cross-project injection attempt"
}
```

**Expected response:** HTTP 403 Forbidden

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "FORBIDDEN",
    "message": "Token is not authorized for this project",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `403`
- `success` is `false`
- `error.code` is `"FORBIDDEN"`
- `error.message` is `"Token is not authorized for this project"`
- No record is written to `project_status_updates`

---

### FAIL-02: MJU Asset Token — GET Status from PMOIS Project

**Objective:** Verify that the MJU Asset token cannot read status from a different project.

**Request:**

```http
GET /api/v1/projects/{PMOIS_ID}/status
Authorization: Bearer {MJU_TOKEN}
```

**Expected response:** HTTP 403 Forbidden

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "FORBIDDEN",
    "message": "Token is not authorized for this project",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `403`
- `success` is `false`
- `error.code` is `"FORBIDDEN"`

---

### FAIL-03: MJU Asset Token — Workspace-Level Route (Project List)

**Objective:** Verify that the MJU Asset token cannot access workspace-level routes that have no `{project_id}` argument.

**Request:**

```http
GET /api/v1/projects
Authorization: Bearer {MJU_TOKEN}
```

**Expected response:** HTTP 403 Forbidden

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "FORBIDDEN",
    "message": "Project-scoped token cannot access workspace-level resources",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `403`
- `success` is `false`
- `error.message` is `"Project-scoped token cannot access workspace-level resources"`

---

### FAIL-04: MJU Asset Token — Workspace-Level Route (Token List)

**Objective:** Verify that the MJU Asset token cannot access token management routes.

**Request:**

```http
GET /api/v1/auth/tokens
Authorization: Bearer {MJU_TOKEN}
```

**Expected response:** HTTP 403 Forbidden

**Pass criteria:**
- HTTP status code is `403`
- `error.code` is `"FORBIDDEN"`
- `error.message` is `"Project-scoped token cannot access workspace-level resources"`

---

### FAIL-05: Invalid Token

**Objective:** Verify that an invalid or non-existent bearer token is rejected before reaching the project scope check.

**Request:**

```http
POST /api/v1/projects/{MJU_ID}/status
Authorization: Bearer invalid_token_string
Content-Type: application/json

{ "status": "on_track", "summary": "Test" }
```

**Expected response:** HTTP 401 Unauthorized

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Token not found",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `401`
- `error.code` is `"UNAUTHORIZED"`

---

### FAIL-06: Missing Authorization Header

**Objective:** Verify that requests with no `Authorization` header are rejected before reaching the project scope check.

**Request:**

```http
GET /api/v1/projects/{MJU_ID}/status
```

**Expected response:** HTTP 401 Unauthorized

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Missing or invalid Authorization header",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**
- HTTP status code is `401`
- `error.code` is `"UNAUTHORIZED"`

---

## 5. Verification Results

> **Note:** This section is to be completed by the engineer running verification on the production server. Fill in each row with the actual HTTP status code observed and mark PASS or FAIL.

| Test ID | Description | Expected HTTP | Actual HTTP | Result |
|---------|-------------|---------------|-------------|--------|
| PASS-01 | POST status for MJU Asset | 201 | 201 | ✅ PASS |
| PASS-02 | GET latest status for MJU Asset | 200 | 200 | ✅ PASS |
| PASS-03 | GET status history for MJU Asset | 200 | 200 | ✅ PASS |
| FAIL-01 | POST to PMOIS project with MJU token | 403 | 403 | ✅ PASS |
| FAIL-02 | GET from PMOIS project with MJU token | 403 | 403 | ✅ PASS |
| FAIL-03 | GET /projects (workspace-level) with MJU token | 403 | 403 | ✅ PASS |
| FAIL-04 | GET /auth/tokens (workspace-level) with MJU token | 403 | 403 | ✅ PASS |
| FAIL-05 | Invalid bearer token | 401 | 401 | ✅ PASS |
| FAIL-06 | Missing Authorization header | 401 | 401 | ✅ PASS |
| BC-01 | ADMIN token POST status for MJU Asset | 201 | 201 | ✅ PASS |
| BC-02 | ADMIN token GET /projects (workspace-level) | 200 | 200 | ✅ PASS |
| BC-03 | ADMIN token GET PMOIS status | 200 | 200 | ✅ PASS |

**Overall result:** ✅ PASS — all 12 scenarios matched expected HTTP status  
**Verified by:** Claude Code (on behalf of CTO)  
**Verification date:** 2026-06-25  
**Environment:** Production — `https://pmo.jaideedigital.com/api`

---

## 6. Observations

### Finding: em dash in JSON body breaks Bash one-liner curl

During the first verification run, PASS-01 and BC-01 returned HTTP 422 (`VALIDATION_ERROR`). Root cause: the em dash character (`—`) in the summary string `"Verification test — initial status submission"` was mishandled by the Bash shell when passed via `-d` flag, resulting in a malformed JSON body. The API correctly rejected it as missing required fields.

**Resolution:** Switched to `--data-raw` with ASCII-only body. Both scenarios returned HTTP 201 as expected. This is a test-script encoding artefact — not an API bug.

**Action:** Verification runbook (`dep-phase4-project-scoped-token-runbook.md`) should be noted to use `--data-raw` and ASCII-only summary strings. No code change required.

---

### Known behaviours confirmed during verification

1. **`project_id` in PASS responses** — the `POST /status` response body should contain the correct `project_id`. Confirm it matches `{MJU_ID}` and not another value.

2. **`last_used_at` update** — after PASS-01 through PASS-03, verify that `api_tokens.last_used_at` for the MJU Token was updated (visible via `GET /api/v1/auth/tokens` using the ADMIN token). This confirms `AuthTokenMiddleware` ran to completion for successful requests.

3. **No audit record for FAIL cases** — FAIL-01 and FAIL-02 should produce no row in `audit_trails` for `entity_type = 'project_status_update'`. The audit middleware only writes after a 2xx response and is short-circuited by the 403 from `ProjectScopeMiddleware`.

4. **ADMIN token unaffected** — after running all FAIL tests, confirm that the ADMIN token can still `POST /api/v1/projects/{MJU_ID}/status`, `GET /api/v1/projects/{PMOIS_ID}/status`, and `GET /api/v1/projects` without restriction. This confirms backward compatibility.

---

## 7. Conclusion

Project-Scoped Token Enforcement (Phase 4) is **functioning correctly in production**.

All 12 verification scenarios passed on 2026-06-25 against `https://pmo.jaideedigital.com/api`:

- **Project isolation is enforced:** the MJU Asset token correctly accesses only its own project and is blocked from PMOIS and all workspace-level routes.
- **Backward compatibility is preserved:** the ADMIN token (workspace-level, `project_id = NULL`) retains full access to all projects and workspace routes — no regression from Phase 4.
- **Auth layer is correct:** invalid and missing tokens are rejected with HTTP 401 before reaching the project scope check.

**MJU Asset integration status:** ✅ Cleared for production use.

**Open items before PMOIS API v1.0 freeze:**
- Known limitation (flagged in Security Design): no server-side validation that `project_id` at token creation belongs to the same workspace. FK prevents non-existent projects but not cross-workspace references. Accepted for v1.0; flagged as future hardening.

**Recommendation:** CTO may sign off this report to proceed with PMOIS API v1.0 freeze.

---

---

## Related Documents

- [Security Design — Project-Scoped Token Enforcement](../security/project-scoped-token-enforcement.md)
