# PMOIS v2 — M0 Design Package **Revision 6 (Final)** — Overview & M1 Impact Assessment

**Reference:** M0 Design Package Revision 5 (Commit `7adb6bb`) + M1 Implementation Plan Revision 3 (Commit `1995e7d`)
**Trigger:** CTO Decision — Revision Required (M0 Design Revision 6). M1 Implementation is **suspended** until Revision 6 passes CTO Review. Upon approval, Revision 6 becomes the **Design Freeze** baseline.

---

## 1. Why Revision 6 exists

During M0 R5 review and additional requirements raised by PMO, gaps were identified that must be fixed **before coding**, to avoid later Database Schema and Architecture changes:

| # | Requirement (CTO Decision) | R5 Status | R6 Resolution |
|---|---|---|---|
| 1 | **Project Team Registry** (CEO/PMO/CTO/Dev/Viewer, multi-assign, history) | `project_members` = current-state only, **no history** | New `project_member_assignments` (R6-06) |
| 2 | **AI Registry: Provider ⟂ Agent** | `ai_consumers` has no provider concept | New `ai_providers` + `ai_consumers.provider_id` (R6-06) |
| 3 | **Development Mode** | ✅ Already `projects.development_mode` (migration 0034) | Documented; no schema change (R6-01 §4) |
| 4 | **Repository Registry expansion** | `repositories` hardcodes GitLab (`gitlab_url`) | New `git_providers` + column rename to `repository_url` (R6-07) |
| 5 | **Progress vs Profile Completeness separation** | ✅ Both columns exist (0034); formula undefined | Formal completeness formula (R6-01 §5) |
| 6 | **Technology Stack Registry** (optional at creation, CTO/Dev fill later) | Not in schema | New `project_technology_stack` (R6-10) |
| 7 | **Environment Registry** (Dev/UAT/Prod, no secrets) | Not in schema | New `project_environments` (R6-10) |
| 8 | **Dependency Registry** (Depends On / Blocked By, graph) | Not in schema | New `project_dependencies` (R6-08) |
| 9 | **Release Registry** (Alpha/Beta/RC/Production/Hotfix) | Not in schema (Timeline ≠ Releases) | New `project_releases` (R6-09) |
| 10 | **Project Template** (auto-create governance, milestones, token, config) | Only governance auto-bind | New `project_templates` + creation pipeline (R6-05) |
| 11 | **Workspace Default Settings** | Not in schema | New `workspace_default_settings` (R6-11) |

## 2. Governing Principle (unchanged, restated)

> **CEO กรอกข้อมูลให้น้อยที่สุด** — Project creation requires only: Workspace, Name, Code/Abbreviation, CTO, Dev, Development Mode (7 fields). All technical registries (Technology Stack, Repository, Environment, Release, Dependency) are **optional at creation** and progressively filled by **CTO and Dev** during development. Template + Workspace Defaults make even the 7 fields partially pre-filled when possible.

## 3. Baseline this package builds on

M1 Phase 1 was implemented before this suspension (Commit `5cbd184`): migrations `0033`–`0042` (LINE fields, projects hierarchy/profile columns, `project_structure_history`, `milestones`, `repositories`, `revisions`, `revision_reviews`, `project_ai_assignments`, permission seed). **Revision 6 does not break any of it** — all R6 changes are additive, starting at migration `0043`.

## 4. Document Map (Revision 6)

| Doc | Content |
|---|---|
| `R6-01-ER-Diagram-Revision6.md` | Updated full ER Diagram + all new/altered table DDL + completeness formula |
| `R6-02-Database-Changes.md` | Migration list `0043`–`0055`, order constraints, rollback policy |
| `R6-03-API-Design-Update.md` | New endpoints, permission codes, error codes |
| `R6-04-UI-Sitemap-Update.md` | New screens/tabs, updated sitemap |
| `R6-05-Project-Creation-Flow-Revision6.md` | Updated creation flow + template application pipeline |
| `R6-06-Team-And-AI-Assignment-Design.md` | Team Registry + AI Provider/Agent split |
| `R6-07-Repository-Registry-Design.md` | Multi-provider repository design |
| `R6-08-Dependency-Registry-Design.md` | Dependency graph design |
| `R6-09-Release-Registry-Design.md` | Release history design |
| `R6-10-TechStack-And-Environment-Registry.md` | Technology Stack + Environment registries |
| `R6-11-Workspace-Default-Settings.md` | Workspace default design |
| `R6-12-M1-Impact-Assessment.md` | Impact on M1 Plan Revision 3, phase re-plan |

## 5. Documents of R5 that remain valid unchanged

`01-System-Architecture`, `03-Key-Relationships-Constraints`, `05-Auth-Authorization-Design`, `07-CTO-Review-Commit-PMO-Update-Flow`, `08-Governance-Template-Design`, `13-Audit-Security-Design`, `14-Milestone-Roadmap`, `15-Risks-and-Open-Questions`, `16-Tech-Stack`, `17-Constraint-Summary` — all still apply, except where explicitly superseded by R6 docs (see each R6 doc's "Supersedes" note).

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
