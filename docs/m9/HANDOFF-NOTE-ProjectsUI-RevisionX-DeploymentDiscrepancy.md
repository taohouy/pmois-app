# PMOIS v2 — Projects UI Revision X — Production Runtime Discrepancy — Root Cause & Fix

**Date:** 2026-09-26 (updated after CTO's second UAT round — Project List `Error: UNKNOWN` + Workspace Activate/Deactivate failure)
**Trigger:** CTO/CEO UAT finding — Production `https://pmo.jaideedigital.com/app/projects.html`.

This note has two parts: **Round 1** (why Production showed the old form at all) and **Round 2**
(why, after deploying the Round 1 fix, Project List still failed with `Error: UNKNOWN` and
Activate/Deactivate still failed). Round 2's defects were found and confirmed by actually running
the application end-to-end against a real MySQL/MariaDB instance in this session's sandbox — not
by static reading — so the fixes below are verified, not theoretical.

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

## Changed files (cumulative, both rounds)

| File | Round | Change |
|---|---|---|
| `public/app/app.js` | 1 & 2 | Round 1: restored `topbar()`/`logout()`, added `showSwal()`, fixed modal/Edit/syntax. Round 2: fixed workspace status values (`active`/`inactive`), fixed `api()` error surfacing to never show a bare "UNKNOWN". |
| `public/app/app.css` | 1 | Added `.modal-overlay`/`.modal-box`. |
| `public/app/projects.html` | 1 | Removed duplicate helper/CDN definitions. |
| `src/Application/Http/Controllers/WorkspaceController.php` | 1 & 2 | Round 1: added `update()`. Round 2: fixed status validation list to `active`/`inactive`. |
| `src/Application/Http/Controllers/ProjectController.php` | 1 | Added `workspaceCode`/`development_mode`/`progress`/`health`/`current_milestone` to `index()`; added `update()`. |
| `src/Config/routes.php` | 1 | Added `PUT /workspaces/{id}` and `PUT /projects/{id}`. |
| `src/Config/dependencies.php` | 1 & 2 | Round 1: injected `WorkspaceRepositoryInterface` into `ProjectController`. Round 2: added the missing `use App\Infrastructure\Persistence\MySQL\MySqlProjectReleaseRepository;` import that was fatally breaking every `GET/POST/PUT /api/v1/projects*` request. |

No database migration in either round — `workspaces.status` already only accepted
`active`/`inactive` (nothing to migrate, the *code* was wrong, not the schema), and
`workspace.update`/`project.update` permission codes were already seeded in migration `0012`.

## Scope discipline (unchanged from Round 1)

No redesign, no new architecture, no unrelated module changes. `PermissionResolver` / Workspace
Scope / Audit mechanism / Response Envelope / Project-Scoped Token Enforcement are all unchanged —
verified this round via the `MEMBER`-role negative-permission test and the audit-trail query above,
not just left alone in theory.
