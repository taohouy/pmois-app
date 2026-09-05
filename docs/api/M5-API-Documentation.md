# PMOIS v2 — M5 API Platform Documentation

เอกสารนี้ครอบคลุม platform capabilities ของ M5 — endpoint catalog ทั้งหมดดู `openapi.yaml` + M1–M4 API docs

---

## 1. API Health Check

`GET /api/v1/health` (guest)
```json
{ "status": "ok|degraded", "checks": { "api": true, "database": true }, "time": "..." }
```
`degraded` = DB connect ไม่ได้ (HTTP 200 ยังคง — ให้ load balancer ใช้ body ตัดสิน)

## 2. API Authentication (สรุป)

| วิธี | ใช้โดย | หมายเหตุ |
|---|---|---|
| Bearer `api_tokens.token_hash` | API clients / AI agents | token เก็บ hash เท่านั้น |
| `pmois_session` cookie | Web UI (LINE Login) | M1 R2 security flow |

## 3. API Authorization (สรุป)

`RequiresPermissionMiddleware` → `PermissionResolver` (project override → workspace → role_permissions; `workspace.create` + global registries = is_platform_admin)

## 4. API Scope Management (M5)

- Catalog: `read` (GET ทั่วไป) • `write` (POST/PATCH/PUT/DELETE) • `ai_context` (PMO context endpoints) • `*` (ทุกอย่าง)
- **Backward compatible**: token ไม่ระบุ scopes → ผ่านทุก endpoint (พฤติกรรม M0 R5 เดิม); token ระบุ scopes → จำกัดตาม scope
- สร้าง token พร้อม scopes: `POST /auth/tokens` body `{token_name, scopes: ["read","write"]}` (เก็บ comma-separated เดิม)
- ละเมิด → `403 SCOPE_INSUFFICIENT`
- Path scope พิเศษ (config ใน `ApiScopeMiddleware::PATH_SCOPES`): `/pmo-context`, `/governance/summary`, `/decisions/recent`, `/projects/status` → `ai_context`

## 5. API Rate Limiting (M5)

- ทุก `/api/v1` request นับต่อ identity (token → user → ip)
- Limit: env `RATE_LIMIT_PER_MINUTE` (default 120) — sliding window 60s
- Response headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- เกิน → `429 RATE_LIMITED` + `Retry-After: 60`
- ข้อจำกัด: ตัวนับ in-memory per PHP worker (single-node); multi-node ต้องใช้ shared store (future)

## 6. Telegram Notification (M5 — Telegram only ตาม Constraint)

- Config: `TELEGRAM_BOT_TOKEN` + `TELEGRAM_CHAT_ID` (ไม่ตั้ง = skipped — ระบบปกติ)
- Events (templates config ใน `NotificationService`): `revision_submitted`, `revision_reviewed`, `revision_committed`, `deployment_status_changed`
- Best-effort: ล้มเหลว log `failed` ไม่ throw; log ทุกครั้งในตาราง `notifications` (migration 0061)

## 7. Audit API

| Method | Path | Permission |
|---|---|---|
| GET | `/audit-logs?limit=&entity_type=&entity_id=` | `audit_trail.view` |

คืน audit rows ของ workspace (ใหม่→เก่า) พร้อม before/after JSON, ip, user

## 8. Project API Token Management

| Method | Path | Permission |
|---|---|---|
| GET | `/projects/{project_id}/api-tokens` | `project.view` |

Tokens ที่ผูกกับ project (active + revoked); สร้างผ่าน `POST /auth/tokens` (body มี `project_id` optional), revoke ผ่าน `DELETE /auth/tokens/{id}`

## 9. API Monitoring

`GET /platform/metrics` (is_platform_admin):
```json
{ "generated_at": "...",
  "api_tokens": { "active": 5 },
  "sessions": { "active": 2 },
  "audit_events_today": 41,
  "notifications": { "today": 6, "sent": 5, "failed": 1, "skipped": 0 } }
```

## 10. OpenAPI

`docs/api/openapi.yaml` — OpenAPI 3.0 spec ครอบคลุม endpoint หลักทุกกลุ่ม (ใช้กับ Swagger UI / codegen ได้)
