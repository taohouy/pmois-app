# PMOIS v2 — M8 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M7 R1 (100/100) — เริ่ม M8 Automation Center ได้ทันที
**Date:** 2026-09-06

---

## 1. Database

- `0063_create_automation_jobs` (+rollback) — queue table (job_type/status/attempts/max_attempts/scheduled_at/payload JSON/result/last_error)
- `0064_seed_automation_permission_codes` (+rollback) — `automation.manage` (ADMIN/CTO), `automation.view` (ADMIN/CTO/PMO_REVIEWER)

## 2. New Source Code

**Queue + Runner**
- `Domain/Automation/AutomationJob` + `AutomationJobRepositoryInterface` + `MySqlAutomationJobRepository` — create/list/claimDue/markCompleted/markFailedWithRetry/requeue
- `Domain/Automation/AutomationJobRunner` — claim due jobs → handler registry (`HANDLERS` config) → complete/failed-with-backoff (60s × attempts, max 3)
- Handlers: `timeline_update` (PMO timeline idempotent), `project_update` (completeness recompute), `notification` (Telegram), `gitlab_sync` (read-only URL check), `ai_dev_auto` (dispatch task ผ่าน Telegram)

**Workflows (automatic triggers)**
- `AutomationWorkflowService` — `onRevisionCommitted` → timeline_update, `onMilestoneClosed` → project_update, `onDeploymentStatusChanged` → notification; best-effort enqueue (best-effort: ล้มเหลวไม่กระทบธุรกรรมหลัก)
- Wire แล้วใน: RevisionController (commit), MilestoneController (close — เพิ่ม milestone lookup เพื่อได้ project_id), DeploymentController (transition)

**Background Jobs**
- `bin/automation-worker.php` — CLI worker: run-once (cron `* * * * *`) หรือ `--watch=N`; `--limit=N`

**API**
- `GET /automation/jobs?status=&job_type=&limit=` (automation.view)
- `POST /automation/jobs` (automation.manage) — enqueue manual job (job_type/payload/scheduled_at)
- `POST /automation/jobs/{id}/retry` (automation.manage)
- `POST /automation/run` (automation.manage) — run due jobs
- `POST /automation/ai-dev-auto` (automation.manage) — AI Dev Auto dispatch

**NotificationService** — เพิ่ม `sendMessage()` (ข้อความอิสระ, best-effort เดิม)

## 3. Web UI

- `public/app/automation.html` [NEW] — queue monitor (status/type filters, retry button), AI Dev Auto dispatch form, GitLab sync trigger, Run-due button
- `app.js` — nav เพิ่ม Automation

## 4. Test Results

**Full Suite: 177 tests / 433 assertions — OK**
- AutomationM8Test (10): enqueue/queued default, invalid job type, scheduled-future ไม่ถูก claim, timeline_update handler → PMO timeline row, project_update → completeness persisted, gitlab_sync ผ่าน (HTTP 200) / ล้มเหลว (HTTP 500 → backoff → failed ครบ max_attempts → retry reset), AI Dev Auto dispatch ผ่าน Telegram, workflow triggers ครบ 3 แบบ

## 5. CTO Observation (recorded)

Predictive Analytics / Early Warning / Project Forecast / AI-assisted Portfolio Insights — `docs/future-enhancements/PREDICTIVE-ANALYTICS.md` (ไม่ทำใน M8 ตามมติ)

## 6. Principles

API First • Configuration over Hardcode (handler registry, retry policy, event templates) • Backward Compatible (additive migrations; workflow triggers เป็น best-effort ไม่เปลี่ยนพฤติกรรมเดิม) • LINE/GitLab/Telegram constraints ไม่เปลี่ยน
