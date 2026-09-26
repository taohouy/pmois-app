# PMOIS Project Context Handover

**Date:** 2026-06-25  
**Prepared by:** Claude Code  
**Reason:** Conversation context near capacity — handover for new session  
**GitLab repository:** `https://gitlab.com/jaideedigital/pmois-app.git`  
**Production URL:** `https://pmo.jaideedigital.com/api`  
**Latest commit:** `95cd104` on `master`

---

## ⚠️ SUPERSEDING UPDATE — 2026-09-26 (Round 5 — M3 Project Management Completion Gate) — PENDING CEO FINAL M3 UAT

Everything below this box was written 2026-06-25 (Phase 4 / API v1.0 freeze) and is historical —
kept for reference. **This box is the current state. Canonical evidence lives in
`docs/m9/HANDOFF-NOTE-ProjectsUI-RevisionX-DeploymentDiscrepancy.md` — this box is a pointer/summary,
not a duplicate; read that file for exact repro commands, tables, and evidence.**

**Naming note:** this "M3" label (CTO's Completion Gate for the Workspace/Project Tabs work below) is
distinct from the repository's own pre-existing, already-completed milestone literally named M3
(`README-M3-REVISION1.md`, `docs/m3/` — Milestone/Revision/CTO Review workflow, unrelated to
Workspaces/Projects). This session's Workspace/Project Tabs work is filed under `docs/m9/`. Flagging
this here so a future reader searching for "M3" doesn't conflate the two.

- **Round 5 (`79faac9`) — M3 Project Management Completion Gate:**
  - Built an evidence-based Feature Inventory from the repository's actual Source-of-Truth design
    docs (not memory) as the Completion Gate directive required, rather than assuming Round 4's
    ad-hoc "ย้ายพื้นที่ทำงาน" implementation was the final word.
  - **Found it was a duplicate of a separate, pre-existing, already-approved mechanism**:
    `PATCH /api/v1/projects/{id}/structure` → `ProjectStructureController` →
    `ProjectStructureService`, with its own dedicated audit table (`project_structure_history`) and
    business rules (circular-hierarchy / project-code-conflict checks), confirmed as the canonical
    flow by `M0-Design/Revision6/R6-05-Project-Creation-Flow-Revision6.md`. Rewired the frontend to
    use it and removed the duplicate ad-hoc handling from `ProjectController::update()`.
  - **Found and fixed two real, pre-existing defects** in that canonical (but previously unused by
    the UI) flow, neither introduced by this session: (1) `changedBy: 0` hardcoded in all three
    `ProjectStructureService` methods — `project_structure_history` never recorded who made a
    structural change, ever; fixed to use the real acting user id. (2) `ProjectStructureController`
    never checked the caller's permission against the *destination* workspace when moving a project
    — same class of gap fixed twice before in earlier rounds (`WorkspaceController`,
    `ProjectController`), now closed here too.
  - **Confirmed "Change Parent / Move Project" is approved M3 scope**, per R6-05's explicit line that
    Move/Change Parent/Promote flows are unchanged from R5 §2–4 — not an invented feature, now fully
    implemented end-to-end rather than left as the earlier ad-hoc/incomplete version.
  - **One item explicitly reported to CTO, not decided by Dev**: cross-workspace project *creation*
    (as opposed to *moving* an existing project) requires touching ~8 separately session-scoped
    repositories inside `ProjectCreationPipeline` — judged a real architecture change, not a
    stabilization fix, and a risk to the frozen Workspace Isolation guarantee. Per the Completion
    Gate's explicit STOP-and-report instruction, this was reported with evidence and options rather
    than decided unilaterally — see the structured CTO report for this round.
  - Built **one consolidated, idempotent, rerunnable deployment SQL**
    (`deploy/M3_ProjectManagement_CompletionGate_Deploy.sql`) superseding the inline snippet shown in
    the v4 README — verified rerun-safe against seeded test data (BEFORE=4→AFTER=0 first run,
    BEFORE=0→AFTER=0 second run, no duplicate rows, `workspaces`/`projects` row counts unchanged).
  - Full regression re-verified after every change: **200 tests / 523 assertions / 0 failures / 0
    errors / 0 skipped**. Interactive re-test of the Move Project flow end-to-end (including a
    403 false alarm correctly root-caused as a pre-existing frozen-`PermissionResolver`-precedence
    interaction with test seed data, not a code defect — recorded as an out-of-scope observation, not
    "fixed").
  - **Consolidated deployment package**: `PMOIS_v2_ProjectsUI_RevisionX_DeploymentPackage_v5.zip` —
    supersedes v1–v4.
  - **Status: PENDING CEO FINAL M3 UAT — do NOT mark this "Production PASS."** Only CEO's own
    Production runtime confirmation can change this status.

- **Branch:** `claude/loving-allen-dg3dcb` (repo on GitHub: `taohouy/pmois-app`)
- **Round 1 (`bcbc3f3`):** Revision X never deployed + its own `app.js` separately broken. Fixed;
  CEO deployed this package.
- **Round 2 (`85a9efb`):** Deploying Round 1 still failed — `GET/POST/PUT /api/v1/projects` fatally
  errored for every request (pre-existing missing PHP import, predates Revision X) and Workspace
  Activate/Deactivate failed (wrong status enum values in code). Both fixed and Dev-verified.
- **Round 3 (`afe7ef3`):** Consolidated stabilization pass. Found and fixed a security regression in
  this session's own Round 1 code (cross-tenant workspace update/deactivate — permission was checked
  against the caller's own session workspace, not the target). Full contract + security regression
  Dev-verified (200 tests / 523 assertions / 0 failures). 4 unrelated items recorded as Observation/
  OFI, not fixed (app-wide `AppErrorMiddleware` ordering issue; a latent-but-inert import pattern in
  5 other controllers; a generic-500-instead-of-validation-error symptom of the same; pre-existing
  Inbound Status API duplicate-submission behaviour). Governance correction applied in place to
  `TEST-RESULTS-Projects-RevisionX.txt` / `CHANGELOG-Projects-RevisionX.md` (false "Production PASS"
  claims corrected at source, not overwritten with a new contradictory summary).
- **Round 4 (this update) — Projects Management UI Finalization, one continuous cycle:**
  - **Delivered the Workspace Tabs UI concept**: Workspace table + Project table replaced with
    Workspace Tabs (navigation context, `ชื่อ (จำนวน)`, `?ws=` URL persists across refresh) + a
    full-width Project working area (search/status filter/health filter/pagination, all composable).
    Actions renamed to plain Thai (`เปิดใช้งาน`/`ปิดใช้งาน`/`ย้ายพื้นที่ทำงาน`, the last one now
    actually implemented, not a placeholder). K2D self-hosted (8 `.woff2` files + real OFL license
    text under `public/app/fonts/k2d/`, scoped to the Projects page only via `body.projects-page`,
    zero runtime CDN dependency) — confirmed loading in a real headless-Chromium session.
  - **Found the real root cause of the `NOT_FOUND` Activate/Deactivate defect** CEO hit after Round
    3: not an identifier mismatch — `POST /api/v1/workspaces` never added the creator to
    `workspace_members`, so every workspace a platform admin created (including CEO's own test
    workspaces `VERIFY-FIX`/`TEST-AFTER-RESTART`/`SHOULD-FAIL`/`BOOTSTRAP`) left them with zero
    membership/permission over it. Fixed: `create()` now grants the creator `ADMIN` membership via
    the already-existing `addMember()`. Pre-existing affected workspaces need a one-time, optional,
    idempotent SQL backfill (data-only, not a migration — see handoff note or package README).
  - **Found and fixed a second, deeper contract gap** while wiring up multi-workspace tab viewing:
    the whole session model binds one fixed workspace per login, so `GET /api/v1/projects` could
    never return a second workspace's data no matter what the UI asked for. Added an optional,
    permission-checked `?workspace_id=` override to `GET /api/v1/projects` (backward compatible —
    omitting it is identical to before) and to `ProjectRepositoryInterface::listByWorkspace()`/
    `findById()`. This also surfaced (and fixed) two related bugs: `create()`'s and the move-project
    path's internal "read back what I just wrote" step both silently failed (`null`/all-null
    response) when the target workspace differed from the session's own.
  - **New security check added, not just a UI nicety**: moving a project to a different workspace
    previously never verified the caller had any right to place it in the *destination* workspace —
    fixed with the same target-workspace `PermissionResolver::can()` pattern as Round 3's fix.
  - **Deliberately reverted**: cross-workspace project *creation*. `ProjectCreationPipeline` touches
    several other repositories (project members, milestones, tech stack, API tokens, governance
    auto-bind, AI assignment) all separately bound to the session's fixed workspace — making
    creation truly cross-workspace would mean unwinding all of them, which is a real architecture
    change, not stabilization, and risks the frozen Workspace Isolation guarantee. `POST /projects`
    still only creates into the session's own workspace; the response now includes `workspaceId` so
    the frontend can tell the user plainly when that differs from the tab they were viewing, instead
    of a silent/misleading success. Reported as a known limitation per CTO's own instruction for
    exactly this situation.
  - Full regression re-verified after every fix, this round: **200 tests / 523 assertions / 0
    failures / 0 errors / 0 skipped**; full interactive browser test (Playwright + headless
    Chromium, real DOM, real API, real DB) — tabs, search, filters, pagination, add/edit/move/
    activate-deactivate, double-submit guard — all confirmed working with **zero console errors**.
    SweetAlert2's real CDN load could not be exercised (this sandbox's network policy blocks
    `cdn.jsdelivr.net`); interactive tests instead used a same-DOM-API local mock of `window.Swal`,
    exercising the exact same `app.js` code path.
- **Consolidated deployment package for CEO** (this session has no Production FTP/SSH access):
  `PMOIS_v2_ProjectsUI_RevisionX_DeploymentPackage_v4.zip` — supersedes v1/v2/v3; includes the font
  assets, the optional backfill SQL, and a full deployer README.
- **Status: PENDING — do NOT mark this revision PASS or Completed.** Only CEO's actual Production
  runtime check at `https://pmo.jaideedigital.com/app/projects.html` can change this status.
- **Next session should:** ask whether the v4 package (and the optional backfill SQL, if CEO wants
  the old test workspaces fixed too) was applied, and what the runtime check showed. If CEO reports
  FAIL, get the exact on-page error text/console output first, and reproduce it in a local DB before
  guessing — a real MariaDB + headless Chromium (Playwright, already installed) in this session's
  sandbox found every defect across all four rounds within minutes of actually running the app,
  versus static reading finding none of them upfront.

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
