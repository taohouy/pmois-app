# PMOIS v2 — M3 Implementation Revision 1 (Project Management)

**Baseline:** M2 Revision 1 (CTO APPROVED, 99/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/` (รวม **Web UI** ที่ `public/app/`), `composer.json`
- `docs/m3/CHANGELOG-M3-Revision1.md` — Changelog
- `docs/m3/TEST-RESULTS-M3-R1.txt` — Test Results: **136 tests / 318 assertions — OK**
- `docs/api/M3-API-Documentation.md` — API Documentation (revisions/reviews/deployments/activities + Web UI)
- `docs/m3/M3-Implementation-Plan-Revision1.md` — Plan (Q09 pragmatic decision)
- `docs/m3/REVIEW-NOTE-M3-R1.md` — Review Note + Web UI smoke test

## M3 Scope (11/11)
Milestone / Revision / CTO Review Workflow / Timeline / Commit Tracking / Deployment Tracking /
Release Management / Activity History / Review API / Timeline API / **Web UI**

## New in M3
- Migration `0059_create_project_deployments`
- Revision workflow: submit → CTO approve/reject → commit (PMO timeline auto-update, idempotent)
- Deployment tracking (state machine pending→deployed→rolled_back)
- `GET /projects/{id}/activities`
- Web UI 5 หน้า: login / dashboard / projects / project detail / reviews

## Quick verify
```
composer install
php database/migrate.php run        # 0059 ใหม่
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
php -S 0.0.0.0:8080 -t public       # แล้วเปิด http://localhost:8080/app/index.html
```
