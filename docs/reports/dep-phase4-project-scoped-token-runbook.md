# Deployment & Verification Runbook
# Phase 4 — Project-Scoped Token Enforcement

**Document type:** Deployment Runbook  
**Document code:** DEP-phase4-project-scoped-token-runbook  
**Version:** 2  
**Status:** Active  
**Date:** 2026-06-25  
**Last updated:** 2026-06-25  
**Author:** Claude Code (on behalf of CTO)  
**Project:** PMOIS  
**Phase:** 4 — First Real Project Integration  
**Production URL:** `https://pmo.jaideedigital.com/api`  
**Related documents:**  
- [Security Design](../security/project-scoped-token-enforcement.md)  
- [Verification Report](project-scoped-token-enforcement-verification.md)

---

## Contents

1. [Overview](#1-overview)
2. [Pre-Deployment Checklist](#2-pre-deployment-checklist)
3. [Step 1 — Commit and Push Phase 4 Code](#3-step-1--commit-and-push-phase-4-code)
4. [Step 2 — Deploy to Production Server](#4-step-2--deploy-to-production-server)
5. [Step 3 — Apply Database Migrations](#5-step-3--apply-database-migrations)
6. [Step 4 — Post-Deployment Health Check](#6-step-4--post-deployment-health-check)
7. [Rollback Procedure](#7-rollback-procedure)
8. [Verification Setup](#8-verification-setup)
9. [cURL Verification Scripts](#9-curl-verification-scripts)
10. [Verification Checklist](#10-verification-checklist)

---

## 1. Overview

### What this deployment contains

| Component | Description |
|-----------|-------------|
| New middleware | `ProjectScopeMiddleware` — enforces project isolation for project-scoped tokens |
| Modified middleware | `AuthTokenMiddleware` — fetches and exposes `project_id` from token row |
| Modified repository | `MySqlApiTokenRepository` — stores and returns `project_id` |
| Modified interface | `ApiTokenRepositoryInterface` — adds `?int $projectId` to `create()` |
| Modified controller | `ApiTokenController` — accepts `project_id` in create request |
| Modified routes | `routes.php` — registers `ProjectScopeMiddleware` in group chain |
| Migration 0031 | `ALTER TABLE api_tokens ADD COLUMN project_id ...` |
| Migration 0032 | Seed MJU Asset project (`code = 'MJU-ASSET'`) |

### Risk level

**Low.** Migration 0031 adds a nullable column with `DEFAULT NULL` — all existing tokens are unaffected. Migration 0032 is `INSERT IGNORE` — idempotent and safe to re-run. Both have rollback files.

### Deployment method

**PMOIS Production Standard: Method B — FTP/File Upload**

The PMOIS production server does not perform `git pull` from GitLab. All file updates are uploaded manually via FTP or the existing deployment workflow.

| Method | Description | PMOIS Standard |
|--------|-------------|----------------|
| **Method A** | Git-based (`git pull` on server) | Optional — only if server has Git access |
| **Method B** | FTP / file upload | **Current PMOIS production standard** |

### Estimated time

| Phase | Estimated time |
|-------|---------------|
| Commit + push to GitLab | 2 minutes |
| FTP upload to production server | 5–10 minutes |
| Migrations | < 1 minute |
| Health check | 2 minutes |
| Full verification (9 scenarios) | 10–15 minutes |
| **Total** | **~20–30 minutes** |

---

## 2. Pre-Deployment Checklist

Complete before starting any deployment step.

| # | Check | Done |
|---|-------|------|
| 1 | Phase 4 commit `949a958` is pushed to GitLab (`git log --oneline -1`) | ☐ |
| 2 | Phase 4 file list confirmed (see Section 3 — Files to Upload) | ☐ |
| 3 | Rollback file exists locally: `0031_alter_api_tokens_add_project_id.rollback.sql` | ☐ |
| 4 | FTP credentials / deployment access to production server confirmed | ☐ |
| 5 | MySQL credentials for production database are available | ☐ |
| 6 | ADMIN token for `https://pmo.jaideedigital.com/api` is available | ☐ |
| 7 | A recent database backup exists | ☐ |

---

## 3. Step 1 — Confirm Phase 4 Code on GitLab

> **Status: Complete.** Phase 4 was committed and pushed to GitLab on 2026-06-25 as commit `949a958`. This step is a confirmation check only.

### 3.1 Verify commit on local machine

```powershell
git log --oneline -3
```

Expected: `949a958` appears as the latest commit.

### 3.2 Files to upload to production server

The following files must be uploaded in Step 2. All paths are relative to the project root (`D:\Projects\pmois-app\`).

**New files:**

| Local path | Upload to (server path relative to app root) |
|------------|----------------------------------------------|
| `src/Application/Middleware/ProjectScopeMiddleware.php` | `src/Application/Middleware/ProjectScopeMiddleware.php` |
| `database/migrations/0031_alter_api_tokens_add_project_id.sql` | `database/migrations/0031_alter_api_tokens_add_project_id.sql` |
| `database/migrations/0031_alter_api_tokens_add_project_id.rollback.sql` | `database/migrations/0031_alter_api_tokens_add_project_id.rollback.sql` |
| `database/migrations/0032_seed_mju_asset_project.sql` | `database/migrations/0032_seed_mju_asset_project.sql` |

**Modified files:**

| Local path | Upload to (server path relative to app root) |
|------------|----------------------------------------------|
| `src/Application/Http/Controllers/ApiTokenController.php` | `src/Application/Http/Controllers/ApiTokenController.php` |
| `src/Application/Middleware/AuthTokenMiddleware.php` | `src/Application/Middleware/AuthTokenMiddleware.php` |
| `src/Config/routes.php` | `src/Config/routes.php` |
| `src/Domain/Auth/ApiTokenRepositoryInterface.php` | `src/Domain/Auth/ApiTokenRepositoryInterface.php` |
| `src/Infrastructure/Persistence/MySQL/MySqlApiTokenRepository.php` | `src/Infrastructure/Persistence/MySQL/MySqlApiTokenRepository.php` |

> The `docs/` directory does not need to be uploaded to the production server — documentation lives in the repository only.

---

## 4. Step 2 — Upload Files to Production Server (Method B: FTP)

> **PMOIS Production Standard:** upload files via FTP or the existing deployment workflow. The production server does not perform `git pull`.

### 4.1 Upload all files in the upload list

Using your FTP client (FileZilla, WinSCP, or equivalent), connect to the production server and upload each file from Section 3.2, preserving the directory structure relative to the application root.

Upload order recommendation: upload PHP source files first, then migration SQL files last (migrations are executed separately in Step 3).

### 4.2 Verify uploaded files

After upload, confirm the critical new file is in place. If you have SSH access, run:

```bash
ls -la {APP_PATH}/src/Application/Middleware/ProjectScopeMiddleware.php
```

If SSH is not available, verify via the FTP client that the file exists with the correct size and a recent modification timestamp.

### 4.3 Reload PHP-FPM / clear OPcache (if applicable)

If the server uses PHP-FPM with OPcache, PHP may serve cached versions of modified files until the cache is cleared.

**Option A — if SSH access is available:**

```bash
sudo systemctl reload php{VERSION}-fpm
# Example: sudo systemctl reload php8.1-fpm
```

**Option B — if only FTP access is available:**

Upload a temporary `opcache_reset.php` file to `public/`:

```php
<?php opcache_reset(); echo 'OPcache cleared'; unlink(__FILE__);
```

Then visit `https://pmo.jaideedigital.com/opcache_reset.php` once in a browser. The file deletes itself after execution.

**Option C — if OPcache is not enabled:**

No action required.

### Method A (Git-based) — Alternative if server has Git access

> Use this method only if the production server is confirmed to have Git and GitLab access.

```bash
ssh {SERVER_USER}@{SERVER_HOST}
cd {APP_PATH}
git pull origin master
```

Expected: commit `949a958` pulled, 21 files changed.

---

## 5. Step 3 — Apply Database Migrations

Still on the production server SSH session.

### 5.1 Note current database state

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} -e "DESCRIBE api_tokens;"
```

Confirm that `project_id` column does **not** yet exist. If it already exists, migration 0031 has already been applied — skip to 5.4.

### 5.2 Apply migration 0031 — add `project_id` column

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} < database/migrations/0031_alter_api_tokens_add_project_id.sql
```

Expected: no error output.

### 5.3 Verify migration 0031

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} -e "DESCRIBE api_tokens;"
```

Expected: `project_id` column appears between `workspace_id` and `created_by_user_id` with type `bigint unsigned` and `Null: YES`.

### 5.4 Apply migration 0032 — seed MJU Asset project

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} < database/migrations/0032_seed_mju_asset_project.sql
```

Expected: no error output. The `INSERT IGNORE` means re-running this is safe.

### 5.5 Verify migration 0032

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} \
  -e "SELECT id, code, name, status FROM projects WHERE code = 'MJU-ASSET';"
```

Expected: one row returned showing `code = MJU-ASSET`, `name = MJU Asset`, `status = active`. **Record the `id` value — this is `{MJU_ID}` used in all verification scripts.**

### 5.6 Verify PMOIS project ID

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} \
  -e "SELECT id, code, name FROM projects WHERE code = 'PMOIS';"
```

**Record the `id` value — this is `{PMOIS_ID}` used in FAIL scenarios.**

---

## 6. Step 4 — Post-Deployment Health Check

From any machine (local or server), confirm the application is responding.

```bash
curl -s https://pmo.jaideedigital.com/api/v1/health
```

Expected response:

```json
{"status":"ok"}
```

If this does not return within 5 seconds or returns an error, **stop and investigate before running any migration verification.**

---

## 7. Rollback Procedure

### When to roll back

Roll back if any of the following occur:
- Migration 0031 returns an error (column already exists with different type, FK error, etc.)
- The health check (Section 6) fails after deployment
- Production traffic is impacted after deployment

### Rollback: migration 0031

```bash
mysql -u {DB_USER} -p{DB_PASS} {DB_NAME} \
  < database/migrations/0031_alter_api_tokens_add_project_id.rollback.sql
```

SQL executed:

```sql
ALTER TABLE api_tokens
    DROP FOREIGN KEY fk_tokens_project,
    DROP COLUMN project_id;
```

**Safe condition:** rollback is safe at any time as long as no project-scoped tokens have been created (i.e., no rows in `api_tokens` have a non-NULL `project_id`). If project-scoped tokens already exist, dropping the column will silently discard that data.

### Rollback: migration 0032

The MJU Asset project seed does not have a rollback file because removing a project requires checking for dependent data. If a rollback of the seed is required:

```sql
-- Check for dependent rows before deleting
SELECT COUNT(*) FROM project_status_updates
  WHERE project_id = (SELECT id FROM projects WHERE code = 'MJU-ASSET');

-- Only delete if count = 0
DELETE FROM projects WHERE code = 'MJU-ASSET';
```

### Rollback: application code

```bash
git revert HEAD --no-edit
git push origin master
# Then re-pull on the server and reload PHP-FPM
```

---

## 8. Verification Setup

Complete this section after deployment and migrations are confirmed healthy.

### 8.1 Set shell variables

On the machine where you will run the curl scripts, set the following variables. Replace placeholder values with actual values from the deployment steps above.

```bash
# Production API base URL
BASE_URL="https://pmo.jaideedigital.com/api/v1"

# Existing ADMIN token (workspace-level, project_id = NULL)
ADMIN_TOKEN="<paste_admin_token_here>"

# MJU Asset project ID (from migration 0032 verification in Step 5.5)
MJU_ID=<paste_mju_id_here>

# PMOIS project ID (from Step 5.6)
PMOIS_ID=<paste_pmois_id_here>
```

### 8.2 Create MJU Asset project-scoped token

Run this command **once** using the ADMIN token. Save the `raw_token` from the response — it will not be shown again.

```bash
curl -s -X POST "$BASE_URL/auth/tokens" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"token_name\": \"MJU Asset Integration Token\", \"project_id\": $MJU_ID}" \
  | python3 -m json.tool
```

Expected response (HTTP 201):

```json
{
  "success": true,
  "data": {
    "id": <integer>,
    "token_name": "MJU Asset Integration Token",
    "project_id": <MJU_ID>,
    "raw_token": "<64-char-hex-string>",
    "warning": "เก็บ raw_token นี้ไว้ตอนนี้เท่านั้น จะไม่แสดงซ้ำอีก"
  },
  "error": null,
  "meta": { ... }
}
```

**Record the `raw_token` value now**, then set the variable:

```bash
MJU_TOKEN="<paste_raw_token_here>"
```

---

## 9. cURL Verification Scripts

All scripts use the variables set in Section 8.1. Run them in order. For each, note the actual HTTP status code returned.

The `-o /dev/null -w "%{http_code}"` flag prints only the HTTP status code for quick pass/fail reading. To see the full response body, remove those flags.

---

### PASS-01 — POST Project Status for MJU Asset

**Expected: HTTP 201**

```bash
echo "=== PASS-01: POST status for MJU Asset ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -X POST "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Verification test — initial status submission","submitted_by_name":"MJU Asset Integration"}'
```

Full response version (run to confirm `data.project_id` and `data.status`):

```bash
curl -s -X POST "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Verification test — initial status submission","submitted_by_name":"MJU Asset Integration"}' \
  | python3 -m json.tool
```

---

### PASS-02 — GET Latest Status for MJU Asset

**Expected: HTTP 200**

```bash
echo "=== PASS-02: GET latest status for MJU Asset ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN"
```

Full response version:

```bash
curl -s "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  | python3 -m json.tool
```

---

### PASS-03 — GET Status History for MJU Asset

**Expected: HTTP 200**

```bash
echo "=== PASS-03: GET status history for MJU Asset ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects/$MJU_ID/status/history" \
  -H "Authorization: Bearer $MJU_TOKEN"
```

Full response version:

```bash
curl -s "$BASE_URL/projects/$MJU_ID/status/history" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  | python3 -m json.tool
```

---

### FAIL-01 — POST Status to PMOIS Project with MJU Token

**Expected: HTTP 403 — "Token is not authorized for this project"**

```bash
echo "=== FAIL-01: POST to PMOIS project with MJU token ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -X POST "$BASE_URL/projects/$PMOIS_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Cross-project injection attempt"}'
```

Full response version (confirm `error.code = FORBIDDEN` and `error.message`):

```bash
curl -s -X POST "$BASE_URL/projects/$PMOIS_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Cross-project injection attempt"}' \
  | python3 -m json.tool
```

---

### FAIL-02 — GET Status from PMOIS Project with MJU Token

**Expected: HTTP 403 — "Token is not authorized for this project"**

```bash
echo "=== FAIL-02: GET status from PMOIS project with MJU token ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects/$PMOIS_ID/status" \
  -H "Authorization: Bearer $MJU_TOKEN"
```

---

### FAIL-03 — Workspace-Level Route: GET /projects

**Expected: HTTP 403 — "Project-scoped token cannot access workspace-level resources"**

```bash
echo "=== FAIL-03: GET /projects with MJU token (workspace-level route) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects" \
  -H "Authorization: Bearer $MJU_TOKEN"
```

Full response version (confirm `error.message`):

```bash
curl -s "$BASE_URL/projects" \
  -H "Authorization: Bearer $MJU_TOKEN" \
  | python3 -m json.tool
```

---

### FAIL-04 — Workspace-Level Route: GET /auth/tokens

**Expected: HTTP 403 — "Project-scoped token cannot access workspace-level resources"**

```bash
echo "=== FAIL-04: GET /auth/tokens with MJU token (workspace-level route) ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/auth/tokens" \
  -H "Authorization: Bearer $MJU_TOKEN"
```

---

### FAIL-05 — Invalid Bearer Token

**Expected: HTTP 401 — "Token not found"**

```bash
echo "=== FAIL-05: Invalid bearer token ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -X POST "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer this_is_not_a_valid_token_000000000000000000000000000000000" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Test"}'
```

---

### FAIL-06 — Missing Authorization Header

**Expected: HTTP 401 — "Missing or invalid Authorization header"**

```bash
echo "=== FAIL-06: Missing Authorization header ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects/$MJU_ID/status"
```

---

### Backward Compatibility Check — ADMIN Token Unaffected

These two additional checks confirm that the ADMIN token behaviour is unchanged. They are not in the verification report scenarios but should be run and recorded here.

```bash
echo "=== BC-01: ADMIN token can POST to MJU Asset ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  -X POST "$BASE_URL/projects/$MJU_ID/status" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"on_track","summary":"Admin backward compatibility check"}'

echo "=== BC-02: ADMIN token can access workspace-level routes ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects" \
  -H "Authorization: Bearer $ADMIN_TOKEN"

echo "=== BC-03: ADMIN token can access PMOIS project ==="
curl -s -o /dev/null -w "HTTP %{http_code}\n" \
  "$BASE_URL/projects/$PMOIS_ID/status" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

All three expected: HTTP 200 or 201.

---

### Run all scenarios in one block

Copy-paste this entire block after setting all variables in Section 8.1 and 8.2.

```bash
echo ""
echo "======================================"
echo " PMOIS Phase 4 Verification — All Scenarios"
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
echo " Expected results:"
echo "  PASS-01: HTTP 201"
echo "  PASS-02: HTTP 200"
echo "  PASS-03: HTTP 200"
echo "  FAIL-01: HTTP 403"
echo "  FAIL-02: HTTP 403"
echo "  FAIL-03: HTTP 403"
echo "  FAIL-04: HTTP 403"
echo "  FAIL-05: HTTP 401"
echo "  FAIL-06: HTTP 401"
echo "  BC-01:   HTTP 201"
echo "  BC-02:   HTTP 200"
echo "  BC-03:   HTTP 200 (or 404 if no status yet)"
echo "======================================"
```

---

## 10. Verification Checklist

Complete after running all curl scripts. Tick each item when confirmed.

### Deployment

| # | Check | Done |
|---|-------|------|
| 1 | All 9 files from Section 3.2 uploaded to production server via FTP | ☐ |
| 2 | `ProjectScopeMiddleware.php` confirmed present on production server | ☐ |
| 3 | OPcache cleared (if applicable) | ☐ |
| 4 | Migration 0031 applied — `project_id` column visible in `DESCRIBE api_tokens` | ☐ |
| 5 | Migration 0032 applied — `MJU-ASSET` project exists in `projects` table | ☐ |
| 6 | `GET /api/v1/health` returns `{"status":"ok"}` | ☐ |

### Token creation

| # | Check | Done |
|---|-------|------|
| 6 | MJU Asset project ID (`{MJU_ID}`) confirmed from database | ☐ |
| 7 | PMOIS project ID (`{PMOIS_ID}`) confirmed from database | ☐ |
| 8 | MJU Asset project-scoped token created via API (HTTP 201) | ☐ |
| 9 | `project_id` in create response matches `{MJU_ID}` | ☐ |
| 10 | `raw_token` saved securely | ☐ |

### Verification scenarios

| Test ID | Expected HTTP | Actual HTTP | Pass |
|---------|---------------|-------------|------|
| PASS-01 | 201 | | ☐ |
| PASS-02 | 200 | | ☐ |
| PASS-03 | 200 | | ☐ |
| FAIL-01 | 403 | | ☐ |
| FAIL-02 | 403 | | ☐ |
| FAIL-03 | 403 | | ☐ |
| FAIL-04 | 403 | | ☐ |
| FAIL-05 | 401 | | ☐ |
| FAIL-06 | 401 | | ☐ |
| BC-01 | 201 | | ☐ |
| BC-02 | 200 | | ☐ |
| BC-03 | 200 / 404 | | ☐ |

### Sign-off

| # | Check | Done |
|---|-------|------|
| 11 | All 9 main verification scenarios match expected HTTP status | ☐ |
| 12 | All 3 backward compatibility checks pass | ☐ |
| 13 | Verification Report updated with actual results | ☐ |
| 14 | Verification Report submitted for CTO sign-off | ☐ |

---

*Runbook prepared 2026-06-25. Ready for execution after CTO authorises deployment.*
