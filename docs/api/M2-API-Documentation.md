# PMOIS v2 — M2 Dashboard API Documentation

Base URL `/api/v1` • Envelope: `{success, data, error{code,message,details}, meta{timestamp}}` • Read-only ทั้งหมด (GET) • Auth: Bearer token หรือ `pmois_session` cookie

---

## 1. Workspace-level dashboards — permission `workspace.view`

### GET `/dashboards/workspace`
Workspace Dashboard แบบรวม:
```json
{
  "workspace_id": 1,
  "projects": { "total": 3, "planning": 1, "active": 2, "on_hold": 0, "closed": 0,
                "green": 1, "yellow": 1, "red": 1, "manual": 1, "ai_assisted": 1, "ai_dev_auto": 1,
                "avg_progress": 42, "avg_completeness": 30 },
  "milestones": { "total": 2, "open": 1, "closed": 1 },
  "upcoming_milestones": [ { "id": 9, "project_id": 5, "project_code": "MAR-00892", "code": "M2", "title": "UAT", "planned_date": "2026-09-12" } ],
  "recent_releases": [ { "project_code": "MAR-00892", "release_type": "beta", "version_label": "0.9.0-beta1", "status": "released", "released_at": "..." } ],
  "governance": { "total": 2, "compliant": 1, "in_progress": 1, "non_compliant": 0, "retired": 0 },
  "recent_activities": [ { "action": "project_created", "entity_type": "project", "entity_id": 5, "user_name": "...", "created_at": "..." } ],
  "timeline": [ { "event_type": "milestone_closed|release|status_update", "occurred_at": "...", "project_code": "...", "detail": "..." } ]
}
```

### GET `/dashboards/progress-summary`
```json
{ "workspace_id": 1, "average_progress_percent": 42, "average_profile_completeness_percent": 30,
  "buckets": { "0-25": 1, "26-50": 1, "51-75": 1, "76-100": 0 } }
```

### GET `/dashboards/health-summary`
```json
{ "workspace_id": 1, "total_projects": 3, "green": 1, "yellow": 1, "red": 1 }
```
> Health Summary แยกจาก Progress Summary ชัดเจน (work tracking vs data completeness/health — M0 R6 Requirement #5)

### GET `/dashboards/statistics`
```json
{ "workspace_id": 1,
  "projects": { "total": 3, "by_status": {...}, "by_development_mode": {...} },
  "milestones": {...}, "governance": {...} }
```

### GET `/dashboards/recent-activities?limit=10`
`limit` 1–50 (default 10) — คืน `audit_trails` ล่าสุดของ workspace:
```json
[ { "id": 88, "action": "team_assigned", "entity_type": "project_member_assignment", "entity_id": 12,
    "user_id": 7, "user_name": "Somchai CTO", "created_at": "..." } ]
```

## 2. Portfolio-level — `GET /dashboards/portfolio` (is_platform_admin เท่านั้น — มุมมอง CEO)

```json
{
  "totals": { "workspaces": 2, "total_projects": 4, "by_status": {...}, "by_health": {...},
              "manual": 1, "ai_assisted": 2, "ai_dev_auto": 1, "avg_progress": 54 },
  "workspaces": [ { "id": 1, "name": "...", "status": "active", "projects": 3,
                    "avg_progress_percent": 42, "health": { "green": 1, "yellow": 1, "red": 1 } } ],
  "dependencies": { "total": 2, "depends_on": 2, "blocked_by": 0 },
  "recent_activities": [ cross-workspace, "workspace_id" ระบุที่มา ]
}
```
Non-admin → `403 FORBIDDEN`

## 3. Project-level — permission `project.view`

### GET `/projects/{id}/dashboard`
Project Dashboard + **Parent roll-up อัตโนมัติเมื่อเป็น parent** (project ต่าง workspace → 404 fail-closed):
```json
{
  "project": { "id": 5, "code": "MAR-00892", "name": "...", "status": "active", "health": "green",
               "progress_percent": 60, "profile_completeness_percent": 55, "development_mode": "ai_assisted",
               "parent_project_id": null },
  "milestones": { "total": 2, "open": 1, "closed": 1, "next": { "code": "M2", "title": "UAT", "planned_date": "..." } },
  "team": { "humans": 2, "ai_agents": 1 },
  "releases": { "total": 1, "latest": { "release_type": "beta", "version_label": "0.9.0-beta1", "status": "released", "at": "..." } },
  "environments": { "total": 2, "development": 1, "uat": 1, "production": 0 },
  "repositories": { "total": 1 },
  "dependencies": { "depends_on": 1, "depended_on_by": 0, "blocked_by": 0 },
  "governance": { "active_adoptions": 1, "non_compliant": 0 },
  "revisions": { "submitted": 0, "cto_approved": 0, "cto_rejected": 0, "committed": 0 },
  "timeline": [ ...เหมือน /timeline ],
  "children": [ { "id": 6, "code": "...", "status": "active", "health": "red", "progress_percent": 20, ... } ],
  "children_rollup": { "total": 1, "avg_progress_percent": 20, "health": {...}, "by_status": {...} }
}
```
(`children`/`children_rollup` ปรากฏเฉพาะเมื่อมีโครงการลูก — leaf project ไม่มี key เหล่านี้)

### GET `/projects/{id}/timeline?limit=20`
Merged event timeline เรียงใหม่→เก่า จาก config-driven sources (`status_update`, `milestone_closed`, `release`, `revision`):
```json
[ { "event_type": "release", "occurred_at": "2026-09-05 10:00:00", "detail": "beta 0.9.0-beta1 (released)" },
  { "event_type": "milestone_closed", "occurred_at": "...", "detail": "M1: Sign-off" } ]
```

## 4. Error codes

`FORBIDDEN` 403 (portfolio โดย non-admin) • `NOT_FOUND` 404 (project ต่าง workspace — fail-closed)
