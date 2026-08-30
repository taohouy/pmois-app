# PMOIS v2 — Key Relationships & Constraints (M0 Item 3)

**Revision 1** — aligned to real schema in `02-MySQL-ER-Diagram-Database-Table-Design.md`. Previous draft proposed multiple competing Project ID schemes (UUID v7, Redis counter, workspace counter); this revision picks exactly one, per CTO instruction.

## 1. Project Identity — Single Rule

| Aspect | Rule |
|---|---|
| **Internal/Immutable ID** | `projects.id` — `BIGINT UNSIGNED AUTO_INCREMENT`. Assigned once at creation, referenced by every FK, **never regenerated**. |
| **Human-readable code** | `projects.code` — existing column, `UNIQUE (workspace_id, code)`. Not a FK target. May be edited by CEO/PMO for readability. |
| **Rejected alternatives** | UUID v7, Redis-based counters, global-uniqueness schemes — rejected as redundant with the existing AUTO_INCREMENT PK and inconsistent with "preserve existing design." |

**Immutability guarantee:** `projects.id` is never touched by Move Workspace, Change Parent, or Promote operations — only `workspace_id` and `parent_project_id` columns change (see Section 3).

---

## 2. Project Hierarchy Model

```
Workspace (workspaces.id)
  └─ Root Project  (projects.parent_project_id IS NULL)
       └─ Child Project (projects.parent_project_id = <root's id>)
```

- `projects.workspace_id` — which workspace the project currently belongs to (existing column).
- `projects.parent_project_id` — self-referencing FK (new in this revision, see `02-...md` §3.2). `NULL` = root project within its workspace.

### Hierarchy Rules

| Rule | Enforcement |
|---|---|
| No circular references (A→B→C→A) | MySQL cannot express this as a DB constraint on a self-referencing FK; validated in the service layer before every `UPDATE parent_project_id` — walk the ancestor chain of the proposed new parent and reject if the target project appears in it. |
| Depth limit | Not fixed by this design (CTO Review flagged "do not invent requirements" — no arbitrary depth cap is imposed; application may warn on deep nesting but does not block it). |
| Child follows parent on workspace move | When a project's `workspace_id` changes, all rows where `parent_project_id` = that project's id are updated to the same new `workspace_id` in the same transaction (cascade at application layer, not a DB `ON UPDATE CASCADE`, so that `project_structure_history` can log each affected child individually). |
| Promote to workspace root | Sets `parent_project_id = NULL` on the promoted project; children keep following it. This does **not** create a new `workspaces` row automatically — "Promote" in M0 scope means "become a root project," not "spawn a new Workspace entity." (If CEO intends the latter, that is a separate, explicit `POST /workspaces` action — not silently triggered.) |

---

## 3. Structure History & Audit

Every hierarchy mutation writes to **both**:
1. `project_structure_history` (fast, hierarchy-specific timeline — see `02-...md` §3.3)
2. `audit_trails` (`action='STRUCTURE_CHANGE'`, `entity_type='project'` — generic audit view, see `13-Audit-Security-Design.md`)

```sql
-- Example: Move Workspace
START TRANSACTION;

INSERT INTO project_structure_history
    (project_id, change_type, from_workspace_id, to_workspace_id, reason, changed_by)
VALUES (:project_id, 'move_workspace', :old_ws, :new_ws, :reason, :actor_id);

UPDATE projects SET workspace_id = :new_ws WHERE id = :project_id;
UPDATE projects SET workspace_id = :new_ws WHERE parent_project_id = :project_id; -- cascade children

INSERT INTO audit_trails (workspace_id, user_id, action, entity_type, entity_id, before_value, after_value)
VALUES (:new_ws, :actor_id, 'STRUCTURE_CHANGE', 'project', :project_id,
        JSON_OBJECT('workspace_id', :old_ws), JSON_OBJECT('workspace_id', :new_ws));

COMMIT;
```

---

## 4. Code Uniqueness Across Workspace Moves

`projects.code` is unique **per workspace** (`uq_workspace_project_code`). When moving a project to a workspace that already has a project with the same `code`:

- The move is **blocked** with `409 CONFLICT` (`error.code = 'PROJECT_CODE_CONFLICT'`).
- CEO/PMO must rename the `code` on one of the two projects before retrying the move.
- No schema change (e.g., global uniqueness) is introduced to work around this — matches existing constraint design.

---

## 5. Progress / Status Write Authority (M0 Item 5, 10)

| Field | Who can write |
|---|---|
| `projects.progress_percent` | CTO, PMO_REVIEWER, or `is_platform_admin=1` — via permission code `project.progress.update`. Not `SENIOR_DEV`/`MEMBER`. |
| `projects.health` | Same as above. |
| `milestones.status` (open/closed) | CTO or `is_platform_admin=1` via `milestone.close` / `milestone.open`. Not writable by `SENIOR_DEV`/`MEMBER`. |
| `revisions.*` (summary, test_result, branch, commit_hash) | Dev (`SENIOR_DEV`/`MEMBER`) or the AI agent submitting on their behalf — factual delivery data only, per M0 Item 10 ("Dev สามารถบันทึกข้อเท็จจริงด้าน Delivery ได้"). |
| `revision_reviews.decision` | CTO only, via `revision.review`. |

Enforced through the existing `RequiresPermissionMiddleware` + `PermissionResolver`, not through token scopes (see `05-Auth-Authorization-Design.md`).

---

## 6. Referential Actions Summary

| Table | On parent delete |
|---|---|
| `projects.workspace_id → workspaces.id` | `RESTRICT` (existing) — a workspace with projects cannot be deleted |
| `projects.parent_project_id → projects.id` | `RESTRICT` (default) — a project with children cannot be deleted directly; must reparent/promote children first |
| `revisions.project_id → projects.id` | `RESTRICT` |
| `milestones.project_id → projects.id` | `RESTRICT` |
| `repositories.project_id → projects.id` | `RESTRICT` |
| `project_ai_assignments.project_id → projects.id` | `RESTRICT` |

No `ON DELETE CASCADE` is introduced anywhere in this revision — matches the existing codebase's pattern of using soft/status-based deactivation (`status='removed'`, `deleted_at`, `is_active`) rather than hard cascading deletes.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review*
*Date: 2026-08-30*