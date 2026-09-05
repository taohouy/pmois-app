# PMOIS v2 — Dependency Registry Design — Revision 6

**New design** (CTO Requirement #8). No R5 counterpart.

## 1. Model

`project_dependencies` [R6-NEW] — see `R6-01-ER-Diagram-Revision6.md` §2.8 for DDL. Typed directed edges between projects:

- `depends_on` — A depends on B (B must deliver for A to proceed)
- `blocked_by` — A is blocked by B (softer scheduling signal, often the mirror of a `depends_on` edge)

Both typed directions are stored explicitly (not derived) so the Portfolio Dashboard can query each list directly; the UI offers a one-click "create mirror edge" so the two views stay consistent without forcing it (some blocking relationships are political/scheduling, not technical).

## 2. Validation rules (service layer)

| Rule | Error |
|---|---|
| No self-edge | `DEPENDENCY_SELF` 409 |
| `depends_on` chain must stay acyclic (ancestor walk — same algorithm as `CIRCULAR_HIERARCHY` for hierarchy) | `DEPENDENCY_CIRCULAR` 409 |
| `blocked_by` edges are informational — cycles allowed and reported as a warning in the graph payload, not rejected | — |
| Both projects must be in the same workspace | `DEPENDENCY_WORKSPACE_MISMATCH` 409 |
| Deleting a project → edges removed (FK RESTRICT on active edges; service refuses project close while it is the target of an active `depends_on` from a non-closed project) | `VALIDATION_ERROR` |

## 3. Graph API (Portfolio Dashboard)

`GET /api/v1/dependencies/graph?workspace_id=N` returns:

```json
{
  "nodes": [ { "id": 892, "code": "MAR-00892", "name": "...", "status": "active",
               "health": "green", "progress_percent": 40, "development_mode": "ai_assisted" } ],
  "edges": [ { "from": 892, "to": 870, "type": "depends_on" } ],
  "warnings": [ { "type": "blocked_by_cycle", "projects": [892, 870, 855] } ]
}
```

Graph is rendered read-only for all roles; editing requires `project.dependency.manage` (ADMIN/CTO/SENIOR_DEV per `R6-02` §3).

## 4. Audit

Every edge create/delete → `audit_trails` (entity_type='project_dependency', action='DEPENDENCY_ADDED'/'DEPENDENCY_REMOVED', before/after JSON).
