# PMOIS v2 — M4 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M3 R1 (99/100) — เริ่ม M4 PMO Governance ได้ทันที
**Date:** 2026-09-05

---

## 1. Database

- `0060_alter_governance_records_add_audience_policy_type` (+rollback) — `audience ENUM(all/cto/dev/pmo) NULL` + `policy_type VARCHAR(50) NULL` — nullable = Backward Compatible (records เดิมไม่กระทบ)

## 2. Source Code

- `GovernanceRecord` entity — + `audience`, `policyType`, `toArray()`
- `GovernanceRecordRepositoryInterface` — + `listByWorkspaceFiltered(audience, policyType, category)`; `create()` เพิ่ม optional params
- `MySqlGovernanceRecordRepository` — implement ตาม interface
- `GovernancePolicyService` [NEW] — Configuration over Hardcode: `POLICY_TYPES = [review, delivery, approval]`, `AUDIENCES = [all, cto, dev, pmo]`; validation (policy_type เฉพาะ category=policy, working instructions เฉพาะ category=guideline); listing policies / working instructions
- `GovernanceRecordController` — index รับ filters (audience/policy_type/category), create ผ่าน service, + `policies()` / `workingInstructions()` endpoints
- Routes: `GET /governance-policies`, `GET /governance/working-instructions` (`governance_record.view`)
- DI wiring ใหม่

## 3. Web UI (M3 UI ต่อยอด)

- `public/app/governance.html` [NEW] — สร้าง template/policy/working instruction (ฟอร์มพร้อม validation), filter list, version management ต่อ record (create draft → publish; แสดงคำอธิบาย auto-supersede)
- `public/app/project.html` — + Governance Binding section (list adoptions, bind governance version, retire)
- `app.js` — nav เพิ่ม Governance

## 4. Governance อัตโนมัติเมื่อสร้าง Project ใหม่ (มีอยู่แล้ว — verified)

`GovernanceAutoBindService` (M1): template payload `governance.governance_version_id` → workspace default → latest published. M4 เพิ่ม test การันตี **isolation**: publish version ใหม่ (v2) → supersede v1 แต่ adoption เดิมของ project ยังชี้ v1 (`testNewVersionPublishDoesNotAffectExistingProjectAdoption`) — ปรับปรุง governance ในอนาคตไม่กระทบ project เดิม; ต้องการ version ใหม่ = bind เพิ่ม explicitly

## 5. Test Results

**Full Suite: 143 tests / 340 assertions — OK** (GovernanceM4Test 7 tests: working instructions, policies 3 ประเภท, invalid policy_type, policy_type cross-category, filters, versioning isolation)

## 6. CTO Observation (recorded)

Unified Project Timeline (Revision→Review→Commit→Deployment→Release ในหน้าจอเดียว) — `docs/future-enhancements/UNIFIED-PROJECT-TIMELINE.md` (ไม่ทำใน M4 ตามมติ)
