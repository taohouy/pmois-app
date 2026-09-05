# PMOIS v2 — Release Registry Design — Revision 6

**New design** (CTO Requirement #9). No R5 counterpart — R5 conflated release history into Timeline; R6 separates them explicitly.

## 1. Separation of concerns (three distinct registers)

| Register | Table | Grain | Purpose |
|---|---|---|---|
| **Timeline / PMO reporting** | `project_status_updates` [EXISTING] | one row per reporting date | Portfolio narrative (on_track/at_risk/off_track) |
| **Revision cycle** | `revisions` [EXISTING] | one row per Dev↔CTO submission | Review/commit workflow |
| **Release history** | `project_releases` [R6-NEW] | one row per released version | What shipped, where, when |

## 2. Release model

Fields per DDL in `R6-01` §2.9:

- `release_type`: `alpha` / `beta` / `rc` / `production` / `hotfix`
- `version_label`: free-form but unique per project (`uq_pr_project_version`) — e.g. `1.2.0-rc1`, `1.2.1-hotfix`
- `status`: `planned` → `in_progress` → `released` → (`rolled_back` | `cancelled`)
- Links: `repository_id` (what was built), `environment_id` (where it deployed — from the new Environment Registry), `milestone_id` (which milestone it closes)

## 3. State machine

```
planned ──▶ in_progress ──▶ released
                │                │
                ├──▶ cancelled   ├──▶ rolled_back ──▶ (re-release as new row, e.g. hotfix)
                └────────────────┴──▶ cancelled
```

Transitions enforced in service layer; only `released` / `rolled_back` rows may set `released_by` / `released_at`. `hotfix` releases SHOULD reference the production release they patch via `release_notes` (simple text convention — no extra FK, per minimal-schema principle).

## 4. Interaction with other registers

- Creating/committing a `revision` does **not** auto-create a release (release is an explicit CTO/Dev act with `project.release.manage`).
- A `production` release marked `released` may optionally post a `project_status_updates` row (PMO timeline entry) — same reuse pattern as revision-commit → timeline in R5.
- Releases count toward **Profile Completeness** (weight 10, `R6-01` §4).

## 5. API

`GET/POST/PATCH /api/v1/projects/{id}/releases` — permission `project.view` / `project.release.manage`; status transitions via `PATCH` (state machine violations → `VALIDATION_ERROR`); audit action `RELEASE_CREATED` / `RELEASE_STATUS_CHANGED`.
