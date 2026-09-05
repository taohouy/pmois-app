# PMOIS v2 — M8 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M7 Revision 1 (100/100) • M0 R6 Design Freeze • CTO Constraints (LINE / GitLab / Telegram)
**Date:** 2026-09-06

---

## 1. M8 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| AI Dev Auto Integration | job type `ai_dev_auto` — dispatch task ผ่าน Telegram channel ให้ AI agent; agent ส่ง revision กลับผ่าน API เดิม (dev_ai_consumer_id) | ✅ |
| GitLab Integration | job type `gitlab_sync` — read-only connectivity check ต่อ repository URL (ไม่เขียน GitLab ตาม M0 stance) | ✅ |
| Automatic Timeline Update | workflow `onRevisionCommitted` → queue `timeline_update` job (idempotent ผ่าน ProjectStatusUpdater) | ✅ |
| Automatic Project Update | workflow `onMilestoneClosed` → queue `project_update` job (recompute profile completeness) | ✅ |
| Automation Workflow | `AutomationWorkflowService` triggers + `HANDLERS` registry (config) | ✅ |
| Automation Queue | `automation_jobs` (0063) + claim-due / attempts / backoff / max_attempts | ✅ |
| Background Jobs | `bin/automation-worker.php` (CLI, run-once สำหรับ cron หรือ `--watch=N`) + `POST /automation/run` | ✅ |
| Telegram Automation | job type `notification` + `NotificationService::sendMessage` | ✅ |
| Automation API | `GET/POST /automation/jobs`, `POST /automation/jobs/{id}/retry`, `POST /automation/run`, `POST /automation/ai-dev-auto` | ✅ |
| Automation Management UI | `/app/automation.html` — queue monitor (filters), AI Dev Auto dispatch, GitLab sync trigger, run-due, retry | ✅ |

## 2. Job types & handlers (config: `AutomationJobRunner::HANDLERS`)

| job_type | handler | อ้างอิง |
|---|---|---|
| `timeline_update` | สร้าง PMO timeline row จาก revision committed (idempotent) | `ProjectStatusUpdater` |
| `project_update` | recompute `profile_completeness_percent` | completeness calculator (M1 R6) |
| `notification` | ส่ง Telegram ข้อความจาก payload | `NotificationService::sendMessage` |
| `gitlab_sync` | HTTP GET ตรวจ repository_url (GitLab only) — บันทึก http status | `repositories` registry |
| `ai_dev_auto` | dispatch task → Telegram; AI agent ส่ง revision กลับผ่าน API | `dev_ai_consumer_id` flow (M1/M3) |

## 3. Retry policy

- ทุก execution เพิ่ม `attempts`; ล้มเหลวและ `attempts < max_attempts` → กลับไป `queued` (backoff 60s × attempts)
- ครบ `max_attempts` (default 3) → `failed` (พร้อม last_error) — retry ด้วยมือผ่าน `POST /automation/jobs/{id}/retry` (reset attempts)
- Worker: single-node (ตามข้อจำกัด M5 rate limiter) — multi-worker/locking เป็น future work

## 4. Deployment (background jobs)

```bash
# cron ทุกนาที
* * * * * php /path/to/pmois-app/bin/automation-worker.php --limit=20
```

## 5. CTO Observation (recorded — ภายหลัง)

Predictive Analytics / Early Warning / Project Forecast / AI-assisted Portfolio Insights — บันทึก `docs/future-enhancements/PREDICTIVE-ANALYTICS.md` (ไม่ทำใน M8 ตามมติ)
