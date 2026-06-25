# PMOIS Project Context Handover

**Date:** 2026-06-25  
**Prepared by:** Claude Code  
**Reason:** Conversation context near capacity — handover for new session  
**GitLab repository:** `https://gitlab.com/jaideedigital/pmois-app.git`  
**Production URL:** `https://pmo.jaideedigital.com/api`  
**Latest commit:** `af84b92` on `master`

---

## 1. Current Project Status

**Phase 4 — Project-Scoped Token Enforcement**

| Item | Status |
|------|--------|
| Implementation | Complete (committed, pushed) |
| Production deployment (FTP upload) | Complete |
| Database migrations 0031 + 0032 | Applied via phpMyAdmin |
| Production health check | PASS — `/api/v1/health` = `{"status":"ok"}` |
| Production ADMIN token | **Ready** — new token generated successfully |
| MJU Asset project-scoped token | Not yet created — next step |
| Verification (9 scenarios) | **Pending** — ready to execute |
| Verification Report | Draft — results table empty, awaiting execution |

---

## 2. Completed Work (This Session)

### Phase 4 Implementation

| File | Change |
|------|--------|
| `src/Application/Middleware/ProjectScopeMiddleware.php` | **NEW** — enforces project isolation |
| `src/Application/Middleware/AuthTokenMiddleware.php` | Adds `project_id` to SELECT; attaches `token_project_id` to request |
| `src/Domain/Auth/ApiTokenRepositoryInterface.php` | Adds `?int $projectId = null` to `create()` |
| `src/Infrastructure/Persistence/MySQL/MySqlApiTokenRepository.php` | Inserts and returns `project_id` |
| `src/Application/Http/Controllers/ApiTokenController.php` | Accepts `project_id` from request body; includes in response |
| `src/Config/routes.php` | Registers `ProjectScopeMiddleware` in route group (runs 2nd) |

### Database Migrations (applied to production)

| Migration | Description |
|-----------|-------------|
| `0031_alter_api_tokens_add_project_id.sql` | `ALTER TABLE api_tokens ADD COLUMN project_id BIGINT UNSIGNED NULL` with FK to `projects.id` |
| `0032_seed_mju_asset_project.sql` | Seeds MJU Asset project (`code = 'MJU-ASSET'`) in JaideeDigital workspace |

### Documentation

| File | Description |
|------|-------------|
| `docs/security/project-scoped-token-enforcement.md` | Security design — CTO Reviewed & Accepted |
| `docs/reports/project-scoped-token-enforcement-verification.md` | Verification report — draft, results pending |
| `docs/reports/dep-phase4-project-scoped-token-runbook.md` | Deployment & verification runbook |
| `docs/governance/governance-framework-v1.4.md` | Governance Framework v1.4 (adds Documentation Standards) |
| `docs/templates/*.md` | 8 document templates (ARCH, SEC, API, DB, VER, DEP, ADR, MIG) |

---

## 3. Current Production Deployment Status

- **Code deployed:** Yes — Phase 4 files uploaded via FTP (commit `949a958`)
- **Migrations applied:** Yes — 0031 and 0032 via phpMyAdmin
- **Health check:** PASS
- **Workspace:** `JAIDEEDIGITAL` (code)
- **Admin user:** `admin@jaidee.digital`
- **Projects in DB:** PMOIS (`code = PMOIS`), MJU Asset (`code = MJU-ASSET`)

**Production deployment convention (important):**  
PMOIS uses **FTP/file upload** — NOT `git pull`. Production server does not have Git access. This must be used for all future deployments.

---

## 4. Database Migration Status

| # | Migration | Status |
|---|-----------|--------|
| 0001–0030 | All prior migrations | Applied (pre-Phase 4 baseline) |
| 0031 | `ALTER TABLE api_tokens ADD COLUMN project_id` | **Applied** |
| 0032 | Seed MJU Asset project | **Applied** |

Current `api_tokens` schema includes `project_id BIGINT UNSIGNED NULL` after `workspace_id`, with FK `fk_tokens_project → projects.id`.

---

## 5. Current Architecture Decisions

### Middleware execution order (Phase 4 final state)

```
1. AuthTokenMiddleware        — auth; sets workspace_id, user_id, token_project_id, ai_consumer_id
2. ProjectScopeMiddleware     — project isolation (NEW Phase 4)
3. AiAccessControlMiddleware  — deny-by-default for AI tokens
4. WorkspaceContextMiddleware
5. AuditLoggingMiddleware
   RequiresPermissionMiddleware (per-route)
```

### Token classification

| `api_tokens.project_id` | Token type | Access |
|-------------------------|------------|--------|
| `NULL` | Workspace-level (ADMIN) | All projects in workspace — unchanged |
| `N` | Project-scoped | Only routes where `{project_id}` route arg = N |

### ProjectScopeMiddleware denial rules

- Token has `project_id` set + route has no `{project_id}` arg → **403** "Project-scoped token cannot access workspace-level resources"
- Token has `project_id` set + route `{project_id}` ≠ `token.project_id` → **403** "Token is not authorized for this project"

### Token creation (POST /api/v1/auth/tokens)

New optional field: `project_id` (integer). Omitting it creates a workspace-level token (backward compatible).

---

## 6. Security Decisions

- **Raw tokens never stored** — only SHA-256 hash in `api_tokens.token_hash`
- **Project isolation is token-embedded** — project_id comes from DB row, not from caller
- **Compromised token cannot be escalated** — changing the URL does not grant access to other projects
- **Workspace isolation preserved** — `BaseRepository.applyWorkspaceScope()` and `assertWorkspaceMatch()` unchanged
- **Audit trail:** `ProjectScopeMiddleware` 403 denials are NOT logged (consistent with other middleware; only `AiAccessControlMiddleware` logs denials as special case per Phase 3 CTO decision)
- **Known limitation:** No validation that `project_id` supplied at token creation belongs to the same workspace. FK prevents non-existent project but not cross-workspace reference. Flagged as future hardening item.

---

## 7. Remaining Verification Tasks

### Verification steps

**Step A: Get project IDs**

```bash
curl -s https://pmo.jaideedigital.com/api/v1/projects \
  -H "Authorization: Bearer {ADMIN_TOKEN}" | python3 -m json.tool
```

Record: `MJU_ID` (id where code = MJU-ASSET) and `PMOIS_ID` (id where code = PMOIS)

**Step B: Create MJU Asset project-scoped token**

```bash
curl -s -X POST https://pmo.jaideedigital.com/api/v1/auth/tokens \
  -H "Authorization: Bearer {ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{\"token_name\": \"MJU Asset Integration Token\", \"project_id\": {MJU_ID}}" \
  | python3 -m json.tool
```

Record `raw_token` from response → this is `MJU_TOKEN`.

**Step C: Run all 9 verification scenarios**

Set variables:

```bash
BASE_URL="https://pmo.jaideedigital.com/api/v1"
ADMIN_TOKEN="<from above>"
MJU_TOKEN="<from Step B>"
MJU_ID=<integer>
PMOIS_ID=<integer>
```

Run one-block verification script (copy-paste all at once):

```bash
echo ""
echo "======================================"
echo " PMOIS Phase 4 Verification"
echo " $(date)"
echo "======================================"
echo ""
echo "--- PASS Cases ---"
echo -n "PASS-01 POST status MJU Asset:         "; curl -s -o /dev/null -w "HTTP %{http_code}\n" -X POST "$BASE_URL/projects/$MJU_ID/status" -H "Authorization: Bearer $MJU_TOKEN" -H "Content-Type: application/json" -d '{"status":"on_track","summary":"Verification test — initial status submission","submitted_by_name":"MJU Asset Integration"}'
echo -n "PASS-02 GET latest status MJU Asset:   "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects/$MJU_ID/status" -H "Authorization: Bearer $MJU_TOKEN"
echo -n "PASS-03 GET status history MJU Asset:  "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects/$MJU_ID/status/history" -H "Authorization: Bearer $MJU_TOKEN"
echo ""
echo "--- FAIL Cases ---"
echo -n "FAIL-01 POST to PMOIS with MJU token:  "; curl -s -o /dev/null -w "HTTP %{http_code}\n" -X POST "$BASE_URL/projects/$PMOIS_ID/status" -H "Authorization: Bearer $MJU_TOKEN" -H "Content-Type: application/json" -d '{"status":"on_track","summary":"cross-project test"}'
echo -n "FAIL-02 GET PMOIS status w/ MJU token: "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects/$PMOIS_ID/status" -H "Authorization: Bearer $MJU_TOKEN"
echo -n "FAIL-03 GET /projects w/ MJU token:    "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects" -H "Authorization: Bearer $MJU_TOKEN"
echo -n "FAIL-04 GET /auth/tokens w/ MJU token: "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/auth/tokens" -H "Authorization: Bearer $MJU_TOKEN"
echo -n "FAIL-05 Invalid bearer token:           "; curl -s -o /dev/null -w "HTTP %{http_code}\n" -X POST "$BASE_URL/projects/$MJU_ID/status" -H "Authorization: Bearer invalid_token_00000000000000000000000000000000000000000000000000000000" -H "Content-Type: application/json" -d '{"status":"on_track","summary":"test"}'
echo -n "FAIL-06 No Authorization header:        "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects/$MJU_ID/status"
echo ""
echo "--- Backward Compatibility ---"
echo -n "BC-01 ADMIN can POST to MJU Asset:     "; curl -s -o /dev/null -w "HTTP %{http_code}\n" -X POST "$BASE_URL/projects/$MJU_ID/status" -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" -d '{"status":"on_track","summary":"admin compat check"}'
echo -n "BC-02 ADMIN can GET /projects:          "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects" -H "Authorization: Bearer $ADMIN_TOKEN"
echo -n "BC-03 ADMIN can GET PMOIS status:       "; curl -s -o /dev/null -w "HTTP %{http_code}\n" "$BASE_URL/projects/$PMOIS_ID/status" -H "Authorization: Bearer $ADMIN_TOKEN"
echo ""
echo "======================================"
echo " Expected:"
echo "  PASS-01: HTTP 201  PASS-02: HTTP 200  PASS-03: HTTP 200"
echo "  FAIL-01: HTTP 403  FAIL-02: HTTP 403  FAIL-03: HTTP 403"
echo "  FAIL-04: HTTP 403  FAIL-05: HTTP 401  FAIL-06: HTTP 401"
echo "  BC-01: HTTP 201    BC-02: HTTP 200    BC-03: HTTP 200"
echo "======================================"
```

---

## 8. Current Blockers

None. Implementation is complete, deployed, and ADMIN token is ready.

No code or architecture blockers.

---

## 9. Required Production Commands (Summary)

| Step | Who | Action |
|------|-----|--------|
| 1 | Engineer | Use Production ADMIN token locally — confirm it authenticates |
| 2 | Engineer | `GET /api/v1/projects` — retrieve `MJU_ID` and `PMOIS_ID` |
| 3 | Engineer | `POST /api/v1/auth/tokens` with `project_id: MJU_ID` — create MJU Asset project-scoped token; save raw token |
| 4 | Engineer | Run one-block verification script (Step C in Section 7) |
| 5 | Engineer | Fill in Verification Report with actual results |
| 6 | Engineer | Submit Verification Report for CTO sign-off |
| 7 | CTO | Sign off → freeze PMOIS API v1.0 |

---

## 10. Next Recommended Action

1. Use the Production ADMIN token locally — confirm it authenticates against `https://pmo.jaideedigital.com/api`
2. `GET /api/v1/projects` to retrieve `MJU_ID` and `PMOIS_ID`
3. `POST /api/v1/auth/tokens` with `project_id: MJU_ID` to create the MJU Asset project-scoped token; save the raw token from the response
4. Run the one-block verification script (Section 7, Step C) — all 12 scenarios
5. Fill in `docs/reports/project-scoped-token-enforcement-verification.md` with actual HTTP results
6. Submit Verification Report for CTO sign-off
7. After CTO approval — freeze PMOIS API v1.0

---

## Key File Locations

| Document | Path |
|----------|------|
| Security design | `docs/security/project-scoped-token-enforcement.md` |
| Verification report (to fill in) | `docs/reports/project-scoped-token-enforcement-verification.md` |
| Deployment & verification runbook | `docs/reports/dep-phase4-project-scoped-token-runbook.md` |
| Governance Framework v1.4 | `docs/governance/governance-framework-v1.4.md` |
| Document templates | `docs/templates/` |
| Token provisioner script | `database/provision_pilot_token.php` |
| ProjectScopeMiddleware | `src/Application/Middleware/ProjectScopeMiddleware.php` |
| AuthTokenMiddleware | `src/Application/Middleware/AuthTokenMiddleware.php` |

---

*Handover prepared 2026-06-25. Resume from Section 10 (7-step next action) in new conversation.*
