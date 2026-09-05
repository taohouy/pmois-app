# PMOIS v2 — Project Creation Flow & Project Template — Revision 6

**Supersedes:** `M0-Design/06-Project-Creation-Move-Promote-Flows.md` §1 (creation only). Move/Change Parent/Promote flows (§2–4) and the Project ID immutability rule (§5) remain unchanged and still valid.

---

## 1. Updated Project Creation Flow (CEO — minimal input)

### 1.1 Required input (7 fields, unchanged from R5)

Workspace, Name, Code (+optional Abbreviation), Assign CTO, Assign Dev, Development Mode, optional Parent Project. **No technical registry field is required** — Repository, Tech Stack, Environment, Release, Dependency are all filled later by CTO/Dev (governing principle: *CEO กรอกข้อมูลให้น้อยที่สุด*).

### 1.2 New optional input

| Field | Source | Behaviour |
|---|---|---|
| `template_id` | UI selector | Optional; omitted → workspace's default template (`workspace_default_settings.default_project_template_id`) is applied automatically; if neither exists → creation proceeds without template |
| `cto_user_id` / `dev_user_ids` | UI | Pre-filled from Workspace Defaults when present; CEO may override |

### 1.3 Creation Pipeline (ordered)

```
1.  Validate permission project.create (ADMIN/CTO only — Q11 closed) + input fields.
2.  Resolve template: explicit template_id > workspace default > none.
3.  INSERT INTO projects (..., source_template_id) → projects.id assigned (permanent Internal ID).
4.  Apply Workspace Defaults for un-filled team slots:
      cto_user_id ?? wds.default_cto_user_id ; dev_user_ids ?? [wds.default_dev_user_id].
5.  INSERT project_members (authorization projection) AND project_member_assignments
    (ledger, assignment_source='workspace_default' | 'direct').
6.  INSERT project_ai_assignments for template-declared AI agents (ai_consumer_id from
    Registry — never a hardcoded name).
7.  Governance Binding: template.default_governance_version_id ?? wds.default_governance_version_id
    ?? workspace's latest published governance_version
    → INSERT governance_adoptions (auto baseline — unchanged from R5).
8.  Template Milestones: INSERT milestones (open) from template payload.
9.  Template Tech Stack / Environments: INSERT placeholder rows ONLY if payload declares them
    (optional; normally left for CTO/Dev).
10. API Token: IF payload.auto_create_project_token = true
    → INSERT api_tokens (project_id, token_hash; scopes informational) and return the
      raw token ONCE in the creation response — never stored in clear.
11. Default Configuration: INSERT workspace_module_settings rows per template payload
    (e.g. milestone_tracking=1).
12. Recompute profile_completeness_percent (= 0 unless template pre-filled items).
13. audit_trails: action='PROJECT_CREATED' (+ 'PROJECT_FROM_TEMPLATE' when template applied).
14. Response: { id, code, name, workspace_id, development_mode, source_template_id,
    profile_completeness_percent, project_token? }
```

Move / Change Parent / Promote flows: **unchanged** from R5 §2–4 (permission `project.structure.update`, history via `project_structure_history`, `CIRCULAR_HIERARCHY` / `PROJECT_CODE_CONFLICT` rules, ID immutability verified).

---

## 2. Workspace Defaults resolution order (CTO Requirement #11)

For every defaulted value: **explicit request value → workspace default → template payload → system fallback**.

| Value | Order |
|---|---|
| CTO | request → `wds.default_cto_user_id` → none (required — creation fails if unresolved) |
| Dev | request → `wds.default_dev_user_id` → none (required) |
| Governance version | template → `wds.default_governance_version_id` → latest published in workspace |
| Git Provider | (used when CTO registers first repo) → `wds.default_git_provider_id` → `gitlab` |
| Development Mode | request → `wds.default_development_mode` → `manual` |
| Permission preset | `wds.default_permission_preset` → `standard` |
| Template | request → `wds.default_project_template_id` → none |

`default_permission_preset` values are code-level presets (`standard`, `restricted`) mapping to which workspace roles get pulled in as project defaults; presets ship as config, extendable without schema change.

---

## 3. Template Payload Contract (`project_templates.payload` JSON)

```json
{
  "milestones": [
    { "code": "M1", "title": "Requirement & Design Sign-off", "planned_offset_days": 14 }
  ],
  "governance": { "governance_version_id": null },
  "ai_agents": [
    { "ai_consumer_code": "claude-code", "role": "SENIOR_DEV", "purpose": "implementation" }
  ],
  "tech_stack": [
    { "layer": "language", "name": "PHP", "version": "8.2" }
  ],
  "environments": [],
  "auto_create_project_token": true,
  "module_settings": { "milestone_tracking": true },
  "default_configuration": { "progress_update_cadence": "per_milestone" }
}
```

Validation (`TEMPLATE_PAYLOAD_INVALID`): unknown `ai_consumer_code` (must exist in the **workspace-scoped Agent registry**), unknown layer enum, negative offsets. `governance_version_id` may be `null` = "use workspace default" — a template never hardcodes a governance version across workspaces.

Templates are workspace-scoped (`uq_pt_workspace_code`), managed with `project.template.manage` (ADMIN/is_platform_admin only), one `is_default = TRUE` per workspace (enforced in service layer).

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
