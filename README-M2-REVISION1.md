# PMOIS v2 — M2 Implementation Revision 1 (Portfolio Dashboard)

**Baseline:** M1 Revision 2 (CTO APPROVED WITH OBSERVATION, 98/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/`, `composer.json` — source (vendor ไม่รวม: `composer install`)
- `docs/m2/CHANGELOG-M2-Revision1.md` — Changelog
- `docs/m2/TEST-RESULTS-M2-R1.txt` — Test Results: **124 tests / 286 assertions — OK**
- `docs/api/M2-API-Documentation.md` — Dashboard API Documentation
- `docs/m2/M2-Implementation-Plan-Revision1.md` — Plan
- `docs/m2/REVIEW-NOTE-M2-R1.md` — Review Note
- `docs/future-enhancements/IDENTITY-VERIFICATION-SERVICE.md` — CTO Observation (recorded, non-blocking)

## M2 Scope (10/10)
Portfolio / Workspace / Parent / Project Dashboards, Timeline, Recent Activities,
Progress Summary, Health Summary, Portfolio Statistics — เป็น Dashboard API ทั้งหมด
(API First, read-only, ไม่มี schema change — Backward Compatible)

## Dashboard endpoints
```
GET /api/v1/dashboards/workspace          (workspace.view)
GET /api/v1/dashboards/portfolio          (is_platform_admin)
GET /api/v1/dashboards/progress-summary   (workspace.view)
GET /api/v1/dashboards/health-summary     (workspace.view)
GET /api/v1/dashboards/statistics         (workspace.view)
GET /api/v1/dashboards/recent-activities  (workspace.view)
GET /api/v1/projects/{id}/dashboard       (project.view — + children roll-up)
GET /api/v1/projects/{id}/timeline        (project.view)
```

## Quick verify
```
composer install
php database/migrate.php run          # ไม่มี migration ใหม่ใน M2
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
```
