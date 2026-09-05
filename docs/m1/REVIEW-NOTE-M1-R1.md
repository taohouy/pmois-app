# PMOIS v2 — Review Note (M1 Implementation Revision 1 — Phase 1 + R6 Phase 1.5)

**สำหรับ:** CTO Review Gate ก่อนเริ่ม Phase 2
**อ้างอิง:** M0 Design Freeze Revision 6 • M1 Plan Revision 3 • CTO Coding Scope letter

---

## 1. สรุปสิ่งที่ส่ง

| CTO Phase 1 item | สถานะ | หลักฐาน |
|---|---|---|
| Database Migration | ✅ 0043–0055 (+ เดิม 0033–0042) — up + rollback + re-apply ผ่านบน MySQL 5.7.36 | `database/migrations/`, §3 ของเอกสารนี้ |
| LINE Login | ✅ OIDC service + routes (`/auth/line*`) + Invitation/Claim (HMAC token 24h, bind `line_user_id` จริง) | `LineLoginService`, `InvitationService`, `LineLoginController`, `ClaimController` |
| Project CRUD | ✅ ผ่าน `ProjectCreationPipeline` (template + workspace defaults + completeness) | `ProjectController::create`, `ProjectCreationPipeline` |
| Project Hierarchy | ✅ Move/ChangeParent/Promote + structure history, routes เดิมไม่เคยมี — เพิ่งถูก register ตอนนี้ | `ProjectStructureController` |
| Milestone Foundation | ✅ CRUD + close/reopen (CTO-only permissions) | `MilestoneController`, `MilestoneService` |
| AI Assignment | ✅ CRUD + revoke + history; agent อ้าง Registry เท่านั้น | `AiAssignmentService` |
| Governance Auto Binding | ✅ Implementation จริงแทน placeholder (explicit → workspace default → latest published) | `GovernanceAutoBindService` |
| GitLab Repository Registry | ✅ Manual registration, GitLab-only validation, ไม่มี auto-provisioning | `RepositoryRegistryService` |
| Permission Seed | ✅ 0042 (เดิม) + 0055 (7 codes ใหม่) | migrations |
| Audit Foundation | ✅ ทุก mutation ใหม่เขียน `audit_trails` ผ่าน `AuditContext` | controllers |
| **R6 Phase 1.5 (Design Freeze)** | ✅ Providers, Team ledger, TechStack, Environments, Dependencies, Releases, Templates, Workspace Defaults, Profile Completeness | `src/Domain/Registry`, `src/Domain/Project` |

## 2. ผลทดสอบ (รันจริง ไม่ใช่ประเมิน)

- **Full suite: 86 tests / 162 assertions — OK** (PHP 8.1.0, MySQL 5.7.36, DB ติดตั้ง migration 0001–0055)
- 23 tests ใหม่สำหรับ R6: dependency cycle/self/workspace rules, release state machine, team ledger↔projection sync, template payload contract, environment secret rejection, tech stack validation
- Migration gate: apply 0001→0055 ✅, rollback 0055→0043 ✅, re-apply ✅
- `php -l` ผ่านทุกไฟล์ใน src/database/tests
- ผลดิบ: `docs/m1/TEST-RESULTS-M1-R1.txt`

## 3. สิ่งที่ CTO ควรทราบ (ความเห็นตรงไปตรงมา)

1. **Commit "M1 Phase 1 Complete" เดิม (5cbd184) ยังรันไม่ได้จริง** — routes ไม่เคยถูก register, controllers เรียก `ApiResponse` ผิด signature, workspace context ไม่เคยถูก set, `InvitationService` พึ่ง lib ที่ไม่ได้ติดตั้ง, `MySqlUserRepository` parse error จาก method ซ้ำ และ `POST /projects` fatal ทันที แพ็กเกจนี้แก้ครบและพิสูจน์ด้วยการรัน test จริง จึงถือเป็น "Phase 1 ที่เสร็จจริง" ตัวแรก
2. **Revision workflow (submit→review→commit) ยังไม่มี** — ตาราง `revisions`/`revision_reviews` มีจาก 0038/0039 แล้ว แต่ services/endpoints เป็น Phase 2 ตามแผน ไม่รวมใน Coding Scope ของ CTO letter
3. **Claim token เปลี่ยนจาก JWT (lcobucci) เป็น HMAC-signed token** — เหตุผล: lib ไม่ได้อยู่ใน composer.json และต้องการหลีกเลี่ยงการเพิ่ม dependency ถ้า CTO ต้องการ JWT มาตรฐานจริง ให้ `composer require lcobucci/jwt` แล้ว swap เฉพาะ token encode/decode (public API ของ service ไม่เปลี่ยน) — **ต้องตั้ง `APP_SECRET` ใน production**
4. **Backfill `ai_consumers.provider_id` = human** ทุกตัวที่มีอยู่ — เป็น operational step ให้ PMO/CTO reclassify หลัง deploy (R6-Q3 ใน R6-12: ยังเก็บ column nullable ไว้, จะ tighten เป็น NOT NULL หลัง reclassify)
5. **Telegram notification** — ยังไม่มีโค้ด (ไม่อยู่ใน Phase 1); เมื่อเริ่มทำจะเป็น Telegram-only ตาม Constraint
6. **Q09 (frontend) ยังเปิด** — ไม่กระทบ Phase 2 backend

## 4. Suggested review focus

- `ProjectCreationPipeline` — ลำดับ 14 steps ตรงกับ R6-05 §1.3, การ resolve defaults, การคืน raw token ครั้งเดียว
- `ProjectDependencyService::assertDependsOnAcyclic` — ancestor walk บน directed edges
- `ProjectTeamAssignmentService` — invariant ledger↔`project_members` (ผ่าน test แล้ว)
- `RepositoryRegistryService` — GitLab-only URL rejection (Constraint #2)
- สิทธิ์ global registries (ai/git providers) — is_platform_admin check ใน controller (ไม่มี role_permissions row) ตาม pattern `workspace.create`

## 5. Gate

หากผ่าน Review นี้ → เริ่ม Phase 2 (Revision workflow + InvitationService hardening + read-only GitLab sync option) ตาม M1 Plan
