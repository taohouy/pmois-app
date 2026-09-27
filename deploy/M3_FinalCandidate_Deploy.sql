-- ============================================================================
-- PMOIS v2 — M3 Project Management — FINAL CANDIDATE Deployment SQL
-- Supersedes: deploy/M3_ProjectManagement_CompletionGate_Deploy.sql (v5)
-- Consolidated, idempotent, rerunnable — data correction only, NO schema change
-- ============================================================================
--
-- CEO/Deployer: run ONLY this one file. Do not also run the v5 SQL
-- (M3_ProjectManagement_CompletionGate_Deploy.sql) — this file contains the
-- exact same correction and is safe to run even if the v5 SQL was already run
-- (the INSERT below is guarded by NOT EXISTS, so a row that already exists is
-- never touched twice).
--
-- Purpose:
--   Fixes existing workspaces whose creator was never recorded as a member of
--   their own workspace (a defect in the pre-Completion-Gate `POST
--   /api/v1/workspaces` implementation, fixed in application code in an earlier
--   round — this script only repairs data that was already written before that
--   fix). Without this, that workspace's own creator gets a `NOT_FOUND` error
--   when trying to Edit/Activate/Deactivate a workspace they created before the
--   fix (e.g. VERIFY-FIX, TEST-AFTER-RESTART, SHOULD-FAIL, BOOTSTRAP, and
--   possibly the original JAIDEEDIGITAL workspace, if it too predates the fix).
--
--   Round 6 (this Final Candidate) introduced no new schema or data corrections
--   of its own — every Round 6 change was a pure code fix (cross-workspace
--   Create/Edit/Move Project). This file exists so CEO/Deployer runs exactly
--   ONE SQL file for the whole M3 effort, not a growing chain of per-round
--   files.
--
-- Scope discipline:
--   - Touches ONLY `workspace_members` — INSERTs rows, never UPDATEs or
--     DELETEs anything.
--   - Never touches `workspaces`, `projects`, or any other table.
--   - Never deletes, renames, or duplicates any existing workspace or project
--     (including MJU-ASSET, PMOIS, JAIDEEDIGITAL) — those are read-only inputs
--     to this script's WHERE clause, never written to.
--   - Never touches the test workspaces VERIFY-FIX / TEST-AFTER-RESTART /
--     SHOULD-FAIL / BOOTSTRAP beyond the one additive membership row each may
--     need — no cleanup of test data happens here (that is explicitly a
--     separate, later, CEO-approved activity per the Completion Gate directive).
--   - Adds a row ONLY for a (workspace, its own creator) pair that has no
--     membership row yet — a workspace whose creator already has a
--     `workspace_members` row is left completely untouched.
--   - Re-running this script after it has already applied cleanly (or after
--     the superseded v5 SQL already applied it) is a no-op: the second run's
--     "AFTER" count equals the first run's "AFTER" count (0, if no new
--     creator-less workspace was created in between), and no duplicate rows
--     are ever created (verified via GROUP BY ... HAVING COUNT(*) > 1 below).
--
-- How to run:
--   Execute this entire file as one script against the Production database
--   (e.g. `mysql pmois_production < M3_FinalCandidate_Deploy.sql`
--   or paste the whole file into your SQL client's query window and run it
--   top to bottom). Read the SELECT results as you go:
--     - "BEFORE" count: how many workspaces are missing their creator's
--       membership right now (0 is a valid, expected result if the code fix
--       — or the superseded v5 SQL — was already applied).
--     - "AFTER" count: MUST be 0. If it is not 0 after running this script,
--       STOP and report back to Dev with the query result — do not re-run
--       repeatedly expecting a different outcome.
--     - "DUPLICATE CHECK": MUST return zero rows. Confirms the backfill never
--       creates more than one membership row per (workspace, user) pair, on
--       this run or any prior run.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- STEP 1 — VERIFICATION (BEFORE): how many workspaces currently lack their
-- creator's membership row. Read-only, changes nothing.
-- ---------------------------------------------------------------------------
SELECT
  COUNT(*) AS workspaces_missing_creator_membership_BEFORE
FROM workspaces w
WHERE NOT EXISTS (
  SELECT 1 FROM workspace_members wm
  WHERE wm.workspace_id = w.id AND wm.user_id = w.created_by
);

-- Optional detail — which workspaces, by code, are affected (for your own
-- reference while reading the result above; does not affect STEP 2 below).
SELECT
  w.id, w.code, w.name, w.created_by, w.created_at
FROM workspaces w
WHERE NOT EXISTS (
  SELECT 1 FROM workspace_members wm
  WHERE wm.workspace_id = w.id AND wm.user_id = w.created_by
)
ORDER BY w.created_at;

-- ---------------------------------------------------------------------------
-- STEP 2 — BACKFILL: grant each affected workspace's own creator `ADMIN`
-- membership. Only inserts a row where one does not already exist for that
-- exact (workspace_id, user_id) pair — cannot create a duplicate membership
-- row, and cannot affect any workspace/user pair that already has one.
-- ---------------------------------------------------------------------------
INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT
  w.id,
  w.created_by,
  (SELECT id FROM roles WHERE code = 'ADMIN'),
  'active'
FROM workspaces w
WHERE NOT EXISTS (
  SELECT 1 FROM workspace_members wm
  WHERE wm.workspace_id = w.id AND wm.user_id = w.created_by
);

-- ---------------------------------------------------------------------------
-- STEP 3 — VERIFICATION (AFTER): must return 0. If it does not, the INSERT
-- above did not resolve every case (e.g. a workspace's created_by user_id no
-- longer exists in `users`) — stop and report this result back to Dev rather
-- than re-running.
-- ---------------------------------------------------------------------------
SELECT
  COUNT(*) AS workspaces_missing_creator_membership_AFTER
FROM workspaces w
WHERE NOT EXISTS (
  SELECT 1 FROM workspace_members wm
  WHERE wm.workspace_id = w.id AND wm.user_id = w.created_by
);

-- ---------------------------------------------------------------------------
-- STEP 4 — DUPLICATE CHECK: must return zero rows, on this run and any rerun.
-- ---------------------------------------------------------------------------
SELECT workspace_id, user_id, COUNT(*) AS cnt
FROM workspace_members
GROUP BY workspace_id, user_id
HAVING cnt > 1;

-- ============================================================================
-- End of M3 Final Candidate deployment SQL.
-- No further statements. No schema change. No data deleted or modified.
-- ============================================================================
