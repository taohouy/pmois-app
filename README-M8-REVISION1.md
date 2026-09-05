# PMOIS v2 — M8 Implementation Revision 1 (Automation Center)

**Baseline:** M7 Revision 1 (CTO APPROVED, 100/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/`, `bin/automation-worker.php` (Background Jobs worker)
- `docs/m8/CHANGELOG-M8-Revision1.md` — Changelog
- `docs/m8/TEST-RESULTS-M8-R1.txt` — Test Results: **177 tests / 433 assertions — OK**
- `docs/m8/M8-Implementation-Plan-Revision1.md` — Plan (job types, retry policy, deployment)
- `docs/m8/REVIEW-NOTE-M8-R1.md` — Review Note
- `docs/future-enhancements/PREDICTIVE-ANALYTICS.md` — CTO Observation (recorded)

## M8 Scope (10/10)
AI Dev Auto Integration • GitLab Integration • Automatic Timeline Update • Automatic Project Update •
Automation Workflow • Automation Queue • Background Jobs • Telegram Automation • Automation API • Automation Management UI

## Job types (handler registry)
timeline_update (idempotent PMO timeline) • project_update (completeness recompute) •
notification (Telegram) • gitlab_sync (read-only URL check) • ai_dev_auto (dispatch task)

## API
```
GET  /api/v1/automation/jobs?status=&job_type=&limit=   (automation.view)
POST /api/v1/automation/jobs                            (automation.manage)
POST /api/v1/automation/jobs/{id}/retry                 (automation.manage)
POST /api/v1/automation/run                             (automation.manage)
POST /api/v1/automation/ai-dev-auto                     (automation.manage)
```

## Background worker (cron)
```
* * * * * php /path/to/pmois-app/bin/automation-worker.php --limit=20
```

## Quick verify
```
composer install
php database/migrate.php run        # 0063/0064 ใหม่
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
php bin/automation-worker.php       # รัน due jobs
php -S 0.0.0.0:8080 -t public       # /app/automation.html
```
