# PMOIS v2 — Project Creation / Move / Promote Flows (M0 Item 8, 9)

**Revision 1** — aligned to real `projects` table + `[ALTER]` columns from `02-MySQL-ER-Diagram-Database-Table-Design.md`, single Project Identity rule from `03-Key-Relationships-Constraints.md`.

## 1. Project Creation Flow (CEO — minimal fields, per M0 Item 3)

### 1.1 Required fields (exactly matches CTO's minimal-input principle)

| Field | Column | Required |
|---|---|---|
| Workspace | `workspace_id` | ✓ |
| Parent Project (optional) | `parent_project_id` | optional, default `NULL` (root) |
| Project Name | `name` | ✓ |
| Abbreviation / Code | `code`, `abbreviation` | ✓ (`code`) |
| Description | `description` | optional |
| Assign CTO | → `project_members` row (role `CTO`) | ✓ |
| Assign Dev | → `project_members` row (role `MEMBER`/`SENIOR_DEV`) | ✓ |
| Development Mode | `development_mode` | ✓ (`manual`/`ai_assisted`/`ai_dev_auto`) |

### 1.2 Steps

```
1. CEO/PMO (permission: project.create) submits the fields above.
2. INSERT INTO projects (...) → projects.id assigned by AUTO_INCREMENT
   — this id is the permanent Internal Project ID (never regenerated).
3. INSERT INTO project_members for the assigned CTO and Dev.
4. INSERT INTO governance_adoptions binding this project to the workspace's
   default active governance_version (auto baseline — reuses existing table,
   no new mechanism, see 08-Governance-Template-Design.md).
5. audit_trails: action='PROJECT_CREATED'.
6. Response: { data: { id, code, name, workspace_id, development_mode, ... } }
```

No repository, milestone, or technology-stack field is required at this step — those are added later by CTO/Dev via `repositories`/`milestones` (M0 Item 14, Progressive Project Profile).

---

## 2. Move Workspace

```
Permission required: project.structure.update (CTO or is_platform_admin)

1. Validate target workspace_id exists and actor has access to it.
2. Validate: no project in the target workspace already has this project's `code`
   → if conflict: 409 PROJECT_CODE_CONFLICT (see 03-Key-Relationships-Constraints.md §4)
3. INSERT project_structure_history (change_type='move_workspace', from/to workspace_id)
4. UPDATE projects SET workspace_id = :new WHERE id = :project_id
5. UPDATE projects SET workspace_id = :new WHERE parent_project_id = :project_id  -- children follow
6. audit_trails: action='STRUCTURE_CHANGE'
7. governance_adoptions is NOT automatically re-bound — the project keeps its
   currently adopted governance_version unless CTO explicitly re-binds it
   (moving workspace does not implicitly change governance, since governance
   is bound per-project via governance_adoptions.project_id, not derived from
   workspace at read time).
```

## 3. Change Parent

```
Permission required: project.structure.update

1. Validate new parent_project_id belongs to the same workspace_id.
2. Walk the ancestor chain of the proposed new parent; if the current project
   appears in that chain → 409 CIRCULAR_HIERARCHY.
3. INSERT project_structure_history (change_type='change_parent', from/to parent_project_id)
4. UPDATE projects SET parent_project_id = :new_parent WHERE id = :project_id
5. audit_trails: action='STRUCTURE_CHANGE'
```

## 4. Promote to Workspace Root

```
Permission required: project.structure.update

1. Set parent_project_id = NULL for the target project (it becomes a root
   project within its current workspace — see 03-Key-Relationships-Constraints.md
   §2 for the scope clarification: this does not auto-create a new Workspace row).
2. Children (parent_project_id = this project) keep following it — no change
   needed for them since only the promoted project's parent_project_id changes.
3. INSERT project_structure_history (change_type='promote_to_workspace')
4. audit_trails: action='STRUCTURE_CHANGE'
```

If CEO's actual intent is a brand-new independent `workspaces` row (rather than "become root within the same workspace"), that is a separate explicit action: `POST /api/v1/workspaces` followed by a Move Workspace — not implied automatically by "Promote."

---

## 5. Project ID Immutability — Verified Across All Three Flows

`projects.id` is never written to in any `UPDATE` statement in Sections 2–4 above — only `workspace_id` and `parent_project_id` change. This satisfies M0 Item 4's constraint verbatim: "Project ID เดิมต้องไม่เปลี่ยนเมื่อมีการปรับโครงสร้าง."

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review*
*Date: 2026-08-30*