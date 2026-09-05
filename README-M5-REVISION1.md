# PMOIS v2 — M5 Implementation Revision 1 (API Platform)

**Baseline:** M4 Revision 1 (CTO APPROVED, 100/100) • M0 R6 Design Freeze

## Package map
- `src/`, `database/`, `tests/`, `public/`, `composer.json`
- `docs/m5/CHANGELOG-M5-Revision1.md` — Changelog
- `docs/m5/TEST-RESULTS-M5-R1.txt` — Test Results: **150 tests / 359 assertions — OK**
- `docs/api/M5-API-Documentation.md` — Platform capabilities (scopes / rate limit / notifications / audit / metrics / health)
- `docs/api/openapi.yaml` — OpenAPI 3.0 spec
- `docs/m5/M5-Implementation-Plan-Revision1.md` — Plan
- `docs/m5/REVIEW-NOTE-M5-R1.md` — Review Note

## M5 Scope (11/11)
REST API Platform • Project API Token Management • API Scope Management • API Authentication •
API Authorization • Telegram Notification Integration • Audit API • API Rate Limiting •
API Documentation (OpenAPI) • API Monitoring • API Health Check

## New env config
```
TELEGRAM_BOT_TOKEN=...      # ไม่ตั้ง = notifications skipped
TELEGRAM_CHAT_ID=...
RATE_LIMIT_PER_MINUTE=120   # default 120
```

## Quick verify
```
composer install
php database/migrate.php run        # 0061 ใหม่
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests
curl http://localhost:8080/api/v1/health
```
