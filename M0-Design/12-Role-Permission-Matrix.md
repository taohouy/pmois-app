# PMOIS v2 — Role Permission Matrix (M0 Item 15)

**Revision 1** — corrects the role model. The initial draft used `CEO / CTO / Dev / Viewer` as if they were four role rows; the real system's `roles` table (`0011_seed_default_roles.sql`) has `ADMIN, MEMBER, VIEWER, CTO, SENIOR_DEV, PMO_REVIEWER`, plus a separate `users.is_platform_admin` flag. This revision maps the CEO brief's role names onto the real ones instead of introducing new role rows.

## 1. Role Mapping

| Brief's role concept | Real system equivalent | Notes |
|---|---|---|
| **CEO / Portfolio Owner** | `users.is_platform_admin = 1` **+** `PMO_REVIEWER` role (or `ADMIN` where full workspace control is needed) | Not a single role row — an attribute + role combination. Exactly one platform admin is expected per the brief ("CEO มีเพียงหนึ่งคน"), enforced operationally (admin discipline), not by a DB constraint, since `is_platform_admin` has no uniqueness constraint today — see `15-Risks-and-Open-Questions.md` Q10. |
| **CTO** | `CTO` role (existing, already seeded with governance/RFC/decision permissions) | Multiple CTOs per project supported via multiple `project_members` rows with `role_id = CTO`. |
| **Dev** | `MEMBER` (general) or `SENIOR_DEV` (technical, no review rights) | Multiple Devs per project supported via `project_members`. AI Dev is `project_ai_assignments` with `role_id = MEMBER`/`SENIOR_DEV`, see `09-AI-Assignment-Design.md`. |
| **Viewer** | `VIEWER` role (existing) | Read-only, matches existing seed (`*.view` permissions only). |

## 2. Permission Resolution Order (existing `PermissionResolver`, unchanged)

```
1. permission_code == 'workspace.create' → users.is_platform_admin only
2. project_members (role override, if projectId given)
3. workspace_members (fallback)
4. role_permissions (role_id + permission_code)
```

No token scope is consulted for this decision (see `05-Auth-Authorization-Design.md`).

## 3. Existing Grant Matrix (`0012_seed_role_permissions.sql`, unchanged, restated for reference)

| Permission (sample) | ADMIN | CTO | SENIOR_DEV | MEMBER | PMO_REVIEWER | VIEWER |
|---|---|---|---|---|---|---|
| `project.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `project.create` | ✓ | ✗ | ✗ | ✓* | ✗ | ✗ |
| `project.update` | ✓ | ✗ | ✗ | ✓* | ✗ | ✗ |
| `governance_version.publish` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `rfc.review` | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| `decision_register.approve` | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| `api_token.create` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `audit_trail.view` | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |

\* `MEMBER` currently has `project.create`/`project.update` in the existing seed — for M0's "CEO creates project" principle, this revision recommends restricting `project.create` to `ADMIN`/`is_platform_admin` + `PMO_REVIEWER` going forward (a seed-data change, not a schema change), so that project creation matches "CEO มีเพียงหนึ่งคนและเป็น Portfolio Owner." This is flagged as a **decision for CTO**, not silently changed — see `15-Risks-and-Open-Questions.md` Q11.

## 4. New Permission Codes for M0 (added to `role_permissions`, no schema change — see `05-Auth-Authorization-Design.md` §4.1)

| Permission code | ADMIN | CTO | SENIOR_DEV | MEMBER | PMO_REVIEWER | VIEWER |
|---|---|---|---|---|---|---|
| `project.structure.update` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `project.progress.update` | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| `milestone.close` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `revision.create` | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| `revision.review` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `repository.manage` | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| `ai_assignment.manage` | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |

This directly encodes M0 Item 10's rule: Dev (`SENIOR_DEV`/`MEMBER`) gets `revision.create` (factual delivery data) but not `revision.review`, `milestone.close`, or `project.progress.update` — exactly the "Dev ห้ามประกาศ Progress/Milestone Closed" constraint.

## 5. Fail-Closed Summary

Not a member of the workspace/project at all → `PermissionResolver::can()` returns `false` for everything except nothing (no default access). Matches existing behaviour, restated for completeness.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — mapped onto real roles/role_permissions*
*Date: 2026-08-30*