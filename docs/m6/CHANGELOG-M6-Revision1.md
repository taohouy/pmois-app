# PMOIS v2 — M6 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M5 R1 (100/100) — เริ่ม M6 Knowledge Center ได้ทันที
**Date:** 2026-09-05

---

## 1. Database

- `0062_create_knowledge_entries` (+rollback) — registry ร่วม 4 ประเภท (business_rule / known_issue / risk / future_enhancement) + type-specific nullable columns (severity/probability/impact/mitigation) + related_revision_id / related_url

## 2. New Source Code

- `Domain/Knowledge/KnowledgeEntry` + `KnowledgeEntryRepositoryInterface` + `MySqlKnowledgeEntryRepository` — CRUD, filters, keyword search, timeline (workspace-scoped ทุก query)
- `Domain/Knowledge/KnowledgeService` — validation ต่อประเภท (config `STATUSES`: business_rule active/deprecated, known_issue open/workaround/resolved, risk open/mitigated/closed, future_enhancement proposed/planned/in_progress/done; known_issue ต้องมี severity, risk ต้องมี probability+impact), unified search (entries + knowledge_articles fulltext + decisions), ADR listing, project knowledge base, knowledge timeline
- `KnowledgeController` + 9 endpoints:
  - `GET/POST /knowledge-entries`, `GET/PATCH/DELETE /knowledge-entries/{id}`
  - `GET /knowledge/search?q=` (unified cross-type)
  - `GET /architecture-decisions?project_id=` (ADR — reuse decision_registers)
  - `GET /projects/{id}/knowledge` (Project Knowledge Base)
  - `GET /knowledge-timeline?project_id=&limit=`
- Permissions: read `knowledge_article.view`, write `knowledge_article.create`/`knowledge_article.update` (seed เดิม)

## 3. Web UI

- `public/app/knowledge.html` [NEW] — unified search box, create form (type-aware fields), registry list พร้อม filters, ADR table, knowledge timeline
- `app.js` — nav เพิ่ม Knowledge

## 4. Reuse (ตามหลักไม่สร้างซ้ำ)

- ADR + Decision Log → `decision_registers` (ADR = category 'architecture')
- Project Knowledge Base → `knowledge_articles` (fulltext index จาก 0024)
- อ้างอิง/เชื่อมโยง → `knowledge_links` (polymorphic, เดิม) + `project_id`/`related_revision_id`/`related_url` ใน entries ใหม่

## 5. Test Results

**Full Suite: 159 tests / 380 assertions — OK**
- KnowledgeM6Test (9): สร้างครบ 4 ประเภท, known_issue ต้องมี severity, risk ต้องมี probability+impact, invalid status ต่อประเภท, unified search ข้ามประเภท, empty keyword rejected, project KB linking, timeline ordering, workspace isolation

## 6. Principles

API First • Configuration over Hardcode (entry types/statuses/policy types เป็น config) • Backward Compatible (additive migration, API เดิมไม่เปลี่ยน) • LINE/GitLab/Telegram constraints ไม่เปลี่ยน
