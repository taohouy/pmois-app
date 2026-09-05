# PMOIS v2 — M7 Implementation Revision 1 (Portfolio Analytics)

**Baseline:** M6 Revision 1 (CTO APPROVED, 100/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/` (Web UI รวม `public/app/analytics.html`), `composer.json`
- `docs/m7/CHANGELOG-M7-Revision1.md` — Changelog
- `docs/m7/TEST-RESULTS-M7-R1.txt` — Test Results: **167 tests / 406 assertions — OK**
- `docs/m7/M7-Implementation-Plan-Revision1.md` — Plan (KPI definitions)
- `docs/m7/REVIEW-NOTE-M7-R1.md` — Review Note
- `docs/future-enhancements/CONTEXT-EXPORTS.md` — CTO Observation (M8/M9)

## M7 Scope (10/10)
Portfolio Analytics Dashboard • KPI Dashboard • Dependency Graph (+leaders) • Workspace Analytics •
Project Health Analytics (+at-risk signals) • Milestone Analytics (+trend) •
CTO/Dev Productivity Metrics • Reports • Charts (SVG) • Analytics API

## Analytics API
```
GET /api/v1/analytics/kpis            (workspace.view)
GET /api/v1/analytics/milestones      (workspace.view)
GET /api/v1/analytics/productivity    (workspace.view)
GET /api/v1/analytics/health          (workspace.view)
GET /api/v1/analytics/dependencies    (workspace.view)
GET /api/v1/analytics/workspace       (workspace.view)
GET /api/v1/analytics/report          (workspace.view)
GET /api/v1/analytics/portfolio       (is_platform_admin)
```

## Quick verify
```
composer install
php database/migrate.php run          # ไม่มี migration ใหม่ใน M7
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
php -S 0.0.0.0:8080 -t public         # /app/analytics.html
```
