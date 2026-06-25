# ADR-0001: PMOIS API v1.0 Freeze

**Document type:** Architecture Decision Record  
**Document code:** ADR-0001  
**Version:** 1  
**Status:** Accepted  
**Review status:** Reviewed  
**Reviewed by:** CTO  
**Approval status:** Approved  
**Approved by:** CTO  
**Date:** 2026-06-25  
**Last updated:** 2026-06-25  
**Author:** Claude Code (on behalf of CTO)  
**Project:** PMOIS

---

## Context

PMOIS (Project Management & Oversight Information System) has completed four implementation phases:

| Phase | Feature | Status |
|-------|---------|--------|
| Phase 1 | Core API — Workspaces, Projects, Users, Auth | Complete |
| Phase 2 | Project Status Updates — submit, view, history | Complete |
| Phase 3 | AI Consumer Access Control — deny-by-default for AI tokens | Complete |
| Phase 4 | Project-Scoped Token Enforcement — one token → one project | Complete |

Phase 4 was verified in production on 2026-06-25. All 12 verification scenarios (9 primary + 3 backward-compatibility) passed. The CTO reviewed and approved the Verification Report on 2026-06-25.

---

## Decision

**PMOIS API v1.0 is frozen as of 2026-06-25.**

The v1.0 surface is defined by the routes, request schemas, response schemas, and auth behaviour present in production at commit `95cd104` on branch `master`.

---

## v1.0 API Surface

### Auth

| Method | Route | Token type required |
|--------|-------|-------------------|
| `POST` | `/api/v1/auth/tokens` | Workspace-level (ADMIN) |
| `GET` | `/api/v1/auth/tokens` | Workspace-level (ADMIN) |
| `DELETE` | `/api/v1/auth/tokens/{id}` | Workspace-level (ADMIN) |

### Projects

| Method | Route | Token type required |
|--------|-------|-------------------|
| `GET` | `/api/v1/projects` | Workspace-level (ADMIN) |
| `GET` | `/api/v1/projects/{project_id}` | Workspace-level or project-scoped (matching project) |

### Project Status Updates

| Method | Route | Token type required |
|--------|-------|-------------------|
| `POST` | `/api/v1/projects/{project_id}/status` | Workspace-level or project-scoped (matching project) |
| `GET` | `/api/v1/projects/{project_id}/status` | Workspace-level or project-scoped (matching project) |
| `GET` | `/api/v1/projects/{project_id}/status/history` | Workspace-level or project-scoped (matching project) |

### Health

| Method | Route | Auth required |
|--------|-------|--------------|
| `GET` | `/api/v1/health` | None |

---

## Token Classification (v1.0)

| `api_tokens.project_id` | Token type | Access |
|-------------------------|------------|--------|
| `NULL` | Workspace-level | All routes in the workspace |
| `N` (integer) | Project-scoped | Only routes where `{project_id}` route arg = N |

---

## Consequences

### What v1.0 freeze means

- Existing integrations using the v1.0 surface are stable and will not break from changes within the v1 series.
- Breaking changes (removed routes, changed required fields, changed auth behaviour) require a new major version (`v2`).
- Additive changes (new optional fields, new routes) are permitted within v1 without a version bump.

### Known limitations (accepted for v1.0)

1. **No cross-workspace FK validation at token creation:** The API does not verify that `project_id` supplied at token creation belongs to the same workspace as the token. The database FK prevents a non-existent project but not a cross-workspace reference. Flagged as future hardening item.

2. **No token revocation UI:** Token revocation is done via `DELETE /api/v1/auth/tokens/{id}` using the ADMIN token. No self-service UI exists.

3. **`submitted_by` stored as user ID:** The status update `submitted_by` field stores the `user_id` of the token's `created_by_user_id`. For project-scoped AI tokens, this is the user who created the token, not a human submitter.

---

## First Integration: MJU Asset

The first external project-scoped integration is MJU Asset (internal asset management):

| Item | Value |
|------|-------|
| Project code | `MJU-ASSET` |
| Project ID | `4` |
| Token name | MJU Asset Integration Token |
| Token ID | `8` |
| Token type | Project-scoped (`project_id = 4`) |
| Integration cleared | 2026-06-25 |

---

## Related Documents

- [Security Design — Project-Scoped Token Enforcement](../security/project-scoped-token-enforcement.md)
- [Verification Report — Phase 4](../reports/project-scoped-token-enforcement-verification.md)
- [Deployment Runbook — Phase 4](../reports/dep-phase4-project-scoped-token-runbook.md)
- [Governance Framework v1.4](../governance/governance-framework-v1.4.md)
