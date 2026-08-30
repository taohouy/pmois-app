# PMOIS v2 — System Architecture Design (M0)

**Revision 2 note:** Sections 8–12 rewritten to remove fixed numeric targets, mandatory infrastructure (Redis/Socket.io/Docker), and `/api/v2` versioning language that contradicted the Revision 1 baseline established in `04-API-Endpoint-Design.md`, `16-Proposed-Technology-Stack-with-Rationale.md`, and `15-Risks-and-Open-Questions.md`. See those files for the authoritative decisions.

## 1. Overview

PMOIS v2 is a **Project Management Office Information System** serving as the centralized project portfolio hub for CEO, CTO, and Dev teams. It supports three development modes: Manual, AI Assisted, and AI Dev Auto.

### Core Principles
- **Single Source of Truth**: Web UI and REST API share the same data and business rules
- **Minimal CEO Input**: CEO creates projects with minimal fields; technical details added progressively by CTO/Dev
- **Centralized Governance**: PMOIS is the governance source; no governance files copied to repositories
- **AI-First Design**: Human and AI agents treated as first-class team members
- **Progressive Profile**: Project profile completeness tracked separately from project progress

---

## 2. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            PMOIS v2 SYSTEM                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                   │
│  │   CEO/       │    │   CTO/Dev    │    │   AI Agents  │                   │
│  │   PMO        │    │   (Web UI)   │    │   (API)      │                   │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘                   │
│         │                   │                   │                            │
│         └───────────────────┼───────────────────┘                            │
│                             ▼                                                │
│              ┌──────────────────────────────┐                               │
│              │      API Gateway /           │                               │
│              │      Load Balancer           │                               │
│              └──────────────┬───────────────┘                               │
│                             ▼                                                │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                      APPLICATION LAYER                                │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐    │  │
│  │  │  Project    │ │ Governance  │ │ Repository  │ │  Team/AI    │    │  │
│  │  │  Registry   │ │  Engine     │ │  Registry   │ │  Assignment │    │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘    │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐    │  │
│  │  │ Milestone/  │ │   Audit &   │ │   Token     │ │  Web UI     │    │  │
│  │  │  Delivery   │ │  Security   │ │  Management │ │  Controllers│    │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘    │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                             ▼                                                │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                       DOMAIN LAYER                                    │  │
│  │  Project │ Workspace │ Governance │ Repository │ Team │ Milestone  │  │
│  │  Audit   │ Token     │ AI Context │ Risk       │ Decision           │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                             ▼                                                │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                    INFRASTRUCTURE LAYER                               │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐    │  │
│  │  │   MySQL     │ │   GitLab    │ │   LINE      │ │   File      │    │  │
│  │  │  (Primary)  │ │   API       │ │   Login     │ │   Storage   │    │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘    │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Application Layer Components

### 3.1 Project Registry Service
- **Responsibility**: Project lifecycle, hierarchy management, portfolio structure
- **Key Operations**: Create, Read, Update, Move, Promote, Archive
- **Business Rules**: Project ID immutability, structure history, audit logging

### 3.2 Governance Engine
- **Responsibility**: Central governance template management, versioning, project binding
- **Templates**: CTO/Dev Working Instructions, Review/Revision/Commit Rules, Delivery/UAT/Release Rules
- **Features**: Version control, baseline inheritance, API access for CTO/Dev

### 3.3 Repository Registry
- **Responsibility**: GitLab repository metadata management
- **Multi-repo Support**: One project → multiple repositories
- **Security**: No plain-text secrets; token-based GitLab integration

### 3.4 Team/AI Assignment Service
- **Responsibility**: Human and AI agent registry, role assignment, history tracking
- **Roles**: CEO (single), CTO (multiple), Dev (multiple), Viewer
- **AI Agents**: ChatGPT, Codex, Claude, Claude Code, Gemini, Custom Agents

### 3.5 Milestone/Delivery Workflow Engine
- **Responsibility**: Revision → Review → Commit → Delivery → Timeline update
- **State Machine**: Enforces governance workflow, prevents unauthorized status changes
- **API**: Dev submits delivery updates; CTO reviews/approves; PMO updates portfolio

### 3.6 Token & Authorization Service
- **Responsibility**: Project/Agent scoped tokens for authentication + identity binding
- **Authentication**: Project/Agent scoped tokens via `api_tokens.token_hash` (existing)
- **Authorization**: `PermissionResolver::can()` → `project_members` (override) → `workspace_members` (fallback) → `role_permissions` (existing)
- **Token metadata**: `scopes` column is informational only (not authorization authority); rotation manual, revocation supported, audit log, last-used tracking (existing `api_tokens` behaviour)

---

## 4. Data Flow Patterns

### 4.1 Project Creation Flow (CEO)
```
CEO (Web UI) → POST /api/projects → Project Registry → 
  Generate Project ID → Create Structure History → 
  Apply Governance Baseline → Return Project → 
  Notify CTO/Dev
```

### 4.2 CTO Review → Commit → PMO Update Flow
```
Dev (API) → POST /api/v1/revisions → 
  Validate Permission (revision.create) → 
  Create Revision (Pending) → 
  CTO (Web UI/API) → POST /api/reviews → 
  Validate Permission (revision.review) → 
  Approve/Reject → 
  If Approved: Update Milestone, Progress, Timeline → 
  Audit Log → Notify Stakeholders
```

### 4.3 Project Structure Change Flow
```
CEO/PMO (Web UI) → PATCH /api/projects/{id}/structure → 
  Validate Permission (project.structure.update) → 
  Record Structure History (from/to/workspace/parent) → 
  Update Project Hierarchy → 
  Optionally Move Child Projects → 
  Audit Log → Return Updated Structure
```

---

## 5. Technology Stack Proposal

**Revision 1 note:** the table previously here contained factual errors (PHP 8.3, Doctrine DBAL, Guzzle-style claims) not matching the real `composer.json`, and presented optional infrastructure (Redis, Kubernetes, OpenTelemetry, Socket.io) as if already decided. It has been removed from this document to avoid duplicate/conflicting copies. **See `16-Proposed-Technology-Stack-with-Rationale.md` (Revision 1) for the corrected, single source of truth** — Required / Existing (verified against `composer.json`) / Proposed-Future, with the frontend framework explicitly left as an open decision (Q09 in `15-Risks-and-Open-Questions.md`) rather than pre-selected.

---

## 6. Deployment Architecture (illustrative future-scale target — not an M0/M1 requirement)

**Revision 1 note:** Cloudflare, Kubernetes/ECS, and Redis shown in the diagram/table below (this section and Section 8) are **optional future-scale options**, not selected or required technology — no hosting decision has been made yet. This is kept only to illustrate a *possible* end-state at high scale; M0/M1 has no dependency on any of it. See `16-Proposed-Technology-Stack-with-Rationale.md` §5 for the authoritative "not required" list.

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Internet  │────▶│  Cloudflare │────▶│  Load       │
│             │     │  (WAF, CDN) │     │  Balancer   │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               ▼
                    ┌─────────────────────────────────────┐
                    │         Kubernetes / ECS            │
                    │  ┌─────────┐  ┌─────────┐           │
                    │  │ PMOIS   │  │ PMOIS   │  ...      │
                    │  │ API Pod │  │ API Pod │           │
                    │  └─────────┘  └─────────┘           │
                    │  ┌─────────┐  ┌─────────┐           │
                    │  │ PMOIS   │  │ PMOIS   │  ...      │
                    │  │ Web Pod │  │ Web Pod │           │
                    │  └─────────┘  └─────────┘           │
                    └─────────────────────────────────────┘
                                               ▼
                    ┌─────────────┐     ┌─────────────┐
                    │  MySQL      │     │  Redis      │
                    │  (Primary)  │     │  (Cache/    │
                    │             │     │   Sessions) │
                    └─────────────┘     └─────────────┘
```

---

## 7. Security Architecture

### 7.1 Authentication Flow (LINE Login) — Fail-Closed Architecture

**Invitation / Account Claim Flow** (first-time user, before first LINE login):
```
1. Admin/PMO (is_platform_admin=1) creates invitation
   → Creates users row (placeholder, line_user_id=NULL, auth_provider='line')
   → Creates workspace_members row with status='active', role assigned by admin
   → Generates secure claim token (signed JWT: workspace_id, user_id, expiry=24h)
   → Delivers claim URL to user via existing channels (email, internal message, manual share — no LINE Notify)
2. User clicks claim link → LINE Login (OIDC)
   → User completes LINE Login → LINE returns sub (line_user_id) + profile
   → Backend validates claim token (signature, expiry, workspace_id, user_id match)
   → Backend updates users row: line_user_id = sub, auth_provider='line', fills profile from LINE
   → Backend records audit_trails: action='LINE_LINK', entity_type='user'
   → User now has valid line_user_id + active workspace_members → normal LINE login works
```

**Normal LINE Login** (after membership is active):
```
User → Web UI → "Login with LINE" → LINE Authorization Server →
  User Consent → Authorization Code → Backend → Token Exchange →
  ID Token + Access Token → Validate ID Token (signature, aud, exp) →
  Lookup users.line_user_id = sub →
  Verify active workspace_members row (status='active') →
  Create Session (HttpOnly Secure Cookie) →
  Redirect to Dashboard

If no active membership → 403 fail-closed, no session
```

**Key Rules:**
- Normal LINE Login NEVER auto-creates user or grants membership
- Membership MUST exist (active workspace_members) before LINE Login succeeds
- Invitation creates active membership immediately; claim token binds line_user_id at claim time
- No pending_invitation status (schema has only active/removed)
- No LINE Notify dependency — claim link can be shared via any channel (email, chat, manual)
- Authorization always from PMOIS membership/role (workspace_members / project_members)

### 7.2 Authorization Model
- **RBAC**: Role-based, real roles `ADMIN/CTO/SENIOR_DEV/MEMBER/PMO_REVIEWER/VIEWER` + `users.is_platform_admin` (see `12-Role-Permission-Matrix.md` for the CEO/CTO/Dev/Viewer → real-role mapping)
- **ABAC**: Project-level override via `project_members`, falling back to `workspace_members` (existing `PermissionResolver`)
- **Not scope-based**: `api_tokens.scopes` is informational only, never the authorization decision — see `05-Auth-Authorization-Design.md` (Revision 1) for the corrected model
- **Fail-Closed**: Unauthorized LINE users cannot access system

### 7.3 Data Protection (Security Targets — Deployment-Dependent)

| Aspect | M0 Baseline | Notes |
|--------|-------------|-------|
| **Encryption at Rest** | MySQL TDE (if hosting supports) / Application-level for secrets | Deployment-dependent — confirm with hosting choice; not a hard M0 requirement |
| **Encryption in Transit** | TLS 1.3 (target) | Deployment-dependent — enforced by load balancer / reverse proxy; confirm with hosting |
| **Secrets Management** | Environment variables sufficient for M0 | `repositories.credential_reference` pattern (see `02-...md` §3.5); Vault/Secrets Manager optional future |
| **Audit Logging** | All mutations logged (existing `audit_trails`) | No TTL/purge mechanism today; retention policy TBD (Q07) |

---

## 8. Scalability Considerations (Revision 2: reclassified — most items here are optional future scale, not M0/M1 baseline)

| Component | Strategy | Status |
|-----------|----------|--------|
| API | Horizontal scaling (stateless) | Achievable with existing Slim/PHP-FPM setup; no new tech required |
| Database | Read replicas, partitioning | Optional future — no traffic volume confirmed to require it yet |
| Cache | Redis | Optional future — see `16-Proposed-Technology-Stack-with-Rationale.md` §5, not required for M0 |
| File Storage | S3-compatible | Optional — `attachments.storage_type` already supports `'local'` for M0, `'s3'` available later without schema change |
| Real-time | Polling/refetch | M0 approach; Socket.io/Redis Pub/Sub is an unconfirmed future option, not baseline |

---

## 9. Integration Points

| External System | Integration Method | Status |
|-----------------|-------------------|---------|
| **LINE Login** | OIDC | Required (M0 baseline) |
| **GitLab** | Manual metadata registration only | Required (M0 baseline) — REST API sync and webhooks are explicitly **future proposals**, not part of M0/M1 baseline (see `10-GitLab-Repository-Design.md` §5) |
| **AI Agents** | REST API via existing `api_tokens` (`ai_consumer_id`) | Required (M0 baseline, reuses existing mechanism) |
| **CI/CD** | GitLab CI/CD | Existing project convention; not a new M0 dependency |
| **Notification** | Not implemented in M0 | Email/Slack/webhook notification is an unconfirmed future proposal, not baseline |

---

## 10. Non-Functional Considerations (Revision 2: no fixed numeric targets are mandated — see `15-Risks-and-Open-Questions.md` Q07/Q08 and `17-Important-Constraint-Summary.md` §2)

| Aspect | M0 Status |
|-------------|--------|
| API Latency / Availability targets | Not fixed by this design — no hosting/infra decision made yet |
| Audit Log Retention | Open decision (Q07) — no fixed period; `audit_trails` has no purge mechanism today |
| Session Timeout | Implementation detail for M1, not fixed here |
| Token Rotation | Manual revoke/reissue today (existing `api_tokens` behaviour); automatic rotation is a future proposal |
| Backup Frequency, RPO/RTO | Open decision (Q08) — depends on a hosting choice not yet made |

---

## 11. API Versioning Note

Per `04-API-Endpoint-Design.md` (Revision 1), M0 additions use the **existing** `/api/v1` prefix — no `/api/v2` is introduced, and no existing `v1` endpoint is deprecated or renumbered. This supersedes any earlier mention of a `v2` migration/versioning strategy in this document.

---

## 12. Appendix: Directory Structure (illustrative only — not a commitment to Docker/React)

```
pmois-app/                    # existing repository, extended in place — not a rewrite
├── database/migrations/      # existing — extended with new M0 migrations (see 02-...md)
├── docs/                     # existing
├── src/
│   ├── Application/           # existing — Controllers, Middleware, Responders
│   ├── Domain/                # existing — Entities, Services, Repository Interfaces
│   └── Infrastructure/        # existing — Repository Implementations
├── tests/                     # existing
├── M0-Design/                 # this design package
└── web/                       # placeholder only — frontend technology is Q09 (open decision),
                                # not committed to React; directory name/structure TBD once decided
```


---

*Document Version: 1.0*  
*Status: DRAFT - For CTO Review*  
*Date: 2026-08-30*