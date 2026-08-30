# PMOIS v2 — Governance Template Design (M0 Item 7)

**Revision 1** — the original draft invented a parallel JSON-template schema. The real system **already has** a governance engine (`0013`–`0015`, `0019`–`0020`) that satisfies this requirement; this revision maps M0 Item 7 onto it instead of duplicating it.

## 1. Mapping (existing tables, unchanged schema)

| M0 Concept | Real Table |
|---|---|
| Governance Template (family, e.g. "CTO Working Instruction") | `governance_records` (`code`, `title`, `category`, `status`) |
| Template Version | `governance_versions` (`version_label`, `content` LONGTEXT, `status` draft/published/superseded, `effective_date`) |
| Individual rule/clause within a version | `governance_version_items` (`item_code`, `title`, `sequence_order`) |
| Project ↔ Governance Binding ("Project Governance Binding") | `governance_adoptions` (`project_id`, `governance_version_id`, `adoption_status`) |
| Compliance tracking per rule | `governance_adoption_items` (`compliance_status` per `governance_version_item_id`) |

No new table is required. See `02-MySQL-ER-Diagram-Database-Table-Design.md` §2.11–2.12 for full column definitions.

## 2. Mapping M0's 8 Template Categories to `governance_records.category` (existing enum: `policy`/`standard`/`framework`/`guideline`)

| M0 Template Type | `category` used |
|---|---|
| CTO Working Instruction | `guideline` |
| Dev Working Instruction | `guideline` |
| Review Rule | `standard` |
| Revision Rule | `standard` |
| Commit / Push Rule | `standard` |
| Delivery Update Rule | `standard` |
| UAT Rule | `policy` |
| Production Release Rule | `policy` |

The distinct "type" name (e.g. "CTO Working Instruction" vs. "Dev Working Instruction") is carried in `governance_records.title`/`code`, not a new enum value — avoids an `ALTER TABLE ... MODIFY category ENUM(...)` unless CTO later decides finer categorization is needed.

## 3. Auto-Baseline on Project Creation

Per M0 Item 7 ("Project ใหม่ต้องได้รับ Governance Baseline อัตโนมัติ"):

```
On project creation (06-Project-Creation-Move-Promote-Flows.md §1):
  SELECT the workspace's designated default governance_version(s)
  (status='published', one per relevant governance_record)
  → INSERT INTO governance_adoptions (project_id, governance_version_id,
       adoption_status='in_progress', adopted_date=NOW())
```

"Designated default" is a workspace-level setting — this revision does not invent a new settings table for it; it can be expressed as the **latest `published` version per `governance_record`** at the time of project creation, which requires no new column. If CTO wants an explicit "default version" flag later, that is a small, separate `ALTER TABLE governance_versions ADD is_default_for_new_projects` — not part of this baseline.

## 4. API Access for CTO/Dev (M0 Item 7: "เรียก Governance ล่าสุดผ่าน API ได้")

Existing endpoint family (already implemented, per `GovernanceRecordController.php`, `GovernanceVersionController.php`, `GovernanceAdoptionController.php`): `GET /api/v1/governance-records`, `GET /api/v1/governance-versions`, `GET /api/v1/governance-adoptions`. No new endpoints required for read access; M0 only adds the auto-adoption trigger at project creation.

## 5. Versioning

Version lifecycle already exists: `draft → published → superseded` on `governance_versions.status`, with `published_by`/`published_at`. This satisfies "Template ต้องมี Version" without new mechanism.

## 6. Central Source (No Copy to Repository)

Unchanged principle: `governance_versions.content` (LONGTEXT) is the single source; nothing is written into a GitLab repository. This was already true of the existing design and required no change.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — mapped onto existing governance_* tables instead of a new parallel schema*
*Date: 2026-08-30*