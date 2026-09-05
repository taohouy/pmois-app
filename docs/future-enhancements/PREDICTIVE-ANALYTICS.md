# Future Enhancement — Predictive Analytics & AI Insights

**ที่มา:** CTO Decision on M8 Implementation Revision 1 — Future Enhancement (เก็บไว้ภายหลัง)
**สถานะ:** บันทึกไว้ตามมติ — **ยังไม่ดำเนินการใน M8**
**Date:** 2026-09-06

---

## 1. เป้าหมาย

ยกระดับ Portfolio Analytics (M7) จาก descriptive → predictive

## 2. รายการ

| Feature | แนวทาง |
|---|---|
| Predictive Analytics | ทำนายวันเสร็จโครงการจาก velocity (committed revisions/สัปดาห์) + open milestones |
| Early Warning | แจ้งเตือนอัตโนมัติผ่าน Telegram เมื่อ signal รวมเกิน threshold (overdue + red + failed deployments) — ต่อยอด Automation Queue |
| Project Forecast | Monte-carlo/linear forecast ต่อ milestone จาก historical trend (M7 monthly trend) |
| AI-assisted Portfolio Insights | ใช้ Context Exports (M8/M9 enhancement) ส่งให้ AI สรุป insight ต่อ portfolio — ต่อยอด ai_context_exports |
