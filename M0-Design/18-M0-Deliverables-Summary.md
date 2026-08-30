# PMOIS v2 — M0 Deliverables Summary

**Revision 2** — every row re-verified against the actual file content after fixing residual inconsistencies flagged in CTO Review (Revision 1 round): `11-Web-UI-Sitemap-Screen-List.md`, `14-Milestone-Roadmap-for-Implementation.md`, `15-Risks-and-Open-Questions.md`, plus a cross-document sweep of `01-System-Architecture.md`.

**Status:** Revision 2 — submitted for CTO Review
**Date:** 2026-08-30

---

## 1. Deliverable → File Mapping

| # | Deliverable | File | Verified this revision |
|---|---|---|---|
| 1 | System Architecture | `01-System-Architecture.md` | ✅ Rev 2: Sections 8–12 rewritten — removed fixed RPO/RTO/latency numbers, mandatory Redis/Kubernetes/Docker, `/api/v2` versioning language |
| 2 | MySQL ER Diagram | `02-MySQL-ER-Diagram-Database-Table-Design.md` | ✅ unchanged since Rev 1 |
| 3 | Database Table Design | (same file as #2) | ✅ |
| 4 | Key Relationship / Constraint | `03-Key-Relationships-Constraints.md` | ✅ unchanged since Rev 1 |
| 5 | API Endpoint Design | `04-API-Endpoint-Design.md` | ✅ unchanged since Rev 1 |
| 6 | Authentication / Authorization Design | `05-Auth-Authorization-Design.md` | ✅ unchanged since Rev 1 |
| 7 | LINE Login Flow | (same file as #6, §2) | ✅ |
| 8 | Project Creation Flow | `06-Project-Creation-Move-Promote-Flows.md` §1 | ✅ unchanged since Rev 1 |
| 9 | Project Move / Promote Flow | (same file as #8, §2–4) | ✅ |
| 10 | CTO Review → Commit → PMO Update Flow | `07-CTO-Review-Commit-PMO-Update-Flow.md` | ✅ unchanged since Rev 1 |
| 11 | Governance Template Design | `08-Governance-Template-Design.md` | ✅ unchanged since Rev 1 |
| 12 | AI Assignment Design | `09-AI-Assignment-Design.md` | ✅ unchanged since Rev 1 |
| 13 | GitLab Repository Design | `10-GitLab-Repository-Design.md` | ✅ unchanged since Rev 1 |
| 14 | Web UI Sitemap / Screen List | `11-Web-UI-Sitemap-Screen-List.md` | ✅ **Rev 2 fixed**: frontend framework returned to Open Decision (Q09) status; WebSocket claim removed; Governance Editor corrected to `LONGTEXT` (not JSON); all `/api/v2` → `/api/v1`; token scope wording corrected to "informational"; final summary table no longer overclaims decisions |
| 15 | Role Permission Matrix | `12-Role-Permission-Matrix.md` | ✅ unchanged since Rev 1 |
| 16 | Audit / Security Design | `13-Audit-Security-Design.md` | ✅ unchanged since Rev 1 |
| 17 | Milestone Roadmap for Implementation | `14-Milestone-Roadmap-for-Implementation.md` | ✅ **Rev 2 rewritten**: M0 completion redefined strictly as Design Approval Gate (no migration/implementation implied); removed fixed sprint/week durations, Beta "3–5 projects," ">80% approval," ">70% satisfaction," mandatory Docker, GitLab webhook handlers, and pre-selected React routes/components |
| 18 | Risks / Open Questions | `15-Risks-and-Open-Questions.md` | ✅ **Rev 2 rewritten**: removed CTO review 24h SLA / 48h escalation / CEO override / rate-limiting-caching requirement (reclassified as new open question Q12, undecided); corrected every Q01–Q08 reference to the actual full Q01–Q11 list; Decision Matrix, Timeline, and Responsibility content consolidated to cover Q09–Q11 |
| 19 | Proposed Technology Stack with Rationale | `16-Proposed-Technology-Stack-with-Rationale.md` | ✅ unchanged since Rev 1 |

**Supporting files (not part of the 19, required by process):**

| File | Purpose |
|---|---|
| `17-Important-Constraint-Summary.md` | Consolidated, corrected constraint list (Rev 1) |
| `18-M0-Deliverables-Summary.md` | This file |

---

## 2. Cross-Document Consistency Sweep (this revision)

Searched all files in `M0-Design/` for: `/api/v2`, `React` (as a decision claim), `WebSocket`, `Socket.io`, `JSON template`, `token scope`/`scope validation` (as authorization), `webhook` (as mandatory), `24h`/`48h`/fixed SLA, fixed `RPO`/`RTO`, and `M0` + `database migration` (as a completion criterion).

**Result:** all remaining occurrences of these terms are either (a) inside a "Revision N note" explicitly stating the item was removed/corrected, or (b) inside `16-Proposed-Technology-Stack-with-Rationale.md`'s explicit "Optional / Future Proposal" section, which is the intended, correctly-labeled place for such terms to appear. No occurrence asserts any of these as a decided M0/M1 baseline.

## 3. Gate

No production database or application code has been created. This remains Design Only, submitted for CTO Review before any Implementation Phase begins.

---

*Document Version: 3.0 (Revision 2)*
*Status: Submitted for CTO Review*
*Package: `PMOIS_v2_M0_Design_Package_Revision2.zip`*