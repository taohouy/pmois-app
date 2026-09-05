# PMOIS v2 — M6 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M5 Revision 1 (100/100) • M0 R6 Design Freeze • CTO Constraints (LINE / GitLab / Telegram)
**Date:** 2026-09-05

---

## 1. M6 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| Business Rules Registry | `knowledge_entries` type `business_rule` + CRUD | ✅ |
| Architecture Decision Records (ADR) | reuse `decision_registers` (category='architecture') + `GET /architecture-decisions` | ✅ |
| Known Issues | type `known_issue` (severity required) + `related_revision_id` link | ✅ |
| Risk Register | type `risk` (probability/impact/mitigation required fields) | ✅ |
| Decision Log | reuse `decision_registers` (API เดิม) + รวมใน unified search | ✅ |
| Future Enhancements | type `future_enhancement` (workspace-level ได้) | ✅ |
| Project Knowledge Base | `GET /projects/{id}/knowledge` (entries + articles ของ project) + reuse `knowledge_articles` | ✅ |
| Search API | `GET /knowledge/search?q=` — ค้นข้ามประเภท (entries + articles fulltext + decisions) | ✅ |
| Knowledge Management UI | `/app/knowledge.html` — สร้าง/กรอง/ค้นหา/ADR/timeline | ✅ |
| Knowledge Timeline | `GET /knowledge-timeline?project_id=&limit=` | ✅ |

## 2. System capability requirement

"เก็บองค์ความรู้ของแต่ละโครงการ แยกตามประเภท ค้นหา อ้างอิง เชื่อมโยงกับ Project ได้"
- แยกประเภท: `entry_type` + validation ต่อประเภท (config `KnowledgeService::STATUSES`)
- ค้นหา: unified search (entries LIKE + articles FULLTEXT + decisions) — คืนแยกกลุ่มตาม source
- อ้างอิง/เชื่อมโยง: `project_id` โดยตรง + `related_revision_id` / `related_url` + reuse `knowledge_links` (polymorphic) เดิม

## 3. Design decisions

1. **Registry ร่วมตารางเดียว** (`knowledge_entries`) — 4 ประเภทใหม่ใช้โครงสร้างเดียวกัน (type + nullable type-specific columns: severity/probability/impact/mitigation) ลด code duplication; status semantics ต่อประเภทเป็น config (`STATUSES`)
2. **ADR + Decision Log reuse decision_registers** — ไม่สร้างตารางใหม่ (M0 R6 กำหนดแล้วว่า category='architecture' ครอบคลุม ADR)
3. **Project Knowledge Base reuse knowledge_articles** (มี fulltext index จาก migration 0024)
4. **Backward Compatible** — migration 0062 additive; API เดิมไม่เปลี่ยน
5. **Workspace isolation** — ทุก query ผ่าน workspace filter (test ครอบ)

## 4. Files

| File | Role |
|---|---|
| migration `0062_create_knowledge_entries` | registry ร่วม 4 ประเภท |
| `KnowledgeEntry` + `KnowledgeEntryRepositoryInterface` + `MySqlKnowledgeEntryRepository` | CRUD + search + timeline |
| `KnowledgeService` | validation ต่อประเภท (config), unified search, ADR, project KB, timeline |
| `KnowledgeController` + routes | 9 endpoints |
| `public/app/knowledge.html` + nav | Knowledge Management UI |
| `tests/Integration/KnowledgeM6Test.php` | 9 tests |

## 5. CTO Observation (recorded — M9)

Extended Health Check (Database/Redis/Queue/Telegram/Storage/Disk/GitLab Connectivity) — บันทึก `docs/future-enhancements/EXTENDED-HEALTH-CHECKS.md` (ไม่ทำใน M6 ตามมติ)
