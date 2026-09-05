# Future Enhancement — Context Exports (M8/M9)

**ที่มา:** CTO Decision on M7 Implementation Revision 1 — Future Enhancement (เก็บไว้ M8/M9)
**สถานะ:** บันทึกไว้ตามมติ — **ยังไม่ดำเนินการใน M7**
**Date:** 2026-09-06

---

## 1. เป้าหมาย

สร้าง **Context สำหรับ AI โดยอัตโนมัติ** จาก Knowledge Center และข้อมูล project แบ่งตามผู้ใช้:

- **AI Context Export** — สำหรับ AI agent (ผ่าน `ai_consumers` + token): governance summary, project status, business rules, known issues, decisions
- **CTO Context Export** — สำหรับ CTO: review queue summary, overdue milestones, at-risk projects, productivity ของทีม
- **Dev Context Export** — สำหรับ Dev: assigned projects, open milestones, known issues, latest decisions/working instructions (audience='dev')

## 2. รูปร่างที่เสนอ

- ต่อยอด `ai_context_exports` (มีอยู่จาก migration 0027 — payload_snapshot JSON) เพิ่ม export_type: `ai_context`, `cto_context`, `dev_context`
- สร้างจาก: Knowledge Center entries + Analytics KPIs + workflow state — trigger ตาม schedule หรือ on-demand endpoint
- ใช้ `NotificationService` (Telegram) แจ้งเมื่อ export พร้อม

## 3. อ้างอิงที่มีอยู่แล้ว (ใช้ต่อได้เลย)

- `ai_context_exports` table + `AiContextAggregationService` (Phase 3)
- Knowledge Center entries (M6) + Analytics read models (M7)
- Telegram notification infra (M5)
