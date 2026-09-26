# PMOIS v2 — Projects UI Revision X — Production Runtime Discrepancy — Root Cause & Fix

**Date:** 2026-09-26 (Round 4 — Projects Management UI Finalization: Workspace Tabs redesign + deeper contract fixes found during that work)
**Trigger:** CTO/CEO UAT finding — Production `https://pmo.jaideedigital.com/app/projects.html`.

**Governance note on labels used in this document:** "Dev Verification" below means this session
ran the real application against a real MariaDB instance and real HTTP requests inside its own
isolated sandbox — it is genuine runtime evidence, not a mock or a source-code read, but it is
**not** Production. "Production PASS" is reserved exclusively for CEO's own runtime check against
`https://pmo.jaideedigital.com`. Earlier documents in this repository (`TEST-RESULTS-Projects-RevisionX.txt`,
`CHANGELOG-Projects-RevisionX.md` §6) incorrectly used Production-PASS language for what was, at
best, unverified and at worst never-executed claims — those files have been corrected in place
(a notice was added at the point of the false claim) rather than left standing next to this newer
account, per governance instruction. This document is the canonical source of truth for the
Projects UI Revision X effort going forward.

This note has three parts: **Round 1** (why Production showed the old form at all), **Round 2**
(why, after deploying the Round 1 fix, Project List still failed with `Error: UNKNOWN` and
Activate/Deactivate still failed), and **Round 3** (a consolidated stabilization + security pass
covering the full Workspace/Project contract, requested after Round 2, done in one continuous
cycle rather than one more single-symptom patch). All defects from Round 2 onward were found and
confirmed by actually running the application end-to-end against a real MySQL/MariaDB instance in
this session's sandbox — not by static reading — so the fixes below are Dev-verified, not
theoretical.

---

## Round 1 — why Production showed the pre-Revision-X form at all

Two problems:

1. **Deployment gap.** Commit `d896372` implemented Revision X but was never deployed —
   Production kept serving the older `23256f3` build.
2. **Revision X itself didn't run.** A fatal JS `SyntaxError` (`await` outside `async`),
   `topbar()`/`logout()` deleted, `showSwal()` never defined, a bad SweetAlert2 SRI hash, malformed
   `onclick` HTML, Edit actions that always `POST`ed, and missing `PUT /workspaces/{id}` /
   `PUT /projects/{id}` endpoints. Fixed in commit `bcbc3f3`. Full detail preserved below in
   "Round 1 defect table".

## Round 2 — found by actually running the app (this update)

After the Round 1 package was deployed, CTO reported: **Project List → `Error: UNKNOWN`**, and
**Workspace Activate/Deactivate → fails**. This session installed MariaDB and PHP's built-in
server locally, loaded `deploy/PMOIS_v2_Database_Install.sql`, created a real session + workspace +
projects, and hit the actual endpoints with `curl`. Two more real, previously-undetected defects
were found — both pre-existing in the codebase, **not caused by the Round 1 fix**, just newly
exposed because Round 1 was the first time `GET/PUT /api/v1/projects` and
`PUT /api/v1/workspaces/{id}` were ever exercised end-to-end:

### Defect A — `GET /api/v1/projects` fatally errors for every request (→ "Project List does not load" / `Error: UNKNOWN`)

Reproduced directly:
```
$ curl -b pmois_session=... http://.../api/v1/projects
HTTP/1.1 500 Internal Server Error
{"message":"Slim Application Error"}
```
Server log:
```
Type: Error
Message: Class "MySqlProjectReleaseRepository" not found
File: src/Config/dependencies.php, Line: 282
```
**Root cause:** `src/Config/dependencies.php` has no `namespace` declaration (it's a plain global
file), so every class reference in it must be either `use`-imported or fully qualified. Line 282
does `new MySqlProjectReleaseRepository(...)` but the corresponding
`use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;` import was missing —
sitting between two sibling imports (`MySqlProjectMemberRepository` / `MySqlProjectRepository`)
that *are* present. This is a **pre-existing bug from commit `23256f3`** (the original M9 baseline,
before any Revision X work), not something Round 1 introduced.

**Why it broke *every* Project request, not just the list:** `ProjectController` depends on
`ProjectCreationPipeline` → `ProfileCompletenessCalculator` → `ProjectReleaseRepositoryInterface`
→ (broken) `MySqlProjectReleaseRepository`. Since `ProjectController`'s constructor pulls in
`ProjectCreationPipeline` regardless of which method is being called, **`index()`, `create()`, and
`close()` were all fatally broken by this the whole time** — this was never Revision-X-specific.
It surfaced now because Round 1 was the first time this session actually drove
`GET /api/v1/projects` through the real DI container end-to-end.

**Fix:** added the one missing `use` line in `src/Config/dependencies.php`. Verified: `GET
/api/v1/projects` now returns `200` with both `PMOIS-001` and `MJU-ASSET` test records including
`workspaceCode`/`development_mode`/`progress`/`health`.

**Note (found, left alone — out of scope):** the same "bare class name in a namespace-less file"
mistake also exists for `AnalyticsController`, `AutomationController`, `KnowledgeController`,
`AuditController`, and `PlatformController` (their factory entries use unqualified class names
too). Unlike the Project one, these are **not actually broken in practice**: their container keys
are *also* unqualified (e.g. registered under the bare string `"AnalyticsController"` instead of
`"App\Application\Http\Controllers\AnalyticsController"`), so when Slim asks the container for the
real, fully-qualified class, the broken entry is never matched and PHP-DI's autowiring builds the
real class directly instead, which happens to succeed. Confirmed by all 200 existing PHPUnit
integration tests passing (see below), including the M6/M7/M8 test suites for those exact modules.
Left untouched per "do not modify unrelated PMOIS modules" — flagging here for awareness only.

### Defect B — `workspaces.status` accepts only `active`/`inactive`, not `active`/`planning`/`on_hold` (→ Activate/Deactivate fails)

Reproduced directly:
```
$ curl -X PUT .../api/v1/workspaces/1 -d '{"status":"on_hold"}'
HTTP/1.1 500 Internal Server Error
```
Server log:
```
Type: PDOException
Message: SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status' at row 1
```
**Root cause:** `database/migrations` define `workspaces.status` as `ENUM('active','inactive')`.
The Revision X design (`CHANGELOG-Projects-RevisionX.md`) and this session's Round 1 fix both
assumed `workspaces.status` could be `active`/`planning`/`on_hold` — that's actually the `projects.status`
enum, not `workspaces.status`. The Activate/Deactivate action toggles to `'on_hold'`, which the
`workspaces` table's real schema rejects outright.

**Fix (3 files, same value everywhere now — `active`/`inactive`):**
- `src/Application/Http/Controllers/WorkspaceController.php` — validation list changed from
  `['active','planning','on_hold']` to `['active','inactive']`.
- `public/app/app.js` — Add/Edit Workspace modal's Status `<select>` now offers `active`/`inactive`
  (was `active`/`planning`/`on_hold`); `activateDeactivateWorkspace()` toggles `active`⇄`inactive`
  (was `active`⇄`on_hold`); workspace list badge colors `active`→green, else→gray (was a 3-way
  ternary referencing the nonexistent `planning` state).

Verified: `PUT /api/v1/workspaces/{id}` with `{"status":"inactive"}` and back to `{"status":"active"}`
both return `200` and persist; `activateDeactivateWorkspace()` in the browser now sends a value the
database actually accepts.

### Defect C (fixed proactively, not separately reported by CTO) — frontend was swallowing real errors as literally "UNKNOWN"

`api()` in `app.js` set `err.code = 'UNKNOWN'` (a literal string, not a fallback) whenever a
non-2xx response lacked a `{error:{code,message}}` envelope — which is exactly what a raw Slim
crash (Defect A, before the fix) or an unenveloped error looks like. This is why the CTO saw the
unhelpful literal text `Error: UNKNOWN` instead of a diagnosable message. Fixed: `api()` now falls
back to `HTTP_<status>` / the actual `res.statusText` instead of a hardcoded string, logs the full
response to the browser console (`console.error`), and `loadWorkspaces()`/`loadProjects()` render
both `err.code` **and** `err.message` in the on-page error state instead of code alone. This does
not change the underlying bug (Defect A did, above) but means a *future* backend error will show
up as something diagnosable instead of a dead end.

---

## Verified in this session (real runtime, not static reading)

This session installed `mariadb-server` and ran PHP's built-in server locally (root access
available in this container, unlike the earlier session that had neither). This is a genuine
change from the previous handoff note, which had no DB available at all.

- **Fresh DB from the real installer:** `mariadb-install-db` + `deploy/PMOIS_v2_Database_Install.sql`
  applied cleanly (46 tables).
- **Full integration suite:** `php vendor/phpunit/phpunit/phpunit tests/Integration` →
  **200 tests, 523 assertions, 0 failures** on a clean DB with all fixes applied (confirms no
  regression from any of the Round 1 or Round 2 changes, across every module — M1 through M9).
- **Manual end-to-end contract walk, via `curl` against the real running app + real DB**, exactly
  reproducing and then fixing both reported failures:
  - `GET /api/v1/workspaces` → 200, real data
  - `POST /api/v1/workspaces` (Create) → 200, persisted
  - `PUT /api/v1/workspaces/{id}` (Edit name/description/status) → 200, persisted
  - `PUT /api/v1/workspaces/{id}` (Activate/Deactivate, `active`⇄`inactive`) → 200, persisted
  - `GET /api/v1/projects` → 200, returns workspaceCode/development_mode/progress/health/current_milestone
  - `POST /api/v1/projects` (Create, with required `cto_user_id`/`dev_user_id` — see note below) → 200, persisted
  - `PUT /api/v1/projects/{id}` (progress/health) → 200, persisted
  - Refresh (`GET` again after each write) → data persists
  - **Permission/Workspace Scope regression check:** created a second session as a `MEMBER`-role
    user (has `project.update` but not `workspace.update` per the seeded role matrix) — confirmed
    `PUT /api/v1/workspaces/{id}` correctly returns `403 Forbidden` for that user while
    `PUT /api/v1/projects/{id}` correctly succeeds. `PermissionResolver` / Workspace Scope
    enforcement is intact and unchanged by this fix.
  - **Audit Trail regression check:** inspected `audit_trails` table directly — every write above
    (workspace create, workspace update, project create, project update) recorded a row with the
    correct `entity_type`/`entity_id`/`action`/`workspace_id`.

**Note on Create Project:** `POST /api/v1/projects` requires either `cto_user_id`/`dev_user_id` in
the request body or a `workspace_default_settings` row for the workspace (pre-existing
`ProjectCreationPipeline` behavior from the R6 design, unrelated to this fix — confirmed by
supplying the IDs explicitly, after which creation succeeded normally). Not a defect; noted here so
it isn't mistaken for one during CEO's manual walkthrough if the test workspace has no defaults
configured.

**Still not verified — and cannot be from this sandbox:** the actual `pmo.jaideedigital.com`
Production server and its live database/session data. This sandbox's MariaDB instance was created
fresh with synthetic data; Production's real data, PHP version, web server config, and OPcache
behavior have not been touched or observed. CEO's Production runtime check is still the acceptance
gate, not this session's local verification.

---

## Round 3 — Consolidated Projects Module Stabilization + First Real Project Readiness

CTO asked for one continuous pass covering the full Workspace/Project contract, security
regression, MJU Asset compatibility, and future-project onboarding readiness, rather than another
single-symptom patch. Done in one sandbox session, re-using and extending the Round 2 environment
(fresh `deploy/PMOIS_v2_Database_Install.sql`, real PHP built-in server, real MariaDB).

### New defect found and fixed: cross-tenant Workspace privilege escalation (security regression, introduced by this session's own Round 1 code)

While running the mandatory security regression checklist, this session found that its own Round 1
`PUT /api/v1/workspaces/{id}` endpoint let an ADMIN of **workspace A** update or deactivate
**workspace B**, which they are not a member of. Reproduced directly:

```
# session belongs only to workspace 2 ("OTHERWS")
$ curl -X PUT .../api/v1/workspaces/1 -b pmois_session=<ws2-admin> -d '{"status":"inactive"}'
HTTP/1.1 200 OK
{"success":true,"data":{"id":1,"code":"JAIDEE",...,"status":"inactive"}}   # workspace 1 belongs to someone else!
```

**Root cause:** `RequiresPermissionMiddleware('workspace.update')` bound to this route checks
whether the caller has `workspace.update` in **their own session's workspace** — it has no way to
know the route's `{id}` might be a *different* workspace. `WorkspaceRepositoryInterface` is
deliberately a top-level, unscoped repository (there is nothing above "workspace" to scope by), so
`WorkspaceController::update()` calling `$this->workspaceRepo->findById($id)` would happily find
and update **any** workspace in the system. The codebase's own `show()` method has a comment
explaining exactly this trap ("WorkspaceRepository เป็น top-level ไม่ scope ด้วย workspace_id ...
Controller ต้องเป็นคนเช็ค membership เพิ่มเอง") and does re-check membership manually — `update()`,
added in Round 1, did not follow that same pattern. This is a defect in this session's own prior
work, not a pre-existing one; it is fixed before any package is sent to CEO for deployment.

**Fix:** `WorkspaceController::update()` now calls `$this->permissionResolver->can($userId, $id,
null, 'workspace.update')` — explicitly checking the permission against the **target** workspace
id from the route, not the session's own workspace — and returns `404` (matching `show()`'s
"don't leak existence" convention) if the caller isn't a permitted member of that specific
workspace.

**Verified (Dev, this sandbox):**
- Cross-tenant attempt (ws2 ADMIN → ws1) now returns `404`, and workspace 1's status is confirmed
  unchanged in the database.
- Legitimate same-workspace update (ws1 ADMIN → ws1) still returns `200` and persists.
- `MEMBER` role (lacks `workspace.update` even in their own workspace) still correctly gets `403`.
- Full integration suite re-run after this fix: **200 tests, 523 assertions, 0 failures, 0 errors,
  0 skipped.**

`ProjectController::update()` was checked for the same class of bug and found **not** vulnerable:
`ProjectRepositoryInterface::findById()` is workspace-scoped via `BaseRepository::applyWorkspaceScope()`
(unlike the top-level Workspace repo), so a cross-workspace project update attempt already
correctly returns `404` — verified directly (ws2 admin → ws1's `PMOIS-001` project → `404`).

### Full Workspace/Project contract — Dev-verified end to end

Seeded: 2 workspaces (to test isolation), an ADMIN and a MEMBER user in workspace 1, an ADMIN-only
user in workspace 2, a `PMOIS-001` and `MJU-ASSET` project in workspace 1 (mirroring the real
Production project codes), a project in workspace 2 (isolation target), one project-scoped API
token bound to the `MJU-ASSET` project, and one workspace-level (ADMIN) API token.

| Contract item | Method + Route | Result |
|---|---|---|
| GET Workspaces | `GET /api/v1/workspaces` | 200, real data |
| Create Workspace | `POST /api/v1/workspaces` | 200, persisted |
| Update Workspace | `PUT /api/v1/workspaces/{id}` | 200, persisted (own workspace only — see security fix above) |
| Activate/Deactivate Workspace | `PUT /api/v1/workspaces/{id}` (`status`) | 200 both directions, persisted |
| GET Projects | `GET /api/v1/projects` | 200, includes workspaceCode/development_mode/progress/health/current_milestone |
| Create Project | `POST /api/v1/projects` | 200, persisted (see note on `cto_user_id`/`dev_user_id` below) |
| Update Project | `PUT /api/v1/projects/{id}` | 200, persisted (progress/health/workspace only — see §"immutable fields" below) |
| Unauthenticated access | `GET /api/v1/projects` (no session) | 401, correctly rejected |
| Workspace isolation (GET) | ws2 admin → `GET /projects` | Returns only ws2's own project, never ws1's |
| Workspace isolation (PUT, project) | ws2 admin → `PUT /projects/{ws1 project id}` | 404 |
| Workspace isolation (PUT, workspace) | ws2 admin → `PUT /workspaces/{ws1 id}` | 404 (fixed this round — was 200 before the fix above) |
| Permission enforcement | MEMBER → `PUT /workspaces/{own id}` | 403 (lacks `workspace.update`) |
| Permission enforcement | MEMBER → `PUT /projects/{own workspace's project}` | 200 (has `project.update`) |
| Audit Trail | every write above | row present in `audit_trails` with correct entity/action/workspace_id |

### Project-Scoped Token Enforcement — regression-tested, frozen behaviour unchanged

| Check | Result |
|---|---|
| Project-scoped token → its own project's Inbound Status endpoints (`POST/GET status`, `GET status/history`) | Succeeds |
| Project-scoped token → a *different* project's status endpoints | `403 FORBIDDEN — "Token is not authorized for this project"` |
| Project-scoped token → workspace-level route (`GET /workspaces`) | `403 FORBIDDEN — "Project-scoped token cannot access workspace-level resources"` |
| Workspace-level (ADMIN) token → `GET /workspaces` | Succeeds |

None of this session's changes touch `ProjectScopeMiddleware`, `AuthTokenMiddleware`, or the token
tables — this table confirms the frozen behaviour still holds, not that it was changed.

### Inbound Status API — regression-tested, untouched by this revision

| Check | Result |
|---|---|
| `POST /projects/{id}/status` (submit, own project, project-scoped token) | `201 Created` |
| `GET /projects/{id}/status` (latest) | `200`, returns the submitted report |
| `GET /projects/{id}/status/history` | `200`, returns full history |
| `POST /projects/{other id}/status` (cross-project, project-scoped token) | `403 FORBIDDEN` |
| `GET /projects/{other id}/status` (cross-project) | `403 FORBIDDEN` |

`ProjectStatusUpdateController` and its repository were not modified in any round of this work —
this table exists purely to confirm no incidental breakage from the Projects UI changes, and finds
none. (Observation, not a defect: submitting twice for the same `report_date` currently creates two
rows rather than being deduplicated — this is pre-existing behaviour of the frozen v1.0 endpoint,
outside this revision's scope, and is not something this session introduced or was asked to change.)

### MJU Asset / existing real project compatibility

The Projects UI and API were verified against records using the **exact same project code
(`MJU-ASSET`) and a `PMOIS`-named project** that mirror what already exists in Production, to
confirm the fixed code displays and operates on pre-existing real-shaped records correctly, not
just newly-created synthetic ones:
- Both appear in `GET /api/v1/projects` with correct workspace/status/progress/health.
- `projects.code` has a DB-level `UNIQUE(workspace_id, code)` constraint (confirmed via
  `SHOW CREATE TABLE projects`) — attempting to create a second `MJU-ASSET` in the same workspace
  is rejected (currently as a generic 500 rather than a clean `VALIDATION_ERROR` — see "Observation"
  below; the rejection itself works, so no duplicate can silently be created).
- This session did **not** create any project against the real `pmo.jaideedigital.com` database and
  has no way to; all `MJU-ASSET`/`PMOIS` records used here were synthetic rows in this sandbox's
  local MariaDB, created solely to mirror the real records' shape for this test. CEO's Production
  check remains the only way to confirm the *actual* `MJU-ASSET` (Production project id `4`) and
  PMOIS records render correctly.

**Observation (not fixed, recorded for backlog):** a duplicate project-code creation attempt
currently surfaces as a generic `{"message":"Slim Application Error"}` (HTTP 500) instead of a
clean `VALIDATION_ERROR`, because of the `AppErrorMiddleware` ordering issue described below — the
uniqueness constraint itself works (no duplicate is created), only the error message shown to the
user is generic rather than specific.

### Future Project onboarding flow — confirmed, no manual SQL required

`Workspace → Create Project → Persist → appears in Project List` was walked end-to-end via the
running API and requires no manual SQL/migration under normal operation — `POST /api/v1/projects`
alone is sufficient. Token issuance for a newly-created project (via the existing, already-approved
`ApiTokenController` flow, unchanged by this revision) was exercised at the data level in this
session's seed script (inserting an `api_tokens` row with `project_id` set) to regression-test
enforcement, not as a new UI feature — per CTO's instruction, no token-secret UI was added to
Projects in this revision.

**Note (carried over, still relevant):** `POST /api/v1/projects` requires either
`cto_user_id`/`dev_user_id` in the body or a `workspace_default_settings` row for the workspace
(pre-existing `ProjectCreationPipeline` behavior, unrelated to this fix). A future real project's
workspace should have its `workspace_default_settings` configured (via the existing, unmodified
`WorkspaceDefaultSettingsController`) for the "CEO กรอกน้อยที่สุด" flow to work without extra
fields — this is existing, approved behaviour, not something introduced or changed here.

### Immutable Project fields — kept immutable, documented rather than newly editable

Per CTO's instruction not to invent new repository methods just to make every field editable: Name,
Code, and Development Mode remain **not editable** via the Project Edit modal (inputs are rendered
`disabled` in edit mode, and the backend `PUT /api/v1/projects/{id}` silently ignores those fields
even if a client sent them anyway — verified by reading `ProjectController::update()`, which only
ever reads `progress`, `health`, and `workspaceId` from the request body). This is an intentional,
documented limitation of the current approved architecture, not an oversight: there is no
`ProjectRepositoryInterface` method to rename/re-code a project, and adding one was judged to be
new architecture, which this revision was told to avoid.

### UI runtime quality fixes (this round)

- **Double-submission prevention:** both the Workspace and Project modal's submit button now
  disables itself for the duration of the request (re-enabled only if the request fails, since a
  successful save closes the modal). Previously, rapid double-click could fire two create/update
  requests.
- **Stale-modal race fix:** `openAddProjectModal()` fetches the workspace list (for the dropdown)
  before rendering — if a user opened Edit on two different project rows in quick succession, the
  slower of the two async fetches could finish last and overwrite the newer modal with stale data.
  A request-sequence guard now discards any modal render whose fetch resolves after a newer
  `openAddProjectModal()` call has already started.
- Confirmed no `integrity`/SRI attribute remains on the SweetAlert2 `<script>` tag (the Round 1 fix
  already removed the incorrect hash that was silently blocking the CDN script).

### Error handling standard — re-verified with `APP_DEBUG=false` (matching Production's expected setting)

- 404 (bad route), 401 (no/invalid session), 404 (nonexistent resource id), and 422 (validation,
  including malformed JSON body) all return clean, structured responses with **no stack trace, no
  file path, no SQL text, and no secrets** — verified directly against the running app.
- **Observation, not fixed (pre-existing, app-wide, not Projects-specific):** `AppErrorMiddleware`
  — whose entire purpose is to convert any uncaught exception into the standard
  `{success:false,error:{code,message}}` envelope — never actually runs for exceptions thrown
  during normal route handling. Slim's own `ErrorMiddleware` (registered one line earlier in
  `public/index.php`, making it the *inner* layer relative to `AppErrorMiddleware`) catches the
  exception first and converts it into Slim's own generic response
  (`{"message":"Slim Application Error"}` for JSON, or its own generic HTML page) before it can ever
  reach `AppErrorMiddleware`'s `catch` block. **This is not a security leak** — Slim's own default
  response is equally generic and contains no stack trace, path, or secret, confirmed above — but
  it means unhandled exceptions (e.g. a DB constraint violation on duplicate project code) show a
  slightly less specific message than the codebase's own envelope design intends. This predates
  every round of this work (the middleware was added in the earlier "M9 UAT Runtime Fix Revision 1"
  commit) and affects the entire API, not just Projects/Workspaces. Per CTO's instruction to record
  unrelated findings rather than fix them in this revision, this is **not fixed here** — it does
  not block the Projects module (every error path tested still returns a safe, if generic, response)
  and is not a new security regression. Recommended as a follow-up revision: reorder
  `public/index.php` so `AppErrorMiddleware` is registered *before* `addErrorMiddleware()` (making
  it the effective inner boundary Slim's middleware needs), or fold its generic-response logic
  directly into a custom Slim error handler.
- `app.js`'s `api()` helper (fixed in Round 2) already degrades gracefully against this — it now
  shows `HTTP_500 — Slim Application Error` instead of a bare `UNKNOWN` when this generic path is
  hit, which is informative enough to act on without leaking anything.

### Automated test result (exact counts, per CTO's requirement not to summarize as bare "PASS")

```
PHPUnit 10.5.63
Tests: 200, Assertions: 523, Failures: 0, Errors: 0, Skipped: 0, Incomplete: 0
```
Run against a freshly-installed database (`deploy/PMOIS_v2_Database_Install.sql`, no manual seed
data — the manual seed data described above was added separately, after this run, purely for the
manual `curl` contract walk) in this session's own sandbox MariaDB (not Production). This is the
full existing `tests/Integration` suite, unmodified — it covers M1 through M9 (Governance,
Knowledge, RFC, Decision Register, Automation, Analytics, Auth, Workspace Scoping, Permission
Resolver, Revision Workflow, and more), not only Projects/Workspaces, confirming no regression
elsewhere in the frozen v1.0 surface.

### Observation / OFI list (recorded, not modified — per "do not touch unrelated modules")

1. `AppErrorMiddleware` never actually runs for in-request exceptions due to middleware ordering
   (see above) — app-wide, pre-existing, not a security leak, not Projects-specific.
2. `src/Config/dependencies.php` has the same "bare class name in a namespace-less file" mistake
   (that broke `ProjectReleaseRepositoryInterface` in Round 2) for `AnalyticsController`,
   `AutomationController`, `KnowledgeController`, `AuditController`, and `PlatformController` — not
   currently broken in practice (PHP-DI's autowiring silently resolves the real class instead,
   confirmed by all 200 tests passing including those modules' own suites), but fragile. Left alone
   per scope discipline.
3. Duplicate project-code creation surfaces as a generic 500 rather than a specific
   `VALIDATION_ERROR` (a symptom of Observation #1) — the uniqueness constraint itself correctly
   prevents the duplicate; only the error message is generic.
4. Inbound Status API allows multiple `report_date`-duplicate submissions per project rather than
   deduplicating — pre-existing, frozen v1.0 behaviour, unmodified and unaffected by this revision.

None of these four block the Projects module or constitute a new security regression; all are
suggested as separate future backlog items, not part of this revision's deliverable.

---

## Round 4 — Projects Management UI Finalization (Workspace Tabs redesign)

CTO's Round 4 request had two parts: (1) a UI concept change — replace the separate Workspace
table + Project table with **Workspace Tabs** (navigation context) + a full-width **Project
working area** (search/filter/pagination), with human-readable Thai copy throughout and a
self-hosted K2D font — and (2) fix the specific defect CEO hit after the Round 3 package:
Workspace Activate/Deactivate failing with `NOT_FOUND: ไม่พบ workspace`.

### Root cause of the Production `NOT_FOUND` on Activate/Deactivate

Not an identifier mismatch (numeric id vs code) — traced end-to-end and confirmed the contract was
consistent. The actual cause: **`POST /api/v1/workspaces` (Add Workspace) never added the creator
to `workspace_members`.** A platform admin who creates a workspace becomes its `created_by` but not
a *member* of it. The Round 3 security fix (workspace-update permission checked against the
*target* workspace via `PermissionResolver::can()`) is what first made this visible: `can()`
requires an actual `workspace_members` row to grant any permission, so the workspace's own creator
— who has no such row — is correctly-but-unhelpfully treated as a non-member and gets the same
404 `show()`/`update()` already use for "not a member" (to avoid leaking workspace existence). This
explains exactly what CEO saw: every workspace they created via "Add Workspace" (the `VERIFY-FIX`,
`TEST-AFTER-RESTART`, `SHOULD-FAIL`, `BOOTSTRAP` test workspaces mentioned in the CTO's brief)
was one they had zero membership in, so Activate/Deactivate (and Edit) on any of them failed.

**Fix:** `WorkspaceController::create()` now adds the creator as an `ADMIN`-role member of the
workspace immediately after creating it, via the **already-existing**
`WorkspaceMemberRepositoryInterface::addMember()` (same method `WorkspaceMemberController::invite()`
already uses) — no new repository method, no schema change. Confirmed with a real request: create
workspace → `workspace_members` row appears immediately → Activate/Deactivate on that same
workspace succeeds without any NOT_FOUND.

**Workspaces created before this fix still lack that membership row** — `VERIFY-FIX`,
`TEST-AFTER-RESTART`, `SHOULD-FAIL`, `BOOTSTRAP`, and possibly the original `JAIDEE` workspace if it
too was created via this path. A one-time, idempotent, **data-only** backfill statement (no schema
change) is provided in the final deployment package's README so these can be fixed retroactively
without needing to delete and recreate them. Per CTO's instruction, these test workspaces are left
alone otherwise — cleanup is a separate, later exercise, not bundled into this defect fix.

### Multi-workspace access — a deeper contract gap found while building Workspace Tabs

Building the tabs UI required calling `GET /api/v1/projects` for a workspace other than the one
tied to the user's login session, and this exposed that **the whole backend session model binds
one fixed workspace per session** (`AuthTokenMiddleware::withSession()` picks the user's
lowest-id active membership once, at login, with no per-request override). Every workspace-scoped
repository is constructed once per request from that same fixed `current_workspace_id`. A user who
is a member of two workspaces could log in and see workspace A's data, but `GET /api/v1/projects`
could never return workspace B's projects — full stop — no matter what the UI asked for. Verified
directly: a tab for a second workspace always showed 0 projects, even when the DB had project rows
in it.

**Fix, additive and backward-compatible (existing consumers unaffected):**
- `GET /api/v1/projects` now accepts an optional `?workspace_id=` query parameter. When given, the
  Controller explicitly re-checks `PermissionResolver::can($userId, $thatWorkspaceId, null,
  'project.view')` **before** querying — the permission check is against the workspace actually
  being requested, not the session's default, closing the same class of gap Round 3's workspace-
  update fix addressed. Omitting the parameter is 100% identical to previous behaviour.
- `ProjectRepositoryInterface::listByWorkspace()` and `::findById()` both gained an optional
  `?int $workspaceIdOverride` parameter (default `null` = old behaviour unchanged) so the
  Controller can ask for a specific, permission-checked workspace's data instead of only ever the
  session's own.
- The Projects page frontend now fetches each visible workspace tab's projects **separately**
  (`?workspace_id=<tab id>`, one request per tab, run in parallel) instead of fetching one dataset
  and filtering it client-side — so the server enforces access per workspace on every load, not
  just once. A tab the user isn't permitted to see shows a clear "ไม่มีสิทธิ์เข้าถึงพื้นที่ทำงานนี้"
  state instead of a silently-empty or wrong list.
- The same override was needed for `PUT /api/v1/projects/{id}` (Edit/Move) and
  `MySqlProjectRepository::create()`'s own internal "read back what I just inserted" step —  both
  originally called the *unscoped* `findById()` after writing into a workspace that could differ
  from the session's, which silently returned `null` and produced either a `RuntimeException`
  (`create()`) or a response full of `null` fields despite the write having actually succeeded
  (`update()`'s move-workspace path). Both reproduced and fixed; `update()` no longer re-fetches at
  all (it builds the response from values it already knows), and `create()`'s internal re-fetch now
  passes the actual target workspace id explicitly.
- **New security check added while wiring this up, not merely a UI nicety**: moving a project to a
  different workspace (`PUT /api/v1/projects/{id}` with `workspaceId`) previously never checked
  whether the caller had any right to place a project into the *destination* workspace — only that
  they could edit the project in its *current* one. A user with `project.update` on their own
  project could have moved it into **any** workspace_id in the system, valid or not, without being
  a member of it. Fixed with the same `PermissionResolver::can(..., 'project.create')` pattern used
  elsewhere in this document. Verified: an admin of workspace B cannot move workspace A's project
  into B without being a member of B; a member of both can.

### Deliberately NOT extended: cross-workspace project *creation*

While building this, `POST /api/v1/projects` was briefly changed to also accept a `workspaceId` in
the body to create directly into a non-default workspace (matching the Workspace Tabs UI's "create
into whichever tab is open" expectation). This broke immediately with a *different* error
(`Project ... ไม่อยู่ใน workspace context ปัจจุบัน`, thrown from
`MySqlProjectMemberRepository`), because `ProjectCreationPipeline::create()` doesn't only insert
into `projects` — it also calls `ProjectMemberRepository`, `MilestoneRepository`,
`ProjectTechStackRepository`, `WorkspaceModuleSettingRepository`, `ApiTokenRepository`, governance
auto-bind, and AI-assignment services, **every one of which is separately bound to the session's
fixed workspace via the same DI container mechanism**. Making cross-workspace creation actually
work would mean threading a workspace override through all of those — a real architecture change,
not a stabilization fix, and one that touches the exact Workspace Isolation guarantees this
revision was told to preserve.

**Decision: reverted.** `POST /api/v1/projects` always creates into the session's own workspace,
exactly as before this round — any `workspaceId` in the request body is now ignored for creation
(it still works for the separate move/edit `PUT` endpoint, which only touches the `projects` table
and was verified safe). The response now includes `workspaceId` so the frontend can tell when a
project landed somewhere other than the tab the user was viewing (only possible for an admin
viewing a non-default workspace tab) and say so plainly — "ระบบสร้างโครงการเข้าพื้นที่ทำงานหลักของ
บัญชีคุณแทน (ข้อจำกัดปัจจุบัน...)" — rather than silently placing it in the wrong tab or claiming an
unqualified success. This is reported here as a **known limitation**, per CTO's own instruction
("หาก architecture ปัจจุบันไม่อนุญาตการย้าย ให้ไม่สร้าง behavior ใหม่ และรายงาน CTO") applied to the
create case as well as the move case it was originally written for.

### UI concept delivered

- **Workspace Tabs** replace the Workspace table: `ชื่อ Workspace (จำนวน Project)`, active tab
  visually distinct, horizontally scrollable if they overflow (no layout break), `+ เพิ่มพื้นที่
  ทำงาน` button beside them. Selecting a tab persists across a refresh via a `?ws=` URL parameter
  (not `localStorage`, so it's shareable/bookmarkable and needs no client storage capability).
- **Workspace header** (name + `แก้ไข` / `เปิดใช้งาน`↔`ปิดใช้งาน`, the label always matching current
  status) replaces the separate Actions column.
- **Project working area**: full-width layout (`max-width: 1600px` on this page only, via a
  `body.projects-page` class — every other page's layout is untouched), toolbar with search (code
  + name, client-side, instant), status filter, health filter, all composable with the active tab
  and with pagination (default 10/page, selectable 10/20/50, page reset on any filter change).
  Workspace column dropped from the table (redundant with the active tab); "Dev Mode"/"Current
  Milestone" relabelled to Thai (`รูปแบบการพัฒนา`/`Milestone ปัจจุบัน`); status/health values shown
  as Thai badges via new Projects-page-only `projectStatusBadge()`/`projectHealthBadge()` helpers
  (the shared `statusBadge()`/`healthBadge()` used by six other pages for unrelated status
  vocabularies were **not** touched).
- **Actions renamed**: `Activate/Deactivate` → `เปิดใช้งาน`/`ปิดใช้งาน` (whichever applies);
  `Change Parent` → `ย้ายพื้นที่ทำงาน`, and — since the underlying `updateWorkspace()` capability
  already existed and is now correctly permission-checked — actually implemented (a SweetAlert2
  dialog with a workspace picker) instead of the previous "not implemented yet" placeholder.
- **K2D self-hosted**: 8 `.woff2` files (Thai + Latin subsets × weights 400/500/600/700, ~124KB
  total) fetched once from Google's own font-serving infrastructure and committed under
  `public/app/fonts/k2d/`, with `public/app/fonts/k2d/OFL.txt` (the real SIL Open Font License 1.1
  text for K2D) alongside them for attribution. Loaded via `@font-face` in `app.css`, scoped to
  `body.projects-page` only (SweetAlert2 popups, inputs, selects, buttons on this page included) —
  no other page's typography changed, and nothing is fetched from Google Fonts or any CDN at
  runtime. Verified in a real headless-Chromium session: `document.fonts.check('16px K2D')` returns
  `true` and the page's computed `font-family` is `K2D, "Segoe UI", ...`.
- Double-submission guard and a stale-async-response fix (both from Round 3) remain in place and
  were re-verified working in this round's browser test.

### Verification this round

All of the above was exercised with a real headless Chromium browser (Playwright, already
preinstalled in this sandbox) driving the actual rendered page against the real PHP app and a real
MariaDB instance — not just `curl`:
- Tabs render with correct per-workspace counts; switching tabs shows that workspace's real
  projects (previously showed 0 for any non-session-default workspace — this is the bug this round
  fixed).
- Search ("ALPHA" → exactly the 2 matching rows), status filter ("closed" → exactly 1 matching
  row), health filter ("red" → exactly 2 matching rows), pagination (12 seeded projects → page 1
  shows 10, page 2 shows 2, correct `‹ 1 2 ›` controls) — all confirmed against a 12-project seed
  set with known expected results per filter.
- Add Workspace → SweetAlert2 "สำเร็จ" → new tab appears and is auto-selected.
- Activate/Deactivate → confirmation dialog → success dialog → button label flips correctly.
- Add Project → workspace field pre-selected to the active tab → appears in that tab's list → tab
  count increments.
- Edit Project → immutable fields (name/code/dev mode) confirmed disabled in the form → progress/
  health changes persist and render correctly.
- Move Project → picker shows only *other* workspaces as targets → source tab count decrements,
  destination tab count increments.
- Double-submit guard → submit button reports `disabled` immediately on click, before the request
  resolves.
- Zero browser console errors across the entire interactive sequence above.
- (SweetAlert2's own CDN load could not be exercised through this sandbox's network policy, which
  rejects `cdn.jsdelivr.net`; the interactive tests above ran against a local, same-DOM-API mock of
  `window.Swal.fire` — same app.js code path, same class names — the CDN's own reachability from a
  real browser with normal internet access is outside what this sandbox can determine.)
- Full `tests/Integration` suite re-run after every code change this round: **200 tests, 523
  assertions, 0 failures, 0 errors, 0 skipped** (final state, fresh installer-built DB).
- Security regression re-confirmed after every fix: unauthenticated → 401; cross-workspace GET
  correctly denied/allowed per membership; cross-workspace workspace-update correctly denied/
  allowed per membership; cross-workspace project-move correctly denied/allowed per membership;
  Project-Scoped Token cross-project and workspace-level denials unchanged; Inbound Status API
  submit/latest/history/cross-project-denial unchanged; `audit_trails` rows present for every write
  exercised.

---

## Round 1 defect table (kept for reference)

| # | Defect (in `public/app/app.js` as of `d896372`) | Impact |
|---|---|---|
| 1 | `openAddProjectModal` used `await` without being declared `async` | Fatal `SyntaxError` — the entire `app.js` fails to parse in the browser |
| 2 | `topbar()`/`logout()` deleted from `app.js` while adding Revision X code, but every page still calls `topbar('...')` | `ReferenceError` on every page |
| 3 | `showSwal()` called throughout but never defined | `ReferenceError` on first SweetAlert2 interaction |
| 4 | SweetAlert2 `<script>` carried a fabricated/incorrect `integrity` hash | Browser silently blocks the CDN script |
| 5 | Edit-Workspace `onclick` had a missing `)` and embedded `JSON.stringify()` inside a double-quoted attribute | Malformed HTML / `SyntaxError` on click |
| 6 | `openAddWorkspaceModal` never pre-filled fields and always `POST`ed | "Edit" created a duplicate instead of updating |
| 7 | Modal open/close used `showSwal(...)` (a real, unrelated SweetAlert2 dialog) and `.close()` on a `Promise` | Broken modal show/close |
| 8 | No `PUT /api/v1/workspaces/{id}` or `PUT /api/v1/projects/{id}` existed | Edit/Activate-Deactivate would 404/405 even with correct frontend code |
| 9 | `GET /api/v1/projects` only returned `id/code/name/status` | Workspace/Dev Mode/Progress/Health/Milestone columns could never show data |

Fixed in commit `bcbc3f3` (all reused existing repository methods / permission codes / Audit
mechanism — no new architecture).

## Changed files (cumulative, all four rounds)

| File | Round | Change |
|---|---|---|
| `public/app/app.js` | 1–4 | Round 1: restored `topbar()`/`logout()`, added `showSwal()`, fixed modal/Edit/syntax. Round 2: fixed workspace status values, fixed `api()` error surfacing. Round 3: double-submission + stale-async-response guards. Round 4: full Workspace Tabs + Project working area rewrite (tabs, per-workspace data fetch via `?workspace_id=`, search/filter/pagination, Thai labels, `moveProject()` implemented for real, create-landed-elsewhere transparency message). |
| `public/app/app.css` | 1, 4 | Round 1: `.modal-overlay`/`.modal-box`. Round 4: K2D `@font-face` (8 files) scoped to `body.projects-page`, tabs/toolbar/pagination/full-width layout styles (this page only). |
| `public/app/projects.html` | 1, 4 | Round 1: removed duplicate helper/CDN definitions. Round 4: full markup rewrite for tabs/header/toolbar/pagination skeleton; `<body class="projects-page">`. |
| `public/app/fonts/k2d/*.woff2` (8 files) + `OFL.txt` | 4 | New — self-hosted K2D font + its SIL OFL 1.1 license text. |
| `src/Application/Http/Controllers/WorkspaceController.php` | 1–4 | Round 1: added `update()`. Round 2: fixed status validation list. Round 3: security fix (target-workspace permission check). Round 4: `create()` now adds the creator as an `ADMIN` member via existing `addMember()` — this is the actual fix for the `NOT_FOUND` Activate/Deactivate defect. |
| `src/Application/Http/Controllers/ProjectController.php` | 1, 4 | Round 1: added fields to `index()`; added `update()`. Round 4: `index()` accepts permission-checked `?workspace_id=` override; `update()` adds destination-workspace permission check for project moves and no longer re-fetches after a move (builds response from known values instead); `create()`'s cross-workspace attempt was tried, found to require unwinding several other session-bound repositories, and deliberately reverted — response now includes `workspaceId` so the frontend can be transparent about this known limitation. |
| `src/Domain/Project/ProjectRepositoryInterface.php` | 4 | `listByWorkspace()` and `findById()` both gained an optional `?int $workspaceIdOverride` parameter (default `null` = unchanged behaviour). |
| `src/Infrastructure/Persistence/MySQL/MySqlProjectRepository.php` | 4 | Implements the above; `create()`'s internal post-insert re-fetch now passes the actual target workspace explicitly (was fatally failing for any workspace other than the session's own). |
| `src/Config/routes.php` | 1 | Added `PUT /workspaces/{id}` and `PUT /projects/{id}`. |
| `src/Config/dependencies.php` | 1, 2, 4 | Round 1: injected `WorkspaceRepositoryInterface` into `ProjectController`. Round 2: added the missing `MySqlProjectReleaseRepository` import. Round 4: injected `PermissionResolver` into `ProjectController` (for the new per-request workspace permission checks) and `RoleRepositoryInterface` into `WorkspaceController` (for the ADMIN-role membership grant on create). |
| `TEST-RESULTS-Projects-RevisionX.txt` | 3 | Correction notice at the top. |
| `CHANGELOG-Projects-RevisionX.md` | 3 | Correction notice above §6. |

No database migration in any round. `workspace.update`/`project.update` permission codes were
already seeded in migration `0012`. Round 4 introduces exactly one **optional, one-time data
backfill** (not a migration) for workspaces created before the Round 4 membership fix — see the
deployment package README.

### Backfill SQL (optional, one-time, data-only — not a schema migration)

Grants each existing workspace's own creator `ADMIN` membership, but only where that row doesn't
already exist (safe to run more than once; changes nothing for workspaces already correct):

```sql
INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT w.id, w.created_by, (SELECT id FROM roles WHERE code = 'ADMIN'), 'active'
FROM workspaces w
WHERE NOT EXISTS (
  SELECT 1 FROM workspace_members wm
  WHERE wm.workspace_id = w.id AND wm.user_id = w.created_by
);
```

Without this, `VERIFY-FIX`, `TEST-AFTER-RESTART`, `SHOULD-FAIL`, `BOOTSTRAP`, and any other
workspace created before this fix will keep returning `NOT_FOUND` on Activate/Deactivate/Edit for
their own creator — the Round 4 code fix only prevents the problem for *newly*-created workspaces
going forward.

## Round 5 — M3 Project Management Completion Gate (`79faac9`)

The Completion Gate directive required building a Feature Inventory from the repository's own
Source-of-Truth design docs (not memory/assumption) before touching anything further. Reading
`M0-Design/Revision6/R6-05-Project-Creation-Flow-Revision6.md` in full as part of that inventory
surfaced that Round 4's ad-hoc "ย้ายพื้นที่ทำงาน" (move workspace) handling — added directly to
`PUT /api/v1/projects/{id}` — **duplicated a separate, pre-existing, already-approved mechanism**
that the ad-hoc version never used: `PATCH /api/v1/projects/{id}/structure` →
`ProjectStructureController` → `ProjectStructureService`, which has its own dedicated audit table
(`project_structure_history`, distinct from the generic `audit_trails` table) and its own business
rules (circular-hierarchy checks, project-code-conflict checks). R6-05 states explicitly: *"Move /
Change Parent / Promote flows: unchanged from R5 §2–4 (permission `project.structure.update`,
history via `project_structure_history`, `CIRCULAR_HIERARCHY` / `PROJECT_CODE_CONFLICT` rules, ID
immutability verified)"* — confirming this canonical flow, not the Round 4 ad-hoc one, is the
approved design, and confirming "Change Parent/Move Project" is itself approved M3 scope.

### Two real, pre-existing defects found in the canonical (but previously unused-by-UI) flow

Neither of these was introduced by this session — both existed in `ProjectStructureService.php` /
`ProjectStructureController.php` before this engagement began, invisible until the UI actually
started exercising that code path this round.

| # | Defect | Effect |
|---|---|---|
| 1 | `ProjectStructureService`'s `moveWorkspace()`, `changeParent()`, and `promoteToRoot()` all hardcoded `changedBy: 0` when writing to `project_structure_history` | Every structural change ever recorded by this table showed `changed_by = 0` — no accountability, for any project, at any time |
| 2 | `ProjectStructureController`'s `move_workspace` action checked the caller's `project.structure.update` permission against the *source* project/workspace only (via the existing route middleware) — never against the *destination* workspace | A user with structure-update rights on their own project could move it into a workspace they have no membership or role in at all |

**Fixes**: (1) both methods now use the real `$actorId` parameter passed in — verified directly
against the DB after the fix: `changed_by` now correctly shows the real acting user's id (e.g. `2`
or `297`), never `0`. (2) added an explicit `PermissionResolver::can($actorId, $newWorkspaceId, null,
'project.create')` check against the destination workspace before allowing the move — same pattern
used to close the equivalent gaps in `WorkspaceController::update()` (Round 3) and
`ProjectController`'s project-move path (Round 4).

### Frontend rewire

`app.js`'s `moveProject()` now calls `PATCH /api/v1/projects/{id}/structure` with
`{ action: 'move_workspace', new_workspace_id }` instead of the Round 4 ad-hoc `PUT /projects/{id}`
call. `ProjectController::update()` no longer accepts or handles a `workspaceId` field at all — its
scope is now exactly what its own docblock always said (progress/health only). The Edit Project
modal's Workspace `<select>` is now `disabled` when editing, since workspace changes go exclusively
through "ย้ายพื้นที่ทำงาน" now — having two UI paths to the same change was exactly the kind of split
that caused this duplication in the first place.

### Item explicitly reported to CTO, not decided by Dev

Cross-workspace project **creation** (as distinct from *moving* an existing project, which is now
fully implemented above) — allowing a user to create a brand-new project directly into a workspace
tab other than their session's own — was investigated per the Completion Gate's requirement to
resolve this via existing architecture wherever possible. `ProjectCreationPipeline::create()` calls
`ProjectMemberRepository`, `MilestoneRepository`, `ProjectTechStackRepository`,
`WorkspaceModuleSettingRepository`, `ApiTokenRepository`, governance auto-bind, and AI-assignment
services — all separately bound to the session's fixed `current_workspace_id` via the DI container.
Making this genuinely work means threading a workspace override through roughly 8 repositories, which
is a real architecture change (not a stabilization fix) and risks the frozen Workspace Isolation
guarantee. Per the Completion Gate's explicit instruction that Dev must not decide this unilaterally,
this was reported to CTO with this evidence rather than implemented — see the structured CTO report
for this round. The previously-considered workaround ("create into workspace A then auto-move to
workspace B") was explicitly avoided since the Completion Gate directive forbids it.

### Out-of-scope observation (not a defect — recorded, not fixed)

An interactive test of the rewired Move Project flow initially showed a 403
`Missing permission: project.structure.update` when moving a freshly-created test project. Root-cause
investigation found this was **not a code defect**: the test's own seed data made the same user both
the platform admin and the project's default `cto_user_id`/`dev_user_id`, which caused
`ProjectCreationPipeline` to add that user to `project_members` with role `MEMBER` (lacking
`project.structure.update`). Per the frozen `PermissionResolver` precedence algorithm (Phase 0 Spec
§5.4 — project-level role overrides workspace-level role by design, not fixable within this
Completion Gate's scope), that `MEMBER` row took precedence over the user's higher workspace-level
role. Re-tested with realistic, distinct CTO/Dev test users — the move then succeeded cleanly with
`changed_by` correctly recorded. Recorded here transparently since it surfaced during this round's
testing, but it is a pre-existing interaction between two frozen, unrelated mechanisms
(`PermissionResolver` precedence + `ProjectCreationPipeline`'s default role assignment), neither of
which this session is permitted to change.

### Consolidated deployment SQL

`deploy/M3_ProjectManagement_CompletionGate_Deploy.sql` — one file, idempotent, rerunnable,
supersedes the inline snippet in the Round 4 section above. Same backfill logic (grant each
workspace's own creator `ADMIN` membership where missing), now with explicit BEFORE/AFTER
verification `SELECT`s built into the file itself rather than left for the reader to construct.
Verified in this session's own test environment: first run against seeded data matching the real
scenario → BEFORE=4, AFTER=0; second run immediately after → BEFORE=0, AFTER=0 (idempotency
confirmed — no duplicate rows via `GROUP BY workspace_id, user_id HAVING cnt > 1`, `workspaces`/
`projects` row counts completely unchanged in both runs).

### Regression

Full PHPUnit suite re-run after every change this round: **200 tests / 523 assertions / 0 failures /
0 errors / 0 skipped** — same stable baseline maintained since Round 2.

### Files changed this round (in addition to the cumulative table above)

| File | Change |
|---|---|
| `public/app/app.js` | `moveProject()` rewritten to call canonical `PATCH .../structure`; Edit Project modal disables the Workspace field. |
| `src/Application/Http/Controllers/ProjectController.php` | Removed ad-hoc `workspaceId`/move handling from `update()`; scope now matches its own docblock exactly. |
| `src/Application/Http/Controllers/ProjectStructureController.php` | Added destination-workspace permission check for `move_workspace`. |
| `src/Domain/Project/ProjectStructureService.php` | Fixed `changedBy: 0` bug in all three structure-change methods. |
| `src/Config/dependencies.php` | Injected `PermissionResolver` into `ProjectStructureController`'s factory. |
| `deploy/M3_ProjectManagement_CompletionGate_Deploy.sql` | New — one consolidated, idempotent, rerunnable deployment SQL with built-in before/after verification. |

## Scope discipline (unchanged from Round 1)

No redesign, no new architecture, no unrelated module changes. `PermissionResolver` / Workspace
Scope / Audit mechanism / Response Envelope / Project-Scoped Token Enforcement are all unchanged —
verified this round via the `MEMBER`-role negative-permission test and the audit-trail query above,
not just left alone in theory. The one item requiring an actual architecture decision (cross-workspace
project creation) was reported to CTO rather than decided by Dev, per the Completion Gate's explicit
instruction.
