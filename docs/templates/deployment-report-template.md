# Deployment Report: {Subject}

**Document type:** Deployment Report  
**Document code:** DEP-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending (PMO)  
**Approver:** Pending (PMO)  
**Project:** {project name}  
**Phase / Feature:** {describe what was deployed}  
**Related documents:** {links to VER, MIG, SEC, ARCH documents — or None}

---

> *Instructions: Copy this template to `docs/reports/dep-{subject}.md`. Sections 1 through 5 should be prepared before deployment. Sections 6 and 7 are completed after deployment. Do not pre-fill Section 6 (Incidents) with "None" before deployment is complete.*
>
> *A Deployment Report is a record of what happened, not what was intended. Record the actual commands run, the actual times, and the actual outcomes.*

---

## 1. Deployment Summary

> *[REQUIRED] A brief description of what was deployed in this operation.*

| Item | Value |
|------|-------|
| Environment | {Production / Staging} |
| Server | {hostname} |
| Deployment date | {YYYY-MM-DD} |
| Deployment start time | {HH:MM timezone} |
| Deployment end time | {HH:MM timezone} |
| Deployed by | {name} |
| Phase / Feature | {description} |
| Code branch / commit | {git commit hash or tag} |

**Scope of this deployment:**

> *List the features, migrations, or configuration changes included in this deployment.*

- {item}

---

## 2. Pre-deployment Checklist

> *[REQUIRED] Complete this checklist before starting deployment. Mark each item as checked or not applicable.*

| # | Check | Status |
|---|-------|--------|
| 1 | All required verification reports have `Status: Active` | ☐ / N/A |
| 2 | Database migration rollback files are available and tested | ☐ / N/A |
| 3 | A database backup has been taken within the last 24 hours | ☐ / N/A |
| 4 | Deployment has been announced to affected teams | ☐ / N/A |
| 5 | Rollback procedure has been reviewed and is understood | ☐ / N/A |
| 6 | Deployment window is within the approved change window | ☐ / N/A |
| 7 | CTO has approved all SEC and ADR documents in this deployment | ☐ / N/A |

**Pre-deployment checklist completed by:** {name}  
**Completed at:** {YYYY-MM-DD HH:MM timezone}

---

## 3. Deployment Steps

> *[REQUIRED] List every step taken during deployment, in the order they were executed. Record the actual commands run, not a generic description. This section is the record of what happened — it must be filled in as deployment proceeds, not reconstructed after the fact.*

| Step | Action | Command or description | Outcome |
|------|--------|----------------------|---------|
| 1 | | | Success / Failed |
| 2 | | | |

### Migration steps

> *List every migration applied, in order.*

| Step | Migration file | Time taken | Outcome |
|------|---------------|------------|---------|
| | | | |

---

## 4. Post-deployment Verification

> *[REQUIRED] List the checks performed after deployment to confirm that the system is functioning correctly.*

| Check | Method | Expected | Actual | Result |
|-------|--------|----------|--------|--------|
| Health endpoint | `GET /api/v1/health` | `{"status": "ok"}` | | |
| {feature check} | | | | |

**Post-deployment verification completed by:** {name}  
**Completed at:** {YYYY-MM-DD HH:MM timezone}

---

## 5. Rollback Procedure

> *[REQUIRED] Document the steps required to reverse this deployment completely. This section must be complete before deployment begins. The rollback procedure must be verified to be executable by a person other than the author.*

**Rollback trigger criteria:**

> *Under what conditions should rollback be initiated?*

- {condition}

**Rollback steps:**

| Step | Action | Command or description |
|------|--------|----------------------|
| 1 | | |

**Estimated rollback time:** {duration}  
**Rollback verified to be complete when:** {condition}

---

## 6. Incidents and Observations

> *[REQUIRED] Record every incident, unexpected behaviour, or deviation from the expected deployment steps. If deployment was entirely clean with no deviations, state "None — deployment completed as planned with no incidents."*

> *Do not fill in "None" before deployment is complete.*

| # | Time | Description | Resolution |
|---|------|-------------|-----------|
| | | | |

---

## 7. Sign-off

> *[REQUIRED] Completed after deployment is confirmed stable.*

**Deployment result:** {Success / Rolled back / Partial}

**Signed off by:** {name}  
**Role:** {Deployer / PMO / CTO}  
**Sign-off date:** {YYYY-MM-DD}

**Open items (if any):**

| # | Description | Owner | Target date |
|---|-------------|-------|-------------|
| | | | |
