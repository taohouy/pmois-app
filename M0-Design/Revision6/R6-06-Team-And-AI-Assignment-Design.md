# PMOIS v2 — Project Team Registry & AI Registry (Provider ⟂ Agent) — Revision 6

**Supersedes:** `M0-Design/09-AI-Assignment-Design.md` §1–2 and extends `12-Role-Permission-Matrix.md` §1 (role mapping unchanged, only the registry mechanics are new).

---

## 1. Project Team Registry (CTO Requirement #1)

### 1.1 Two-layer model

| Layer | Table | Role |
|---|---|---|
| **Authorization (live)** | `project_members` [EXISTING] | Read by `PermissionResolver::can()` — **unchanged, zero impact on existing permission logic** |
| **Assignment ledger (history)** | `project_member_assignments` [R6-NEW] | Full assign/unassign history, `assignment_source`, notes; never deleted |

Service-layer invariant: every active ledger row (`revoked_at IS NULL`) has a matching `project_members` row with the same (project_id, user_id, role_id); revoking the ledger row updates/removes the projection. `uq_project_user` on the live table allows one current role per user per project; the ledger retains every historical role change.

### 1.2 Role coverage (CEO / PMO / CTO / Dev / Viewer)

No new `roles` rows — the existing six roles + `is_platform_admin` cover the brief (per R5 role matrix):

| Brief role | Stored as | Where |
|---|---|---|
| CEO | `is_platform_admin = 1` (+ `PMO_REVIEWER`) | user attribute — appears in team views as "CEO" |
| PMO | `PMO_REVIEWER` | `project_member_assignments.role_id` |
| CTO | `CTO` | `project_member_assignments.role_id` |
| Dev | `MEMBER` / `SENIOR_DEV` | `project_member_assignments.role_id` |
| Viewer | `VIEWER` | `project_member_assignments.role_id` |

Multiple assignees per project per role = multiple ledger rows. The UI labels (`CEO/PMO/CTO/Dev/Viewer`) are presentation mappings over real roles, consistent with the rest of the design.

### 1.3 Flows

```
Assign:   POST /api/v1/projects/{id}/team-assignments   (project.team.manage)
          → INSERT project_member_assignments (assigned_by, assigned_at)
          → UPSERT project_members projection
          → audit_trails action='TEAM_ASSIGNED'
Revoke:   PATCH /api/v1/team-assignments/{id}/revoke    (project.team.manage)
          → UPDATE ... SET revoked_by, revoked_at
          → sync project_members projection
          → audit_trails action='TEAM_REVOKED'
History:  GET  /api/v1/projects/{id}/team-assignments?include=revoked
```

## 2. AI Registry — Provider separated from Agent (CTO Requirement #2)

### 2.1 Model

| Concept | Table | Examples |
|---|---|---|
| **Provider** (who makes it — global registry) | `ai_providers` [R6-NEW] | OpenAI, Anthropic, Google, Microsoft, Human |
| **Agent** (what is used — workspace registry) | `ai_consumers` [ALTER: + `provider_id`] | ChatGPT, Codex, Claude, Claude Code, Gemini, Human |

- Agent `code`/`name` remain workspace-scoped as today; **`provider_id` is mandatory on write** (`PROVIDER_REQUIRED` on omission).
- `Human` is both a Provider and an Agent — this is how a human "member" and an AI agent coexist in one mental model; human project participation still uses `project_member_assignments`, the Human Agent row exists for completeness/API attribution.

### 2.2 Project assignment references the Registry — never a hardcoded name

`project_ai_assignments` [EXISTING from migration 0040] continues to store `ai_consumer_id` (FK). Template payloads reference agents by `ai_consumer_code` **resolved against the workspace registry at creation time** — a template carrying a code that doesn't exist in the target workspace fails validation (`TEMPLATE_PAYLOAD_INVALID`). No AI name is ever stored outside the registry tables.

### 2.3 Backfill & reclassification (operational step after migration 0044)

1. All existing `ai_consumers` rows start with `provider_id = human`.
2. PMO/CTO reclassify real agents (e.g. `claude-code` → provider `anthropic`) via the existing `AiConsumerController` pattern, now with provider field.
3. New agents require choosing a provider in the create form (UI dropdown from `ai_providers`).

### 2.4 Interaction with Revision workflow (unchanged)

`revisions.dev_user_id` XOR `dev_ai_consumer_id` rule from R5 §3.6 stands. Development Mode (`projects.development_mode`) remains descriptive metadata for Dashboard/analysis (CTO Requirement #3) — it does not alter permissions; authority always comes from the role on the assignment row.

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
