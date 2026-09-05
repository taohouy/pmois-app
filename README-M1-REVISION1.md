# PMOIS v2 — M1 Implementation Revision 1 (Phase 1 + R6 Phase 1.5)

**Baseline:** M0 Design Freeze Revision 6 • CTO Coding Scope: Phase 1
**Deliverable for:** CTO Review Gate (before Phase 2)

## Package map
- `src/`, `database/`, `tests/`, `public/`, `composer.json` — source code (vendor ไม่รวม: `composer install`)
- `docs/m1/CHANGELOG-M1-Revision1.md` — Changelog (รวมรายการ bug ของ commit Phase 1 เดิมที่แก้)
- `docs/m1/TEST-RESULTS-M1-R1.txt` — Test Result (86 tests / 162 assertions — OK)
- `docs/api/M1-API-Documentation.md` — API Documentation (endpoints + permission + payload)
- `docs/m1/DEPLOYMENT-NOTES-M1-R1.md` — Deployment Note (.env, migration steps, smoke test)
- `docs/m1/REVIEW-NOTE-M1-R1.md` — Review Note (สิ่งที่ CTO ควรโฟกัส)
- `docs/m1/M1-Implementation-Plan-Revision4.md` — Updated plan (R6 baseline + constraints)
- `M0-Design/Revision6/` — Design Freeze baseline (Source of Truth)

## Design constraints (mandatory, per CTO)
1. LINE Login only 2. GitLab only 3. Notification: Telegram only (future)

## Quick verify
```
composer install
php database/migrate.php run
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
```
