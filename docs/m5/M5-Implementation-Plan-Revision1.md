# PMOIS v2 — M5 Implementation Plan (Revision 1)

**อ้างอิง:** CTO Approval on M4 Revision 1 (100/100) • M0 R6 Design Freeze • CTO Constraints (LINE / GitLab / Telegram)
**Date:** 2026-09-05

---

## 1. M5 Scope coverage

| Scope item | Deliverable | สถานะ |
|---|---|---|
| REST API Platform | /api/v1 ทั้งหมด (มีตั้งแต่ M1) — M5 เพิ่ม platform capabilities | ✅ |
| Project API Token Management | `GET /projects/{id}/api-tokens` (list per project) + create/revoke เดิม | ✅ |
| API Scope Management | `ApiScopeMiddleware` + scope catalog (read/write/ai_context) — backward compatible | ✅ |
| API Authentication | Bearer api_tokens + PMOIS session cookie (M1 R2) | ✅ (มีอยู่) |
| API Authorization | PermissionResolver + permission codes (มีตั้งแต่ M1) | ✅ (มีอยู่) |
| Telegram Notification Integration | `NotificationService` + Telegram Bot API + notifications log (0061) + wiring ใน workflow | ✅ |
| Audit API | `GET /audit-logs` (filters: entity_type/entity_id/limit) | ✅ |
| API Rate Limiting | `RateLimitMiddleware` (sliding window, `RATE_LIMIT_PER_MINUTE`, headers X-RateLimit-*) | ✅ |
| API Documentation | `docs/api/openapi.yaml` + M5 doc | ✅ |
| API Monitoring | `GET /platform/metrics` (admin): tokens/sessions/audit today/notifications by status | ✅ |
| API Health Check | `GET /api/v1/health` อัปเกรด: DB connectivity check → ok/degraded | ✅ |

## 2. Design decisions

1. **Scope management backward compatible** — M0 R5 กำหนดว่า `api_tokens.scopes` เป็น informational; M5 เพิ่มการบังคับแบบไม่ทำลาย: token ที่ **ไม่ระบุ scopes** (legacy) ผ่านทุก endpoint เหมือนเดิม; token ที่ **ระบุ scopes** ถูกจำกัดตาม scope ที่ประกาศ (`read` / `write` / `ai_context` / `*`) — mapping เป็น config (`ApiScopeMiddleware::PATH_SCOPES` + method mapping)
2. **Rate limiting in-memory per worker** — ไม่เพิ่ม infrastructure (ไม่ใช้ Redis ตาม M0 constraint); เพียงพอสำหรับ single-node องค์กรภายใน; ย้ายไป shared store ภายหลังได้ (interface เดิม)
3. **Telegram best-effort** — ส่งล้มเหลว log status='failed' แล้วไม่ throw (ธุรกรรมหลักไม่พัง); ไม่ตั้ง TELEGRAM env → status='skipped'; event templates เป็น config
4. **Notifications log table (0061)** — log ทุกความพยายามส่งเพื่อ monitoring/retry

## 3. Configuration (env)

```env
TELEGRAM_BOT_TOKEN=...     # ไม่ตั้ง = notification skipped
TELEGRAM_CHAT_ID=...
RATE_LIMIT_PER_MINUTE=120  # default 120 req/min ต่อ token/user
```

## 4. Events ที่ส่ง Telegram (config templates)

`revision_submitted` • `revision_reviewed` • `revision_committed` • `deployment_status_changed` — wire ที่ controller layer หลัง action สำเร็จ (best-effort)

## 5. Files

| File | Role |
|---|---|
| migration `0061_create_notifications` | notification log |
| `Domain/Notification/*` + `MySqlNotificationRepository` | Notification port + Telegram service |
| `Middleware/RateLimitMiddleware` | rate limiting |
| `Middleware/ApiScopeMiddleware` | scope enforcement (backward compatible) |
| `AuthTokenMiddleware` (แก้) | set `token_scopes` attribute |
| `AuditController` + dashboard read model | Audit API |
| `PlatformController` | metrics |
| `ApiTokenController::listForProject` + repo | project token listing |
| `tests/Integration/ApiPlatformM5Test.php` | 7 tests |
