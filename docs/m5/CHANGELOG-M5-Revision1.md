# PMOIS v2 — M5 Implementation — Changelog (Revision 1)

**อ้างอิง:** CTO Approval on M4 R1 (100/100) — เริ่ม M5 API Platform ได้ทันที
**Date:** 2026-09-05

---

## 1. Database

- `0061_create_notifications` (+rollback) — notification log (workspace/project/channel/event/message/status/error/sent_at)

## 2. New Source Code

**Telegram Notification Integration (Telegram only ตาม Constraint)**
- `Domain/Notification/NotificationService` — Telegram Bot API best-effort; config `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID` (ไม่ตั้ง = skipped); event templates config (revision_submitted/reviewed/committed, deployment_status_changed); ล้มเหลว log failed ไม่ throw
- `MySqlNotificationRepository` + interface — log ทุกความพยายาม + statusCounts (ใช้ใน metrics)
- Wire ใน RevisionController (submit/commit), RevisionReviewController (review), DeploymentController (transition)

**API Scope Management (backward compatible)**
- `ApiScopeMiddleware` — scope catalog: `read` / `write` / `ai_context` / `*`; path scope map เป็น config
- legacy token (ไม่ระบุ scopes) ผ่านทุก endpoint ตามพฤติกรรม M0 R5 เดิม; token ที่ระบุ scopes ถูกบังคับ → `403 SCOPE_INSUFFICIENT`
- `AuthTokenMiddleware` set `token_scopes` attribute (parse comma string)

**API Rate Limiting**
- `RateLimitMiddleware` — sliding window 60s per identity (token → user → ip); env `RATE_LIMIT_PER_MINUTE` (default 120); `429 RATE_LIMITED` + `X-RateLimit-*` + `Retry-After` headers; in-memory per worker (ไม่เพิ่ม infra — ประกาศข้อจำกัดชัด)

**Audit API**
- `AuditController` + `MySqlDashboardRepository::auditLogs` + service — `GET /audit-logs` (filters entity_type/entity_id/limit, permission `audit_trail.view`)

**Project API Token Management**
- `ApiTokenRepositoryInterface::listByProject` + impl + `ApiTokenController::listForProject` — `GET /projects/{id}/api-tokens`

**API Monitoring + Health**
- `PlatformController::metrics` — `GET /platform/metrics` (admin): active tokens/sessions, audit events today, notifications by status
- `GET /api/v1/health` อัปเกรด: DB connectivity check → `{status: ok|degraded, checks:{api,database}, time}`

## 3. Configuration (env)

`TELEGRAM_BOT_TOKEN` • `TELEGRAM_CHAT_ID` • `RATE_LIMIT_PER_MINUTE` (default 120)

## 4. Documentation

- `docs/api/openapi.yaml` [NEW] — OpenAPI 3.0 spec ครอบคลุม endpoint หลักทุกกลุ่ม
- `docs/api/M5-API-Documentation.md` — platform capabilities (scopes/rate limit/notifications/audit/metrics/health)

## 5. Test Results

**Full Suite: 150 tests / 359 assertions — OK**
- ApiPlatformM5Test (7): Telegram skipped/sent/failed (best-effort ไม่ throw), event template, audit filters + workspace isolation, project token listing, scope mapping

## 6. Principles

API First • Backward Compatible (legacy token ผ่านเหมือนเดิม; ไม่มี breaking change) • Configuration over Hardcode (scope catalog, rate limit, notification templates, event sources) • LINE Login (Web UI) • GitLab (source control) • Telegram (notification)
