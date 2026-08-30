# PMOIS v2 — Proposed Technology Stack with Rationale (M0 Item 19)

**Revision 1** — corrects factual errors against the real `composer.json` (previous draft claimed PHP 8.3, Doctrine DBAL, Guzzle, Nyholm PSR-7, Pest — none of these are actually installed). Restructured into **Required / Existing / Proposed-Future**, per CTO Review §7: optional infrastructure must not be presented as an M0/M1 dependency.

## 1. Required (explicit in the brief — not optional)

| Item | Requirement source |
|---|---|
| MySQL 8.0+ | "Database: MySQL" (brief §2) |
| LINE Login (OIDC) | "Authentication สำหรับ Web UI: LINE Login เท่านั้น" (brief §2, §13) |
| REST API | "API: REST API" (brief §2) |
| GitLab as primary repo provider | "Repository Provider หลัก: GitLab" (brief §2) |

## 2. Existing (already in the codebase today — verified against `composer.json` and source)

| Component | Actual version/detail | Source |
|---|---|---|
| Language | PHP `^8.1` | `composer.json` |
| Framework | `slim/slim ^4.12` | `composer.json` |
| PSR-7 implementation | `slim/psr7 ^1.6` | `composer.json` (not Nyholm — correction) |
| DI container | `php-di/php-di ^7.0` | `composer.json`, used in `AuthTokenMiddleware` |
| Database access | Raw `PDO` (MySQL), no ORM | `BaseRepository.php` — workspace-scoped repository pattern |
| Test framework | `phpunit/phpunit ^10.0` (dev only) | `composer.json` (no Pest — correction) |
| Response envelope | Custom `ApiResponse` class (`success/data/error/meta`) | `src/Application/Http/Responders/ApiResponse.php` |
| Auth/permission | `AuthTokenMiddleware` + `PermissionResolver` + `RequiresPermissionMiddleware` | `src/Application/Middleware/`, `src/Domain/Identity/` |

**No change is proposed to any of the above** — M0 additions (new tables, new controllers/services for revisions, milestones, repositories, AI assignment, LINE login) are built using this exact same stack: PHP 8.1+/Slim 4/raw PDO/`ApiResponse`, following existing patterns (`BaseRepository`, `RequiresPermissionMiddleware`).

## 3. Required New Addition for M0 (minimal, justified)

| Component | Why required (not optional) |
|---|---|
| A LINE Login OIDC client (e.g. a small PHP OIDC library, or hand-rolled using Guzzle/cURL) | LINE Login is explicitly mandated; some HTTP client is needed to call LINE's token/userinfo endpoints. Choice of library (raw cURL vs. a Guzzle dependency) is an implementation detail for M1, not decided here — adding Guzzle is a one-line `composer require` if chosen, not an architectural commitment. |
| Web frontend framework | The brief requires a Web UI; no frontend technology exists in the repo today (this is a backend-only Slim API project currently). A concrete choice (e.g. React, or server-rendered PHP templates) is needed before M1 UI work starts — **this is a decision to make, not yet a "proposal being pushed"**. See Section 4 for options without pre-selecting one as mandatory. |

## 4. Frontend Approach — Options, Not a Mandated Choice

The original draft mandated React + Vite + Tailwind + Radix + TanStack Query + Zustand as if settled. Per CTO Review §7, this revision presents it as an **option to decide**, alongside the simpler alternative that fits a Slim-only backend:

| Option | Trade-off |
|---|---|
| **A. Server-rendered PHP views** (Twig/Plates + Slim, no separate frontend build) | Fewer moving parts, matches "minimum architecture necessary" instruction; slower to build rich interactive screens (Milestone/Review Console, drag-drop hierarchy) |
| **B. Separate SPA (React/Vue + Vite)** | Better fit for the 30+ screen, real-time-feeling UI described in M0 Item 12; adds a second toolchain/deployment artifact |

**No selection is made in this revision.** This is called out as an open question for CTO decision in `15-Risks-and-Open-Questions.md` (Q09), not silently defaulted to React.

## 5. Explicitly Marked as Optional / Future Proposal (NOT an M0/M1 dependency)

Per CTO Review §7/§8, the following — all present in the original draft — are downgraded to "candidate for later, if a concrete need arises":

| Item | Why it is not baseline |
|---|---|
| Redis | No caching/session requirement has been confirmed to need it yet; PHP native sessions are sufficient for M0 LINE-login sessions |
| Kubernetes / ECS / Fargate | No hosting decision has been made; current deployment target is unspecified |
| Cloudflare (WAF/CDN) | No hosting/traffic requirement stated |
| HashiCorp Vault / AWS Secrets Manager | Environment variables are sufficient for M0 secret storage (`repositories.credential_reference` points at one) |
| OpenTelemetry / Prometheus / Grafana / Loki | No observability requirement stated for M0 |
| Real-time infrastructure (Socket.io, Redis Pub/Sub) | No real-time requirement confirmed; Timeline/Activity screens can be built with plain polling/refetch for M0 |
| S3-compatible storage | `attachments.storage_type` already supports `'local'` and `'s3'` as an enum choice — `'local'` is sufficient for M0; S3 remains available without any schema change when actually needed |

None of these block M0 sign-off or M1 implementation start.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — corrected against real composer.json, infrastructure reclassified as optional*
*Date: 2026-08-30*