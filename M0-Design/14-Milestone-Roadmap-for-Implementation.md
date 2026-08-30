# PMOIS v2 — Milestone Roadmap for Implementation (M0 Item 17)

**Revision 2** — re-grounded to the Revision 1/2 baseline. All fixed durations, percentages, and specific technology commitments from the original draft are removed or reclassified as open planning items, per CTO instruction that M0 does not commit implementation-phase specifics.

## 1. M0 Completion = Design Approval Gate (not implementation)

**M0 is complete when CTO approves this design package.** M0 completion does **not** require, and must not be read as requiring:
- Any database migration run (even in a test environment)
- Any application code written
- Any schema validated against a live database

This corrects the original draft's M0 sign-off checklist, which incorrectly listed "Test migrations run without errors" as an M0 completion criterion — that activity belongs to the Implementation Phase (post-approval), not M0 itself.

### 1.1 M0 Sign-Off Criteria (the only things required for M0 to be "done")

| Criteria | Requirement |
|---|---|
| Design Package Complete | 19 deliverables present and internally consistent (this package) |
| CTO Review Completed | CTO has reviewed and issued approval (or requested further revision) |
| No Implementation Started | Confirmed — no migration run, no production code written |

## 2. Phases After M0 Approval (names only — durations are open planning items, not fixed here)

```
M0 (Design Approval) → M1 (Core Implementation) → M2 (Governance/AI wiring) → M3 (Pilot) → M4 (Rollout)
```

No fixed week/sprint counts are assigned to M1–M4 in this document — team capacity and actual scope are not yet known at design time. Sprint planning happens **after** M0 approval, as its own activity, not pre-committed here.

## 3. M1 Scope (what, not when) — grounded in Revision 1/2 documents, no new invented mechanisms

| Area | Source document | Note |
|---|---|---|
| New/altered tables (`02-...md` §3) | `02-MySQL-ER-Diagram-Database-Table-Design.md` | Migration files to be written during M1, following existing migration numbering convention (`0033_...` onward) |
| LINE Login integration | `05-Auth-Authorization-Design.md` | Uses whatever OIDC client library is chosen during M1; not pre-selected here |
| New permission codes | `12-Role-Permission-Matrix.md` §4 | Added to `role_permissions` seed data, no schema change |
| Revisions/Milestones/Repositories/AI Assignment services | `02-...md` §3, `07-...md`, `09-...md`, `10-...md` | Built following existing `BaseRepository`/Controller/Service patterns already in the codebase |
| Web UI | `11-Web-UI-Sitemap-Screen-List.md` | Cannot start until Q09 (frontend approach) is decided — this is a **prerequisite for M1 UI work**, not a parallel-track assumption |

Explicitly **not** part of M1 baseline unless separately approved: GitLab webhook handlers, automated branch protection, Docker/container packaging, any specific frontend framework — all remain optional/future per `10-GitLab-Repository-Design.md` §5 and `16-Proposed-Technology-Stack-with-Rationale.md` §5.

## 4. M2–M4 (directional only, not committed)

| Phase | Directional intent | Status |
|---|---|---|
| M2 | Governance auto-binding and AI assignment wired into the revision workflow end-to-end | Reuses existing `governance_adoptions`/`ai_consumers` — no new registry to build (already exists, see `09-AI-Assignment-Design.md`) |
| M3 | Pilot with a small number of real projects | Number of projects, duration, and success criteria are **not fixed here** — to be proposed and agreed separately once M1/M2 scope is actually delivered |
| M4 | Broader rollout | Criteria to be defined closer to the time, based on M3 pilot outcome |

No numeric target (e.g. "3-5 projects," "80% approval rate," "70% satisfaction") is stated in this revision — all such figures in the original draft are removed as unconfirmed and premature at design stage.

## 5. Dependency Note

The only hard dependency stated here: **M1 cannot begin until CTO approves this M0 package**, and **Web UI work cannot begin until Q09 is decided** (`15-Risks-and-Open-Questions.md`). All other sequencing (which service before which, sprint-by-sprint breakdown) is an M1 planning activity, not fixed by this design document.

---

*Document Version: 2.0 (Revision 2)*
*Status: Revised per CTO Review — removed fixed durations/metrics, M0 redefined strictly as Design Approval Gate*
*Date: 2026-08-30*