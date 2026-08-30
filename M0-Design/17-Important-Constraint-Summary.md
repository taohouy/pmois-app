# PMOIS v2 — Important Constraint Summary (M0)

**Revision 1** — removed items that were invented without a confirmed requirement (fixed retention period, fixed RPO/RTO, mandatory rate limits); corrected cross-references to the revised documents.

## 1. Confirmed Constraints (from the CEO brief — non-negotiable)

| # | Constraint | Enforcement |
|---|---|---|
| C01 | LINE Login only for Web UI; no local username/password | `users.line_user_id` + `auth_provider`; see `05-Auth-Authorization-Design.md` |
| C02 | LINE user not pre-authorized (no active `workspace_members`) → fail-closed, denied | `13-Audit-Security-Design.md` §3.2 |
| C03 | Authorization managed in PMOIS, not derived from LINE profile | `PermissionResolver` — unchanged existing mechanism |
| C04 | Project ID (`projects.id`) immutable across Move Workspace / Change Parent / Promote | `03-Key-Relationships-Constraints.md` §1, §5 |
| C05 | CEO creates project with minimal fields only (7 fields) | `06-Project-Creation-Move-Promote-Flows.md` §1 |
| C06 | Progress / Portfolio Status not editable by Dev independently | `03-Key-Relationships-Constraints.md` §5, enforced via `project.progress.update` permission code |
| C07 | Milestone Closed requires CTO decision, not Dev | `milestone.close` permission code, `07-CTO-Review-Commit-PMO-Update-Flow.md` §4 |
| C08 | Governance is centrally sourced in PMOIS; not copied into repositories | `08-Governance-Template-Design.md` §6 (already true of existing `governance_versions.content`) |
| C09 | No plain-text GitLab secrets stored | `repositories.credential_reference` (pointer only), `10-GitLab-Repository-Design.md` §4 |
| C10 | Commit/push happens only after CTO approval, never before | `07-CTO-Review-Commit-PMO-Update-Flow.md` §3 Step 5 (DB-guarded: `WHERE status='cto_approved'`) |
| C11 | No circular project hierarchy | `03-Key-Relationships-Constraints.md` §2, application-layer ancestor-chain check |
| C12 | Progress vs. Profile Completeness kept as separate, distinct fields | `projects.progress_percent` vs. `projects.profile_completeness_percent` |
| C13 | M0 = Design only; no production DB/app changes before CTO approval | This entire package is a proposal pending review |

## 2. Removed From "Constraint" Status (were invented, now reclassified as open decisions or future proposals — not mandatory)

| Previously stated as | Now | Reference |
|---|---|---|
| Fixed 5-level hierarchy depth cap | No fixed cap; UI visualization instead | `15-Risks-and-Open-Questions.md` Q02 |
| Fixed 7-year audit retention | Open decision, not yet made | `15-Risks-and-Open-Questions.md` Q07 |
| Fixed RPO 1h / RTO 4h | Removed — depends on hosting decision not yet made | `16-Proposed-Technology-Stack-with-Rationale.md` |
| Fixed API rate limits (100 req/min etc.) | Not implemented in M0; no fixed numbers mandated | `04-API-Endpoint-Design.md` §6 |
| Mandatory branch-protection automation via GitLab API | Future proposal, not baseline | `10-GitLab-Repository-Design.md` §5 |
| Token scopes as the authorization decision | Corrected: authorization is via `PermissionResolver`/`role_permissions`, scopes are informational | `05-Auth-Authorization-Design.md` |

## 3. Response Format Constraint

All API responses use the existing envelope `{success, data, error{code,message,details}, meta}` (`src/Application/Http/Responders/ApiResponse.php`) — see `04-API-Endpoint-Design.md` §2 for the full rationale on why this, rather than a new flat structure, was kept.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review*
*Date: 2026-08-30*