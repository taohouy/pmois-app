# PMOIS v2 — M6 Implementation Revision 1 (Knowledge Center)

**Baseline:** M5 Revision 1 (CTO APPROVED, 100/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/` (Web UI รวม `public/app/knowledge.html`), `composer.json`
- `docs/m6/CHANGELOG-M6-Revision1.md` — Changelog
- `docs/m6/TEST-RESULTS-M6-R1.txt` — Test Results: **159 tests / 380 assertions — OK**
- `docs/m6/M6-Implementation-Plan-Revision1.md` — Plan (scope 10/10)
- `docs/m6/REVIEW-NOTE-M6-R1.md` — Review Note
- `docs/future-enhancements/EXTENDED-HEALTH-CHECKS.md` — CTO Observation (ไว้ M9)

## M6 Scope (10/10)
Business Rules Registry • ADR • Known Issues • Risk Register • Decision Log •
Future Enhancements • Project Knowledge Base • Search API • Knowledge Management UI • Knowledge Timeline

## Knowledge API
```
GET/POST /api/v1/knowledge-entries            (filters: entry_type/project_id/status)
GET/PATCH/DELETE /api/v1/knowledge-entries/{id}
GET /api/v1/knowledge/search?q=               (unified: entries + articles + decisions)
GET /api/v1/architecture-decisions            (ADR — decision_registers)
GET /api/v1/projects/{id}/knowledge           (Project Knowledge Base)
GET /api/v1/knowledge-timeline?project_id=&limit=
```

## Quick verify
```
composer install
php database/migrate.php run        # 0062 ใหม่
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
php -S 0.0.0.0:8080 -t public       # /app/knowledge.html
```
