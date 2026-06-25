# Migration Report: {Subject}

**Document type:** Migration Report  
**Document code:** MIG-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending (PMO)  
**Approver:** Pending (PMO)  
**Project:** {project name}  
**Migration files:** {list migration file names}  
**Related documents:** {links to DB, DEP documents — or None}

---

> *Instructions: Copy this template to `docs/migrations/mig-{subject}.md`. Sections 1 through 7 should be prepared before executing migrations. Sections 8 and 9 are completed after the migrations run. A Migration Report is required for every set of migrations applied to the production database.*
>
> *Record the actual SQL executed and the actual outcomes. Do not reconstruct this document after the fact.*

---

## 1. Migration Summary

> *[REQUIRED] What schema changes does this migration set make, and why?*

| Item | Value |
|------|-------|
| Environment | {Production / Staging} |
| Database | {database name} |
| Migration date | {YYYY-MM-DD} |
| Migration start time | {HH:MM timezone} |
| Migration end time | {HH:MM timezone} |
| Executed by | {name} |

**Migration files in this report:**

| Migration file | Description |
|---------------|-------------|
| `{number}_{name}.sql` | |

---

## 2. Environment

> *[REQUIRED] Describe the state of the database environment at the time of migration.*

| Item | Value |
|------|-------|
| MySQL version | |
| Current highest migration | {number}_{name} (before this run) |
| Row counts for affected tables | {table: N rows, ...} |
| Last database backup taken | {YYYY-MM-DD HH:MM} |

---

## 3. Migration Files

> *[REQUIRED] Show the complete content of each migration file applied in this report.*

---

### {number}_{name}.sql

```sql
{exact content of the migration file}
```

**Rollback file:** `{number}_{name}.rollback.sql`

```sql
{exact content of the rollback file}
```

---

## 4. Pre-migration State

> *[REQUIRED] Describe the relevant state of the database before the migrations were applied. For schema migrations, show the output of `DESCRIBE {table_name}` or equivalent. For seed migrations, show the row counts of affected tables.*

```sql
-- DESCRIBE {table_name} before migration:
{output}
```

---

## 5. Migration Steps Executed

> *[REQUIRED] Record every command run, in order. Include the time each command was executed and its outcome.*

| Step | Time | Command / action | Outcome |
|------|------|-----------------|---------|
| 1 | {HH:MM} | `mysql -u {user} -p {db} < {file}` | Success / Error |

---

## 6. Post-migration Verification

> *[REQUIRED] Verify that the migration produced the expected schema changes. Show the output of verification queries.*

```sql
-- DESCRIBE {table_name} after migration:
{output}

-- Row count verification:
SELECT COUNT(*) FROM {table_name};
-- Expected: {n}
-- Actual:   {n}
```

**Application health check after migration:**

| Check | Result |
|-------|--------|
| `GET /api/v1/health` | {response} |
| {relevant endpoint} | |

---

## 7. Rollback Procedure

> *[REQUIRED] Document the exact steps to reverse these migrations. This section must be complete before migration begins.*

**Rollback trigger criteria:**

- {condition under which rollback should be initiated}

**Rollback steps:**

| Step | Command / action |
|------|-----------------|
| 1 | `mysql -u {user} -p {db} < {number}_{name}.rollback.sql` |

**Rollback safe if data has been written to new columns?** {Yes / No — {reason}}

**Estimated rollback time:** {duration}

---

## 8. Incidents and Observations

> *[REQUIRED] Record every incident, error, or unexpected behaviour encountered during migration. If migration was entirely clean, state "None — all migrations applied successfully with no errors or unexpected output."*

> *Do not fill in "None" before migration is complete.*

| # | Time | Description | Resolution |
|---|------|-------------|-----------|
| | | | |

---

## 9. Sign-off

> *[REQUIRED] Completed after migration is confirmed stable and the application is verified.*

**Migration result:** {Success / Rolled back / Partial}

**Database state after migration:** {Matches expected / Deviates — see Section 8}

**Signed off by:** {name}  
**Role:** {Deployer / PMO}  
**Sign-off date:** {YYYY-MM-DD}
