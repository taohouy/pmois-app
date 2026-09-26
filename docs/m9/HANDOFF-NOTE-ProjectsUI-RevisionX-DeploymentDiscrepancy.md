# PMOIS v2 — Projects UI Revision X — Production Runtime Discrepancy — Root Cause & Fix

**Date:** 2026-09-26
**Trigger:** CTO/CEO UAT finding — Production `https://pmo.jaideedigital.com/app/projects.html` shows the old single-form "Create Project" UI instead of the approved Revision X (Workspace List + Project List + Modals + SweetAlert2).

---

## 1. Root Cause

Two separate problems, both real:

### 1.1 Deployment gap (the finding as reported)
Commit `d896372` ("update", 2026-09-06) is the commit that implemented Projects UI Revision X
(`CHANGELOG-Projects-RevisionX.md` / `REVIEW-NOTE-Projects-RevisionX.md`). It is present on
`master` in this repository. Production is still serving the pre-Revision-X build (the
`public/app/projects.html` / `app.js` from commit `23256f3`, M9 Revision 1) — i.e. **Revision X
was committed to the repo but never actually deployed to Production.** This matches exactly what
the CTO observed: the old "Create Project (CEO — กรอกน้อยที่สุด)" form with Name/Code/CTO User
ID/Dev User ID/Mode.

### 1.2 Revision X itself was not actually functional (newly discovered — more serious)
While investigating, the Revision X source in `d896372` was found to be broken badly enough that,
**even if it had been deployed as-is, it would have broken every page in the application**, not
just Projects. This means the "PASS" runtime results recorded in
`TEST-RESULTS-Projects-RevisionX.txt` / `REVIEW-NOTE-Projects-RevisionX.md` could not have been
produced by an actual browser session against that exact commit:

| # | Defect (in `public/app/app.js` as of `d896372`) | Impact |
|---|---|---|
| 1 | `openAddProjectModal` used `await` without being declared `async` | **Fatal `SyntaxError`** — the entire `app.js` file fails to parse in the browser, so `api()`, `esc()`, `badge()`, etc. are undefined on **every page**, not only Projects |
| 2 | `topbar()` and `logout()` were deleted from `app.js` while adding the Revision X code, but every page (`dashboard.html`, `projects.html`, `governance.html`, etc.) still calls `topbar('...')` on load | `ReferenceError` on every page — no navigation bar, no logout |
| 3 | `showSwal()` was called throughout (workspace/project create, activate/deactivate, confirmations) but never defined | `ReferenceError` the first time any SweetAlert2 interaction was needed |
| 4 | SweetAlert2 `<script>` tag carried a fabricated/incorrect `integrity` (SRI) hash | Browser silently blocks the SweetAlert2 CDN script from executing → `window.Swal` never exists |
| 5 | Edit-Workspace button rendered `onclick="openAddWorkspaceModal(${JSON.stringify(...)}"` — missing closing `)`, and both Edit buttons embedded `JSON.stringify(...)` (double quotes) inside a double-quoted HTML attribute | Malformed HTML / `SyntaxError` on click |
| 6 | `openAddWorkspaceModal` never pre-filled the form for an existing workspace and always `POST`ed | "Edit Workspace" silently created a duplicate workspace instead of updating |
| 7 | Modal open code called `showSwal(...)` (which opens a real, unrelated SweetAlert2 dialog) and later called `modalSwal.close()` on what is actually a `Promise` | Broken modal show/close entirely |
| 8 | No backend endpoint existed for `PUT /api/v1/workspaces/{id}` (used by both "Edit Workspace" and "Activate/Deactivate") or `PUT /api/v1/projects/{id}` | Both actions would fail with 404/405 even with correct frontend code |
| 9 | `GET /api/v1/projects` (`ProjectController::index`) only ever returned `id/code/name/status` | "Workspace / Dev Mode / Progress / Health / Current Milestone" columns required by the approved spec could never have displayed real data |

Root cause of (1.2): the `d896372` commit was written and self-reported as verified without an
actual browser runtime check — several of the above are the kind of error that is caught
instantly by opening dev tools, so genuine runtime verification did not happen despite what the
review documents say.

## 2. Fix Applied (this revision — no redesign, no new architecture)

All fixes reuse the existing structure, existing `PermissionResolver` / Workspace Scope /
`AuditContext`, and existing repository methods. Nothing in the page layout, navigation, or API
architecture was changed.

| File | Change |
|---|---|
| `public/app/app.js` | Restored `topbar()` / `logout()`; added `showSwal()` (thin SweetAlert2 wrapper, used only for Success/Error/Warning/Confirmation as originally specified); replaced the broken "open a real Swal to house a custom form" pattern with a plain modal overlay (`openModal`/`closeModal`) — SweetAlert2 stays reserved for alerts/confirmations per the changelog; fixed the `await`-without-`async` syntax error; fixed Edit buttons to pass just an id and look up the record from a small in-memory cache (`_workspacesCache`/`_projectsCache`) instead of embedding raw `JSON.stringify()` inside an HTML attribute; wired Edit-Workspace to `PUT` with pre-filled fields; wired Edit-Project to `PUT` for the fields that have backing repository support (progress/health/workspace) and disabled the fields that don't (name/code/development_mode — no update method exists for these, so the UI does not claim to support editing them) |
| `public/app/app.css` | Added `.modal-overlay` / `.modal-box` (a few lines) so the existing modal markup actually renders as a modal instead of an unstyled floating `<div>` |
| `public/app/projects.html` | Removed duplicate `api()/esc()/badge()/...` definitions and a second SweetAlert2 CDN `<script>` tag that duplicated what's already in `app.js` |
| `src/Application/Http/Controllers/WorkspaceController.php` | Added `update()` — wires the **already-existing** `WorkspaceRepositoryInterface::update()` to `PUT /api/v1/workspaces/{id}`; backs both Edit and Activate/Deactivate |
| `src/Application/Http/Controllers/ProjectController.php` | `index()` now includes `workspaceId`, `workspaceCode`, `development_mode`, `progress`, `health`, `current_milestone` (all already on the `Project` entity, just not mapped out before); added `update()` — wires the **already-existing** `ProjectRepositoryInterface::updateProgress()` / `updateWorkspace()` to `PUT /api/v1/projects/{id}` |
| `src/Config/routes.php` | Added `PUT /api/v1/workspaces/{id}` (permission `workspace.update`, already seeded) and `PUT /api/v1/projects/{id}` (permission `project.update`, already seeded, scoped by project like `/close`) |
| `src/Config/dependencies.php` | `ProjectController` factory now also injects `WorkspaceRepositoryInterface` (needed for the `workspaceCode` lookup) |

No database schema change, no new permission codes (both `workspace.update` and `project.update`
were already seeded in `database/migrations/0012_seed_role_permissions.sql` but never wired to a
route), no new repository methods.

## 3. Verification performed in this environment (limits — read before trusting any "PASS")

This session runs in an isolated container with **no access to the production server** (no
FTP/SSH credentials, no way to redeploy `pmo.jaideedigital.com`) and **no MySQL server available**
locally (only the PHP MySQL driver, no `mysqld` binary). Given that:

- ✅ `node --check public/app/app.js` — no syntax errors (previously failed with a `SyntaxError`)
- ✅ `php -l` on every changed PHP file — no syntax errors
- ✅ `php -r 'require dependencies.php'` — container wiring loads without error
- ❌ **Could not run the integration test suite** — every DB-backed test fails with `PDOException:
  SQLSTATE[HY000] [2002] Connection refused` (no MySQL in this container); this is an environment
  limitation, not a result of this change
- ❌ **Could not open the app in a browser or hit a running server** — no app server was started
  end-to-end against a real DB
- ❌ **Did not touch, and cannot verify, the actual Production runtime** at
  `https://pmo.jaideedigital.com/app/projects.html`

**This means acceptance item 7 ("Verify the actual Production runtime after deployment") is
explicitly NOT satisfied by this session and must not be reported as PASS until someone with
Production deploy access:**
1. Deploys `public/app/{app.js,app.css,projects.html}`, `src/Application/Http/Controllers/{WorkspaceController.php,ProjectController.php}`, `src/Config/{routes.php,dependencies.php}` from this branch.
2. Confirms `workspace.update` / `project.update` are actually granted to the relevant roles on the Production DB (seed migration `0012` should already cover this, but Production's `role_permissions` table should be spot-checked).
3. Opens `https://pmo.jaideedigital.com/app/projects.html` in a real browser and walks the
   acceptance checklist in `REVIEW-NOTE-Projects-RevisionX.md` §4.1 for real, including opening
   dev tools / network tab this time.

## 4. Scope discipline

Per the CTO's instructions: no redesign, no new page architecture, `PermissionResolver` /
Workspace Scope / Audit mechanism untouched, no unrelated PMOIS changes made in this revision.
Project "Edit" intentionally stays limited to progress/health/workspace (the fields that already
had repository-level update support) — extending it to rename/re-code a project would require new
domain methods, which is exactly the kind of new-architecture change this revision was told to
avoid; the UI reflects that by disabling those fields in the Edit form rather than silently
failing or lying about what it can save.
