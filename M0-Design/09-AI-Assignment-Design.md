# PMOIS v2 — AI Assignment Design (M0 Item 6, 12)

**Revision 1** — the AI Agent **Registry** already exists (`ai_consumers`); only the project-level **Assignment** was missing. This revision removes the invented parallel registry schema from the draft and keeps just the one new table.

## 1. Registry vs. Assignment (two distinct, existing + new tables)

| Concept | Table | Status |
|---|---|---|
| **Registry** — which AI agents/consumers exist at all (ChatGPT, Codex, Claude, Claude Code, Gemini, custom) | `ai_consumers` (`code`, `name`, `description`, `status`) | **EXISTING** (`0025_create_ai_consumers.sql`) |
| **Assignment** — which registered agent is currently working on which project, in which role | `project_ai_assignments` | **NEW** (`02-...md` §3.7) |

Registering a new AI type = one `INSERT INTO ai_consumers`, using the existing endpoint/controller pattern already in the codebase (`AiConsumerController.php`). No change needed there.

## 2. Assignment Flow

```
Assign:
  POST /api/v1/projects/{id}/ai-assignments   (permission: ai_assignment.manage — CTO/PMO)
  { "ai_consumer_id": 3, "role_id": <MEMBER or CTO role id>, "purpose": "code_review" }
  → INSERT INTO project_ai_assignments (assigned_by = actor, assigned_at = NOW())
  → audit_trails: action='AI_ASSIGNED'

Revoke:
  PATCH /api/v1/ai-assignments/{id}/revoke
  → UPDATE project_ai_assignments SET revoked_by=actor, revoked_at=NOW()
  → audit_trails: action='AI_REVOKED'
  (row is never deleted — revoked_at IS NOT NULL = history)
```

History is simply: all rows where `revoked_at IS NULL` = currently active; all rows = full history. No separate history table needed.

## 3. AI as Dev/CTO Participant in the Revision Workflow

`revisions.dev_ai_consumer_id` (nullable, `02-...md` §3.6) lets a revision be attributed to an AI agent instead of a human `dev_user_id` — exactly one of the two is populated. This is how "Dev Agent" is recorded on a delivery, per M0 Item 9's field list, without inventing a separate delivery schema for AI vs. human submissions.

## 4. Context Handover

`ai_context_exports` (existing table, `0027_create_ai_context_exports.sql`) already persists `payload_snapshot` (JSON) per export, scoped by `ai_consumer_id` and `scope_entity_type/id`. This satisfies M0 Item 15's "AI Context / Handover" data requirement — no new table.

## 5. Development Mode Interaction

`projects.development_mode` (`manual`/`ai_assisted`/`ai_dev_auto`, `02-...md` §3.2) is descriptive metadata only in M0 — it does not by itself change what an AI-attributed revision is allowed to do; authority is still governed by the `role_id` on the `project_ai_assignments` row (e.g. an AI assigned the `CTO` role could, in principle, call `revision.review` — whether that is actually desirable is a policy decision for CTO, not a technical gap in this design). This revision does not hard-code per-mode permission overrides, since none were confirmed as a requirement — flagged as an open question in `15-Risks-and-Open-Questions.md`, not invented here.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — mapped onto existing ai_consumers registry + one new assignment table*
*Date: 2026-08-30*