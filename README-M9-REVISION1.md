# PMOIS v2 — M9 Implementation Revision 1 (Production Hardening / UAT / Go-Live)

**Baseline:** M8 Revision 1 (CTO APPROVED, 100/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/` (migrations คงไว้ใน repo), `deploy/` (**Consolidated Installer + Rollback + .env example**), `bin/` (worker + validation tools), `tests/`, `public/`
- `docs/production/INSTALLATION-GUIDE.md` — Deployment Guide (7 ขั้นตอน)
- `docs/production/ROLLBACK-GUIDE.md` — Rollback Guide (DB + app)
- `docs/production/OPERATIONS-GUIDE.md` — Operations Guide (cron/monitoring/users/troubleshooting)
- `docs/production/SECURITY-REVIEW-M9.md` / `PERFORMANCE-REVIEW-M9.md` — Reviews
- `docs/production/RELEASE-NOTES.md` — Release Notes
- `docs/m9/CHANGELOG-M9-Revision1.md`, `TEST-RESULTS-M9-R1.txt`, `REVIEW-NOTE-M9-R1.md`
- `docs/future-enhancements/` — Observation สะสมทั้งหมด (6 รายการ)

## Deployment (ห้ามรัน migration ทีละไฟล์)
```
mysql ... pmois < deploy/PMOIS_v2_Database_Install.sql    # ไฟล์เดียว
php bin/create-admin.php admin@example.com
php bin/admin-claim-url.php admin@example.com --base-url=https://...
php bin/validate-integrations.php                          # ALL CHECKS PASSED = พร้อม UAT
```

## Validation ที่ทำจริง (รายละเอียดใน TEST-RESULTS-M9-R1.txt)
1. Fresh install → 45 ตาราง + seeds + admin ✅
2. Full regression 177 tests / 433 assertions บน installed DB ✅
3. Backup → drop → restore → 45/45 ✅
4. Rollback → 0 ตาราง → re-install → 45/45 ✅
5. validate-integrations → DB/Storage/Automation ✅ (LINE/GitLab connectivity ต้องรันใน production network)
