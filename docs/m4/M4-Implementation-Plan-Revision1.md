# PMOIS v2 — M4 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M3 Revision 1 (99/100) • M0 R6 Design Freeze
**Date:** 2026-09-05

---

## 1. M4 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| Governance Template Management | governance_records CRUD (มีตั้งแต่ M1) + M4 validation layer | ✅ |
| CTO Working Instruction Management | `category='guideline'` + `audience='cto'` + `GET /governance/working-instructions?audience=` | ✅ |
| Dev Working Instruction Management | เดิมด้วย `audience='dev'` | ✅ |
| Governance Versioning | versioning (draft→published→superseded) มีตั้งแต่ M1 + auto-supersede — M4 เพิ่ม Web UI + การันตี isolation ด้วย test | ✅ |
| Project Governance Binding | governance_adoptions (มีตั้งแต่ M1) + Web UI binding section | ✅ |
| Project Template Management | project_templates (M1 R6 Phase 1.5) + template payload `governance.governance_version_id` | ✅ |
| Review Policy | `policy_type='review'` + `GET /governance-policies?policy_type=review` | ✅ |
| Delivery Policy | `policy_type='delivery'` | ✅ |
| Approval Policy | `policy_type='approval'` | ✅ |
| Governance API | endpoints เดิม + filters + policies/working-instructions endpoints | ✅ |
| Governance Web UI | `/app/governance.html` + governance binding section ใน project detail | ✅ |

## 2. System capability requirements — mapping

| ต้องการให้ระบบทำได้ | Implementation |
|---|---|
| กำหนด Governance Template กลาง | governance_records (workspace-scoped) + version_items + Web UI form |
| Version Governance ได้ | governance_versions + auto-supersede (draft→published→superseded) |
| ผูก Governance กับแต่ละ Project | governance_adoptions (per-project, active/retired) + UI binding |
| ใช้ Governance อัตโนมัติเมื่อสร้าง Project ใหม่ | `GovernanceAutoBindService` (template → workspace default → latest published) + template payload — implement ตั้งแต่ M1 และยังทำงาน |
| ปรับปรุง Governance ในอนาคตโดยไม่กระทบ Project เดิม | adoption ชี้ `governance_version_id` ตายตัว — publish v2 ไม่แตะ adoption เดิม (test `testNewVersionPublishDoesNotAffectExistingProjectAdoption`); project ที่ต้องการ version ใหม่ bind เพิ่ม explicitly |

## 3. Configuration over Hardcode

- Policy types (`review`, `delivery`, `approval`) และ audiences (`all`, `cto`, `dev`, `pmo`) เป็น config ใน `GovernancePolicyService` — เพิ่ม type ใหม่แก้ที่เดียว ไม่แตะ schema
- `audience`/`policy_type` เป็น column ใหม่แบบ nullable — records เดิมทั้งหมดยังใช้ได้ (Backward Compatible)

## 4. Files

| File | Role |
|---|---|
| migration `0060_alter_governance_records_add_audience_policy_type` | + audience, policy_type |
| `GovernanceRecord` (entity) + repo interface/impl | + audience/policyType + `listByWorkspaceFiltered` |
| `GovernancePolicyService` | validation + policy/working-instruction listing (config) |
| `GovernanceRecordController` | index filters + create (M4 fields) + `policies()` + `workingInstructions()` |
| `public/app/governance.html` | Web UI: templates, versions (draft→publish), policies, working instructions |
| `public/app/project.html` | + Governance Binding section (list/bind/retire) |
| `tests/Integration/GovernanceM4Test.php` | 7 tests — validation, filters, versioning isolation |

## 5. CTO Observation (recorded, ไม่ทำใน M4)

Timeline ต่อเนื่อง Revision → Review → Commit → Deployment → Release ในหน้าจอเดียว — บันทึก Future Enhancement: `docs/future-enhancements/UNIFIED-PROJECT-TIMELINE.md`
