# PMOIS Project Context Handover

**Date:** 2026-06-25  
**Prepared by:** Claude Code  
**Reason:** Conversation context near capacity — handover for new session  
**GitLab repository:** `https://gitlab.com/jaideedigital/pmois-app.git`  
**Production URL:** `https://pmo.jaideedigital.com/api`  
**Latest commit:** `95cd104` on `master`

---

## ⚠️ SUPERSEDING UPDATE — 2026-09-26 (Round 2) — Projects UI Revision X: Production Runtime Discrepancy (OPEN, pending CEO verification)

Everything below this box was written 2026-06-25 (Phase 4 / API v1.0 freeze) and is historical —
kept for reference. **This box is the current state.**

- **Branch:** `claude/loving-allen-dg3dcb` (repo on GitHub: `taohouy/pmois-app`)
- **Round 1 (commit `bcbc3f3`):** Fixed the reason Production showed the old single-form page at
  all — Revision X (`d896372`) was never deployed, and even if it had been, its own `app.js` was
  fatally broken (SyntaxError, deleted `topbar()`/`logout()`, undefined `showSwal()`, no
  `PUT /workspaces/{id}`/`PUT /projects/{id}` backend routes). CEO deployed this package.
- **Round 2 result — still UAT FAIL, two NEW defects found:** after deploying Round 1, CEO reported
  Project List showing `Error: UNKNOWN` and Workspace Activate/Deactivate failing. This session
  installed a real MariaDB + PHP server in its sandbox (previously had neither) and reproduced both
  failures against real HTTP requests + real DB, then fixed them:
  - **`GET/POST/PUT /api/v1/projects` fatally errored for every request** — pre-existing bug from
    the original `23256f3` baseline (predates Revision X entirely): `src/Config/dependencies.php`
    was missing one `use` import (`MySqlProjectReleaseRepository`), and since that file has no
    namespace, PHP couldn't find the class — `Error: Class "MySqlProjectReleaseRepository" not
    found`. This broke `ProjectController` entirely (list, create, close), not just the list view.
    One-line fix, verified with a real request afterward (200 OK, both `PMOIS-001` and `MJU-ASSET`
    now returned).
  - **Workspace Activate/Deactivate always failed** — the Revision X design (and this session's own
    Round 1 code) assumed `workspaces.status` could be `active`/`planning`/`on_hold`, but the real
    DB schema only allows `ENUM('active','inactive')` (the 3-value enum belongs to
    `projects.status`, not `workspaces.status`). Every activate/deactivate attempt sent `on_hold` →
    `PDOException: Data truncated for column 'status'` → 500. Fixed in 3 files (frontend select
    options + toggle logic + backend validation) to use `active`/`inactive` throughout. Verified
    with real PUT requests toggling both directions successfully.
  - Also fixed (proactively, per CTO's instruction not to reduce structured errors to "UNKNOWN"):
    `app.js`'s `api()` helper no longer hardcodes the literal string `'UNKNOWN'` — it now surfaces
    the real HTTP status/message and logs full details to the browser console.
  - Full detail, including the exact repro commands and server log output:
    `docs/m9/HANDOFF-NOTE-ProjectsUI-RevisionX-DeploymentDiscrepancy.md`
- **Verification this round (real, not static):** fresh DB from `deploy/PMOIS_v2_Database_Install.sql`
  → full PHPUnit integration suite **200 tests / 523 assertions / 0 failures** → manual `curl`
  walk of the entire contract (GET/POST/PUT workspaces, GET/POST/PUT projects, persistence-after-
  refresh) against the real running app → negative-permission test with a `MEMBER`-role session
  confirming `PermissionResolver`/Workspace Scope still correctly blocks `workspace.update` while
  allowing `project.update` → `audit_trails` table inspected directly to confirm every write is
  still logged. **Still not verified: the actual Production server** — this was a sandbox DB with
  synthetic data, not `pmo.jaideedigital.com`.
- **Consolidated deployment package prepared for CEO** (this session has no Production FTP/SSH
  access): `PMOIS_v2_ProjectsUI_RevisionX_DeploymentPackage.zip` (v2) — supersedes the Round 1 zip,
  contains all 7 files at their current (Round 1 + Round 2) state plus the deployer README.
- **Status: PENDING — do NOT mark this revision PASS or Completed.** Two rounds of CTO UAT have
  already failed after a "verified" fix; the next status change must come from CEO's actual
  Production runtime check, not from this session's local verification, however thorough.
- **Next session should:** ask whether the Round 2 package was uploaded and what the runtime check
  showed before doing anything else on Projects UI. If CEO reports FAIL again, resist the urge to
  guess — get the exact on-page error text and/or browser console output first, and prefer
  reproducing it in a local DB (a real MariaDB was successfully installed in this session's sandbox
  — `apt-get install -y mariadb-server`, root available) over re-reading code, since static reading
  missed both Round 2 defects and running the app found them in minutes.

---

## 1. Current Project Status

**🎉 PMOIS API v1.0 — FROZEN**

Phase 4 (Project-Scoped Token Enforcement) completed and approved by CTO on 2026-06-25.

| Item | Status |
|------|--------|
| Implementation | ✅ Complete |
| Production deployment (FTP upload) | ✅ Complete |
| Database migrations 0031 + 0032 | ✅ Applied |
| Production health check | ✅ PASS |
| Production verification (12 scenarios) | ✅ All PASS |
| Verification Report | ✅ Approved by CTO — 2026-06-25 |
| MJU Asset project-scoped token | ✅ Created — token id: 8 |
| **PMOIS API v1.0** | ✅ **FROZEN** — ADR-0001 |

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

None. PMOIS API v1.0 is frozen. No open blockers.

---

## 9. PMOIS API v1.0 Milestone Summary

| Milestone | Date | Outcome |
|-----------|------|---------|
| Phase 1 — Core API | Prior to 2026-06-25 | Complete |
| Phase 2 — Project Status Updates | Prior to 2026-06-25 | Complete |
| Phase 3 — AI Access Control | Prior to 2026-06-25 | Complete |
| Phase 4 — Project-Scoped Token Enforcement | 2026-06-25 | Complete |
| Production Verification (12 scenarios) | 2026-06-25 | All PASS |
| CTO Sign-off | 2026-06-25 | Approved |
| **PMOIS API v1.0 Freeze** | **2026-06-25** | **Frozen** |
| First integration: MJU Asset | 2026-06-25 | Cleared |

---

## 10. Next Steps

PMOIS API v1.0 is frozen. Future work should follow ADR-0001:

- Additive changes (new optional fields, new routes) are permitted within v1.
- Breaking changes require a `v2` version bump.
- New project integrations: create a project-scoped token via `POST /api/v1/auth/tokens` with `project_id`.
- Known hardening items for a future phase: cross-workspace FK validation at token creation.

---

## Key File Locations

| Document | Path |
|----------|------|
| **API v1.0 Freeze ADR** | `docs/adr/ADR-0001-pmois-api-v1.0-freeze.md` |
| Verification report (approved) | `docs/reports/project-scoped-token-enforcement-verification.md` |
| Security design | `docs/security/project-scoped-token-enforcement.md` |
| Deployment & verification runbook | `docs/reports/dep-phase4-project-scoped-token-runbook.md` |
| Governance Framework v1.4 | `docs/governance/governance-framework-v1.4.md` |
| Document templates | `docs/templates/` |
| Token provisioner script | `database/provision_pilot_token.php` |
| ProjectScopeMiddleware | `src/Application/Middleware/ProjectScopeMiddleware.php` |
| AuthTokenMiddleware | `src/Application/Middleware/AuthTokenMiddleware.php` |

---

*Handover last updated 2026-06-25. PMOIS API v1.0 frozen. See ADR-0001 for freeze decision record.*
