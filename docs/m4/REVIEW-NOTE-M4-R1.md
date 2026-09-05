# PMOIS v2 — Review Note (M4 Implementation Revision 1)

**สำหรับ:** CTO Review — M4 PMO Governance
**อ้างอิง:** CTO Approval on M3 R1 (99/100)

---

## 1. Scope coverage (11/11)

Governance Template Management ✅ • CTO Working Instructions ✅ • Dev Working Instructions ✅ • Governance Versioning ✅ • Project Governance Binding ✅ • Project Template Management ✅ (M1 R6) • Review Policy ✅ • Delivery Policy ✅ • Approval Policy ✅ • Governance API ✅ • Governance Web UI ✅

## 2. System capability requirements (5/5)

- กำหนด Template กลาง ✅ • Version ได้ ✅ • ผูกกับ Project ✅ • ใช้อัตโนมัติเมื่อสร้าง Project ✅ (auto-bind จาก M1, verified) • ปรับปรุงในอนาคตไม่กระทบ Project เดิม ✅ (**test พิสูจน์**: publish v2 → supersede v1 → adoption เดิมยังชี้ v1)

## 3. Verification evidence

- **Full Suite: 143 tests / 340 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0060)
- GovernanceM4Test 7 tests: CTO working instruction, 3 policy types, invalid policy_type, cross-category rejection, filters, **versioning isolation**
- `php -l` ผ่านทุกไฟล์; migration 0060 additive (nullable columns, มี rollback)

## 4. Design decisions ให้ CTO ทราบ

1. **Working Instructions และ Policies ใช้ governance_records เดิม** เพิ่ม 2 columns (audience / policy_type) แทนสร้างตารางใหม่ — reuse template/versioning/publish machinery ทั้งหมดที่ผ่าน review แล้ว (Backward Compatible: NULL = พฤติกรรมเดิม)
2. **Policy types เป็น config** ใน `GovernancePolicyService` — เพิ่ม 'security' ฯลฯ แก้ array เดียว
3. **Validation rules**: policy_type ได้เฉพาะ category='policy'; audience cto/dev ได้เฉพาะ category='guideline'
4. **Governance Web UI** ต่อยอด static app จาก M3 — หน้า governance.html (templates/versions/policies/instructions) + governance binding ใน project detail
5. **Observation บันทึกแล้ว** — `docs/future-enhancements/UNIFIED-PROJECT-TIMELINE.md`

## 5. Deliverable

`PMOIS_v2_M4_Implementation_Revision1.zip` — Source, Migration, Tests, Test Results, Plan, Changelog, Review Note, Web UI
