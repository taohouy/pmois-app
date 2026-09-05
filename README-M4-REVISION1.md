# PMOIS v2 — M4 Implementation Revision 1 (PMO Governance)

**Baseline:** M3 Revision 1 (CTO APPROVED, 99/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/` (Web UI รวม `public/app/governance.html`), `composer.json`
- `docs/m4/CHANGELOG-M4-Revision1.md` — Changelog
- `docs/m4/TEST-RESULTS-M4-R1.txt` — Test Results: **143 tests / 340 assertions — OK**
- `docs/m4/M4-Implementation-Plan-Revision1.md` — Plan (scope mapping 11/11 + capability 5/5)
- `docs/m4/REVIEW-NOTE-M4-R1.md` — Review Note
- `docs/future-enhancements/UNIFIED-PROJECT-TIMELINE.md` — CTO Observation (recorded, non-blocking)

## M4 highlights
- Governance Templates + Versioning (draft→published→superseded, auto-supersede)
- CTO/Dev Working Instructions (audience) + Review/Delivery/Approval Policies (policy_type) — config-driven
- Governance API: filters + `/governance-policies` + `/governance/working-instructions`
- Web UI: `/app/governance.html` + governance binding ใน project detail
- **Auto-governance on project creation** (จาก M1) + **versioning isolation** (ปรับปรุง governance ไม่กระทบ project เดิม — พิสูจน์ด้วย test)
- Migration `0060` (nullable columns — backward compatible)

## Quick verify
```
composer install
php database/migrate.php run        # 0060 ใหม่
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
php -S 0.0.0.0:8080 -t public       # /app/governance.html
```
