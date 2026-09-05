# PMOIS v2 — Review Note (M3 Implementation Revision 1)

**สำหรับ:** CTO Review — M3 Project Management
**อ้างอิง:** CTO Approval on M2 R1 (99/100, เริ่ม M3 + Web UI ควบคู่)

---

## 1. Scope coverage (11/11)

Milestone Management ✅ (M1 + UI) • Revision Management ✅ • CTO Review Workflow ✅ • Project Timeline Management ✅ • Commit Tracking ✅ • Deployment Tracking ✅ (migration 0059) • Release Management ✅ (M1 + เชื่อม deployment) • Project Activity History ✅ • Review API ✅ • Timeline API ✅ • **Web UI** ✅ (5 หน้า)

## 2. Verification evidence

- **Full Suite: 136 tests / 318 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, migrations 0001–0059)
- RevisionWorkflowTest (8): ครอบ state machine + กฎ M0 ทุกข้อ (MILESTONE_NOT_OPEN, REVISION_NOT_APPROVED, dev XOR ai, double review) + PMO timeline row idempotent
- DeploymentTrackingTest (4): state machine + deployed_at/by
- `php -l` ผ่านทุกไฟล์; migration 0059 additive (มี rollback)

## 3. Design decisions ให้ CTO ทราบ

1. **Web UI = static app (vanilla JS) ใต้ `public/app/`** — ใช้ API เดิม + session cookie จาก M1 R2; ไม่มี build tooling/dependency ใหม่ ไม่ปิด Q09 (เป็น MVP เพื่อรับ UX feedback ตาม Observation); ถ้า Q09 ตัดสินใจใช้ SPA framework ภายหลัง UI ชุดนี้เป็น functional reference
2. **Browser redirect ใน LINE callback** — ตรวจจาก `Accept: text/html` เท่านั้น; API clients ยังรับ JSON เดิม (Backward Compatible)
3. **Commit → PMO timeline** — สร้าง `project_status_updates` ด้วย idempotency key `revision-commit-{id}` (กัน duplicate), test_result=failed → overall_status=at_risk
4. **Deployment แยกจาก Release** — deployment อ้าง release_id/environment_id ได้ (optional) เพื่อรองรับ deploy ที่ไม่มี release record
5. **Project Activity History** — audit-based (`entity_type='project'`) — เหตุการณ์เฉพาะประเภท (release/timeline) อยู่ใน dashboard endpoints ตามเดิม

## 4. Manual smoke test สำหรับ Web UI (ใน Review)

```
php -S 0.0.0.0:8080 -t public
```
1. เปิด `/app/index.html` → Login with LINE → redirect กลับมาที่ `/app/projects.html`
2. `/app/dashboard.html` — stats/summaries/portfolio (admin)
3. สร้าง project → เปิด project detail → submit revision
4. `/app/reviews.html` → Approve → กลับไป project → Commit → ตรวจ timeline มี event
5. สร้าง deployment → transition เป็น deployed
6. `POST /auth/logout` ผ่านปุ่ม Logout

## 5. Deliverable

`PMOIS_v2_M3_Implementation_Revision1.zip` — Source, Migration, Tests, Test Results, API Documentation (M3), Plan, Changelog, Review Note, Web UI
