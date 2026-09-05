# PMOIS v2 — M1 Implementation Plan (Revision 4)

**Reference:** M0 Design Freeze **Revision 6** + CTO Approval letter (Design Freeze granted)
**Supersedes:** Revision 3 (Phase numbering only — everything else carries over)
**Status:** Phase 1 + Phase 1.5 IMPLEMENTED — submitted for CTO Review Gate

---

## 1. Changes from Revision 3

1. Baseline switched from M0 R5 to **M0 Revision 6 (Design Freeze)** — R6 Phase 1.5 inserted as its own phase.
2. CTO Design Constraints recorded as mandatory:
   - **Authentication: LINE Login only** (no Google/Microsoft/Entra/local/Multi-IdP)
   - **Source control: GitLab only** (no GitHub/Azure DevOps/Bitbucket)
   - **Notification: Telegram only** when notification work starts (no multi-provider design)
3. Development principles reiterated: Backward Compatible • Configuration over Hardcode • API First • UI/API share one business rule set • CEO minimal input • CTO/Dev fill technical data.

## 2. Phase status

| Phase | Content | Status |
|---|---|---|
| Phase 1 | Migrations 0033–0042, LINE Login, Project CRUD/Hierarchy, Milestone, AI Assignment, Governance auto-bind, GitLab Registry, Permission seed, Audit | **IMPLEMENTED** (this package, Revision 1) |
| Phase 1.5 | Migrations 0043–0055 + R6 registries + creation pipeline + completeness | **IMPLEMENTED** (this package) |
| Phase 2 | Revision/Review/Commit workflow (`revisions`, `revision_reviews`), read-only GitLab sync option, AI-attributed revisions (`dev_ai_consumer_id`) | Not started |
| Phase 3 | `ProjectStatusUpdater` wiring on commit, full audit sweep, e2e integration | Not started |
| Phase 4 | Hardening, E2E, docs | Not started |

## 3. Verification evidence for Phase 1 / 1.5

- Migrations: 0001→0055 apply ✅, rollback 0055→0043 ✅, re-apply ✅ (MySQL 5.7.36)
- Full PHPUnit suite: **86 tests / 162 assertions — OK** (`docs/m1/TEST-RESULTS-M1-R1.txt`)
- Lint (`php -l`, PHP 8.1): all files in `src/`, `database/`, `tests/` pass

## 4. Open decisions (carried, unchanged)

| ID | Item | Status |
|---|---|---|
| Q09 | Frontend framework | Open — blocks UI only |
| R6-Q1 | `default_permission_preset` contents (`standard`/`restricted`) | Config-level, finalize with PMO |
| R6-Q2 | May AI agents hold CTO role? | Open (policy) |
| R6-Q3 | Tighten `ai_consumers.provider_id` to NOT NULL | After provider reclassification post-deploy |
| — | Claim token: HMAC (current) vs standard JWT lib | CTO preference; swap is isolated to `InvitationService` |

## 5. Gate

Phase 2 starts only after CTO Review passes `PMOIS_v2_M1_Implementation_Revision1.zip` per the Review Note (`docs/m1/REVIEW-NOTE-M1-R1.md`).

---

*Document Version: 4.0 (Revision 4)*
*Date: 2026-09-05*
