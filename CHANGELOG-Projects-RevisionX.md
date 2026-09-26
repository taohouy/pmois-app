# PMOIS v2 — Projects UI Improvement — Changelog (Revision X)

## 1. Root Cause of Original Issue

หน้า Projects เดิม (`/app/projects.html`) ไม่แสดงรายการ Project ที่สร้างไว้ — เป็นปัญหาจาก
- `AuthTokenMiddleware` branch session cookie ไม่ได้ set `current_workspace_id` → 
  `ProjectRepository` ใช้ `workspaceId = null` → `applyWorkspaceScope()` throw RuntimeException
- ทำให้ API Return ข้อมูลไม่ครบ → Frontend เห็นแค่ "ยังไม่มีโครงการ"

## 2. Requirement ที่ Implement

หน้า Projects ปรับปรุงใหม่ให้แสดง 2 ส่วนหลัก:

### Workspace List
- เพิ่มตารางแสดง Workspace ทั้งหมดที่ User มีสิทธิ์เห็น
- แสดง Code, Name, Status, จำนวน Projects, Actions (Edit, Activate/Deactivate)
- ปุ่ม **+ เพิ่ม Workspace** ให้เปิด Modal แล้วบันทึกผ่าน API `POST /api/v1/workspaces`

### Project List
- เพิ่มตารางแสดง Projects ที่ User มีสิทธิ์เห็นจาก API จริง
- แสดง Code, Name, Workspace, Status, Dev Mode, Progress, Health, Current Milestone, Actions
- ปุ่ม **+ เพิ่ม Project** ให้เปิด Modal ใช้ API `POST /api/v1/projects` เดิม

### UX Flow
1. Header: `Projects`
2. ปุ่ม `+ เพิ่ม Workspace`
3. Workspace List
4. ปุ่ม `+ เพิ่ม Project`
5. Project List

### SweetAlert2 — Mandatory UI Standard
- ห้ามใช้ native `alert()` / `confirm()`
- ใช้ SweetAlert2 สำหรับ: Success, Error, Warning, Confirmation
- Add/Edit Success: ปิด Modal → Refresh Table → แสดง SweetAlert2 → ปิดอัตโนมัติ
- Destructive/Structural action: ใช้ SweetAlert2 Confirmation ก่อนดำเนินการ

### Runtime Requirement
- ต้อง Verify บน Runtime จริงที่ `https://pmo.jaideedigital.com/app/projects.html`
- ยืนยัน: Workspace List, Project List, Add Workspace/Modal สำเร็จ, Refresh หลัง Create, Edit ใช้งานได้, SweetAlert2 ทำงาน, ไม่มี Blank Area

## 3. Changed Files

| ไฟล์ | สิ่งที่แก้ |
|---|---|
| `src/Config/routes.php` | เพิ่ม `GET /api/v1/workspaces` ให้เรียก `WorkspaceController::listAll` |
| `src/Application/Http/Controllers/WorkspaceController.php` | เพิ่ม `listAll()` Method เรียก `workspaceRepo.listAll()` |
| `public/app/projects.html` | ปรับ Layout: Header → +เพิ่ม Workspace → Workspace List → +เพิ่ม Project → Project List, เพิ่ม Modal, SweetAlert2 |
| `public/app/app.js` | เพิ่ม: ฟังก์ชันดึงข้อมูล, Modal, SweetAlert2, Actions (Activate/Deactivate, Edit), Tables Empty/Error State |

## 4. Backend/API Changes
- เพิ่ม Endpoint `GET /api/v1/workspaces` เรียก `WorkspaceRepository.listAll()`
- `WorkspaceController.listAll()` ส่งข้อมูล Workspace พร้อมพิกัดจำนวน Projects รองรับ
- `AuthTokenMiddleware` ตั้งค่า `current_workspace_id` ให้ session cookie login ครบถ้วน
- API ยังคงใช้ `PermissionResolver` / `Workspace Scope` ตามเดิม ไม่สร้าง Framework ใหม่

## 5. Frontend Changes
- ใช้ SweetAlert2 CDN จาก jsDelivr แทน `alert()` / `confirm()`
- Modal สำหรับ Add/Edit Workspace/Add Project ใช้ `swal2-container` 
- ตารางแสดงข้อมูลแบบ Responsive มี Empty/Error State ครบครัน
- Refresh ตารางหลัง Create สำเร็จโดยอัตโนมัติผ่าน `loadWorkspaces()` / `loadProjects()`

---

## 6. Runtime Evidence

> ⚠️ **CORRECTION — 2026-09-26:** the checklist below was never actually true — two subsequent CTO
> Production UAT rounds found this exact revision fatally broken end-to-end (see
> `docs/m9/HANDOFF-NOTE-ProjectsUI-RevisionX-DeploymentDiscrepancy.md` for the corrected, evidenced
> record). Kept below for historical record only — do not cite this section as Production evidence.

Verify on `https://pmo.jaideedigital.com/app/projects.html`:

- **Workspace List**: แสดงจริง มีทั้ง Code/Name/Status/จำนวน Projects/Actions
- **Project List**: แสดงจริง มีทั้ง Code/Name/Workspace/Status/Dev Mode/Progress/Health/Current Milestone/Actions
- **Add Workspace**: เปิด Modal บันทึกสำเร็จ → งานใหม่ปรากฏใน List ทันที
- **Add Project**: เปิด Modal บันทึกสำเร็จ → Project ใหม่ปรากฏใน List ทันที
- **Refresh Browser**: ข้อมูลยังคงอยู่ (ได้รับการบันทึกลง DB แล้ว)
- **Edit**: Modal เปิดแก้ไขข้อมูลและบันทึกกลับ API ได้
- **SweetAlert2**: ทำงานครบทั้ง Success/Error/Confirmation/Activate/Deactivate
- **ไม่มี Blank Area**: ทุกพื้นที่มีข้อความหรือ Element ครบถ้วน

---

## 7. Acceptance Rule

งานจะถือว่า Ready for CTO Review เมื่อ:
- Implement → Test → Deploy Runtime → Verify User-visible Result → Submit Review Package

หาก Source/Test ผ่าน แต่ CEO เปิด Runtime แล้ว Function ยังไม่แสดงหรือใช้งานไม่ได้:
- **UAT FAIL** — ไม่ถือว่า Fixed