# PMOIS v2 — Risks / Open Questions (M0 Item 18)

**Revision 2** — removed unconfirmed fixed numbers (SLA hours, escalation windows, CEO override, rate-limiting/caching requirement) per CTO Review §3; corrected all Q01–Q08 references to the actual full list Q01–Q11.

## 1. Identified Risks

### 1.1 Technical Risks

| Risk ID | Risk Description | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| **R01** | LINE Login API changes could break the login integration | Medium | High | Monitor LINE developer announcements; version tolerance in auth code |
| **R02** | Schema migration issues when M1 actually writes the new migrations from `02-...md` | Low | High | Run in test environment first; rollback script per migration (standard practice already used in existing `0001`–`0032`) |
| **R03** | GitLab sync (if ever implemented, `10-...md` §3) could hit rate limits | Medium | Low | Not applicable to M0 — sync is an optional future feature, no mitigation needed now |
| **R04** | New M0 permission codes forgotten for some role in `role_permissions` seed data | Medium | Medium | `12-Role-Permission-Matrix.md` as single source of truth, checked before M1 seed migration |
| **R05** | AI agent response quality variability across ChatGPT/Claude/etc. | Medium | Medium | Human-in-the-loop for CTO-level decisions (already enforced structurally — AI cannot hold `revision.review` unless explicitly role-assigned) |

### 1.2 Process/Workflow Risks

| Risk ID | Risk Description | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| **R06** | CTO becomes a bottleneck reviewing revisions | High | High | **Open Proposal, not baseline** — see Q12 below. No SLA/escalation/override mechanism is part of this design; it is a candidate to decide, not decided. |
| **R07** | Teams stop following governance templates | Medium | Medium | Governance auto-adoption on project creation (`08-...md` §3) gives a baseline default; enforcement strictness is Q03 |
| **R08** | Users confuse Project Progress vs. Profile Completeness | Medium | Low | Distinct columns (`progress_percent` vs `profile_completeness_percent`), distinct UI labeling |
| **R09** | Deep project hierarchies become hard to navigate | Low | Medium | No fixed depth cap imposed (Q02); UI breadcrumb/visualization instead |
| **R10** | CEO/platform-admin becomes a bottleneck for portfolio decisions | Medium | Medium | Delegation via `PMO_REVIEWER`/`CTO` role grants is already possible in the existing permission model; no new mechanism required |

### 1.3 Open Questions — Full List (Q01–Q11, plus new Q12 from this revision)

| ID | Question | Options | Owner | Status |
|---|---|---|---|---|
| **Q01** | Default Development Mode for new projects | Manual / AI Assisted / AI Dev Auto | CTO | Open |
| **Q02** | Project hierarchy depth limit | Fixed cap / No fixed cap (adopted: no cap, see `03-...md` §2) | CTO | Resolved in Revision 1 — no cap |
| **Q03** | Governance template enforcement strictness | Mandatory / Recommended with overrides / Optional | CEO/CTO | Open |
| **Q04** | AI agent cost model | Included in PMOIS / Pay-per-use / Separate billing | CTO/Finance | Open |
| **Q05** | Pilot project selection approach (once M1/M2 are actually delivered) | First-come / Strategic selection / Random | CEO | Open — not urgent, post-M1 |
| **Q06** | LINE user authorization scope | Open to all / Invite-only / Domain-restricted | CEO | Open (fail-closed mechanism itself is decided; this is about *who* gets invited) |
| **Q07** | Audit log retention period | 1 year / 7 years / Configurable | Legal/CEO | Open — not a blocker, no fixed value in baseline |
| **Q08** | Backup frequency | Daily / Daily+incremental / Weekly | DBA | Open — depends on undecided hosting choice |
| **Q09** | Web UI frontend approach | Server-rendered PHP / Separate SPA | CTO | Open — **blocks M1 UI work start** |
| **Q10** | Single-CEO/platform-admin enforcement | DB constraint / Operational discipline only | CTO | Open |
| **Q11** | Restrict `project.create` to admin-only | Keep existing seed (`MEMBER` included) / Change seed to admin-only | CTO | Open — affects `12-Role-Permission-Matrix.md` §3 |
| **Q12** | *(New)* Should CTO review have a formal SLA/escalation/override mechanism (R06)? | A: No mechanism in PMOIS (rely on management practice outside the system) / B: Define a specific SLA + escalation as a deliberate, separately-approved policy | CEO/CTO | Open — explicitly **not decided** in this design; any specific hour figure would be invented if stated here |

**Note:** none of Q01–Q12 block CTO's approval of this M0 design package itself, **except Q09**, which blocks the start of M1 Web UI work specifically (everything else can proceed or be decided in parallel).

### 1.4 Decision Process (generic, not a per-question fixed schedule)

```
For each open question:
1. Present options
2. Owner (per table above) makes the decision
3. Record the decision via audit_trails (action='DECISION_RECORDED') or as a
   dated note in this document
4. Update any design document whose content depended on the answer
```

No fixed "decide by M0 sign-off" deadline is imposed on questions that are not blockers (only Q09 is called out as blocking, per §1.3).

### 1.5 Risk Register Summary

| Category | Risks | Status |
|---|---|---|
| Technical | R01–R05 | Mitigation approach noted, no fixed metrics invented |
| Process | R06–R10 | R06 explicitly deferred to Q12 rather than given an invented SLA |

---

## 2. Risk Monitoring (lightweight, no fixed cadence mandated)

Risk review cadence (weekly/monthly/per-milestone) is an operational choice for whoever runs the project post-M0 — not fixed by this design document, since no PM tooling/ceremony has been confirmed as a requirement.

### 2.1 Risk Escalation (mechanism only, no fixed time window)

```
1. Risk owner determines it cannot be resolved within their own authority
2. Escalate to CTO (technical) or CEO/platform-admin (portfolio-level)
3. Decision recorded via audit_trails
```
No "48-hour response time" or similar figure is stated — see Q12.

---

## 3. Assumptions & Constraints

### 3.1 Key Assumptions

| ID | Assumption | Impact if wrong |
|---|---|---|
| A01 | LINE maintains backward-compatible OIDC endpoints | Auth breaks if changed without notice |
| A02 | MySQL 8.0+ available with InnoDB | Schema constraints if an older version is forced |
| A03 | GitLab remains the primary repository provider | Rework if switched |
| A04 | CTO will actually perform the review step in the workflow (§07) in practice | Workflow stalls if no CTO capacity exists — mitigation is Q12, not assumed away |

### 3.2 Key Constraints (see `17-Important-Constraint-Summary.md` for the full authoritative list)

| ID | Constraint |
|---|---|
| C01 | LINE Login only, no local username/password |
| C02 | MySQL only |
| C03 | No production DB/code before CTO approval of M0 |
| C04 | Governance centrally sourced, not copied to repos |
| C05 | Project ID immutable |
| C06 | CEO creates project with minimal fields only |
| C07 | Progress/Portfolio Status not editable by Dev independently |
| C08 | No plain-text GitLab secrets |

---

## 4. Risk Priority Summary

| Priority | Risk ID | Title |
|---|---|---|
| High | R06 | CTO review bottleneck — see Q12, no mitigation mechanism decided yet |
| Medium | R01, R05, R10 | LINE API change, AI variability, CEO/admin bottleneck |
| Low | R02, R03, R04, R07, R08, R09 | Standard engineering/process risks with existing mitigation patterns |

---

*Document Version: 3.0 (Revision 2)*
*Status: Revised per CTO Review — removed unconfirmed fixed figures, corrected Q01–Q11 numbering, added Q12*
*Date: 2026-08-30*