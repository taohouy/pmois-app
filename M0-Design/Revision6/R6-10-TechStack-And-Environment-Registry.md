# PMOIS v2 — Technology Stack & Environment Registry — Revision 6

**New design** (CTO Requirements #6 and #7). No R5 counterpart.

## 1. Technology Stack Registry (Requirement #6)

`project_technology_stack` [R6-NEW] — DDL in `R6-01` §2.6.

### 1.1 Principle: optional at creation, progressive enrichment

- The **CEO creation form contains no tech-stack field** (7-field rule preserved).
- Database and UI both fully support the registry; **CTO/Dev fill it in** during development (`project.techstack.manage`: ADMIN/CTO/SENIOR_DEV/MEMBER).
- Uniqueness `(project_id, layer, name)` — one entry per layer+name; version changes are updates (history via `audit_trails`, no extra table per minimal-schema principle).

### 1.2 Layers

`language`, `framework`, `database`, `runtime`, `frontend`, `infrastructure`, `tooling`, `other` — enum kept intentionally coarse; `other` + `notes` covers everything exotic. Status `active`/`deprecated`/`planned` lets a team record planned and retired technologies, which feeds later portfolio analysis (e.g. "projects still on PHP 7.x").

## 2. Environment Registry (Requirement #7)

`project_environments` [R6-NEW] — DDL in `R6-01` §2.7.

### 2.1 Coverage

| Requirement field | Column |
|---|---|
| Environment tier | `environment` ENUM(`development`, `uat`, `production`) |
| URL | `url` |
| Runtime | `runtime` |
| PHP Version | `php_version` |
| Database | `database_engine` |
| Deploy Path | `deploy_path` |

Multiple rows per tier allowed (e.g. two UAT servers) via `name` (`uq (project_id, environment, name)`).

### 2.2 No secrets (hard rule)

No `password`, `secret`, `token`, or connection-string-with-credentials column exists. If a pointer is genuinely needed, `credential_reference` stores the **name** of an environment variable / secret-store key — same pattern and rationale as `repositories.credential_reference` (R5 §3.5). Validation rejects values matching secret-like column names in API input (`VALIDATION_ERROR`).

## 3. Interaction

- Releases link to environments (`project_releases.environment_id`) — "what shipped where".
- Environments count toward Profile Completeness (weight 15); Tech Stack (weight 15, ≥3 entries) — see `R6-01` §4.
- Both registries render as Project Detail tabs (`/projects/:id/tech-stack`, `/projects/:id/environments`) and are optional fields in the template payload (applied at creation only when the template declares them).
