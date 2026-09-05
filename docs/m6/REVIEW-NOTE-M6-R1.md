# PMOIS v2 — Review Note (M6 Implementation Revision 1)

**สำหรับ:** CTO Review — M6 Knowledge Center
**อ้างอิง:** CTO Approval on M5 R1 (100/100)

---

## 1. Scope coverage (10/10)

Business Rules Registry ✅ • ADR ✅ • Known Issues ✅ • Risk Register ✅ • Decision Log ✅ • Future Enhancements ✅ • Project Knowledge Base ✅ • Search API ✅ • Knowledge Management UI ✅ • Knowledge Timeline ✅

## 2. Verification evidence

- **Full Suite: 159 tests / 380 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0062)
- KnowledgeM6Test (9): สร้างครบ 4 registry types, validation ต่อประเภท (severity/probability+impact/status), unified cross-type search, empty keyword rejection, project KB linking (เฉพาะ entries ที่ผูก project), timeline ordering, workspace isolation
- `php -l` ผ่านทุกไฟล์; migration 0062 additive (มี rollback)

## 3. Design decisions ให้ CTO ทราบ

1. **Registry ร่วมตารางเดียว** — business_rule/known_issue/risk/future_enhancement ใช้ `knowledge_entries` ร่วมกัน (type + nullable columns ต่อประเภท) ลด duplication และทำ unified search/timeline ได้ทันที; statuses ต่อประเภทเป็น config
2. **ADR + Decision Log reuse decision_registers** — ตาม M0 R6 §6 ที่กำหนดไว้; ADR = category 'architecture' (`GET /architecture-decisions`)
3. **Search API ค้นข้าม 3 sources** — knowledge_entries (LIKE), knowledge_articles (FULLTEXT เดิม), decision_registers — คืนแยกกลุ่มตาม source เพื่อให้ UI จัดหมวดได้
4. **Workspace-level knowledge ได้** — `project_id` nullable ใน entries (ความรู้ระดับ workspace ไม่ผูก project)
5. **Observation บันทึกแล้ว** — Extended Health Checks ไว้ M9: `docs/future-enhancements/EXTENDED-HEALTH-CHECKS.md`

## 4. Deliverable

`PMOIS_v2_M6_Implementation_Revision1.zip` — Source, Migration, Tests, Test Results, Plan, Changelog, Review Note, Web UI
