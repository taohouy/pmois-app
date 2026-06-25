# Database Design: {Subject}

**Document type:** Database Design  
**Document code:** DB-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending  
**Approver:** Pending  
**Project:** {project name}  
**Related documents:** {links to related ARCH, SEC, MIG documents — or None}

---

> *Instructions: Copy this template to `docs/database/db-{subject}.md`. Fill in every section. Remove all instruction blocks (lines beginning with `> *`) before submitting for review. Include the exact SQL for every table defined in this document.*

---

## 1. Objective

> *[REQUIRED] What data does this design store, and what business purpose does it serve?*

---

## 2. Background

> *[REQUIRED] What requirement or phase introduced this schema? Reference the relevant design decision. Describe the state of the database before this design if it modifies existing tables.*

---

## 3. Entity-Relationship Summary

> *[REQUIRED] Describe the entities and their relationships in plain language. A diagram is welcome but a clear narrative is sufficient.*

```
{entity-relationship diagram or narrative}
```

**Primary entities introduced:**

| Entity | Table name | Description |
|--------|-----------|-------------|
| | | |

**Relationships:**

| From table | Relationship | To table | Via column |
|------------|-------------|----------|------------|
| | | | |

---

## 4. Table Definitions

> *[REQUIRED] Define every table introduced or modified by this design. Use one subsection per table. Include the exact SQL.*

---

### 4.{n} `{table_name}`

> *Purpose:*

```sql
CREATE TABLE {table_name} (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- {describe purpose of each column group}

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Column notes:**

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | BIGINT UNSIGNED | No | Auto-increment primary key |
| | | | |

---

## 5. Index Strategy

> *[REQUIRED] List every index on the tables defined in this document. For each index, state its purpose and the queries it supports. Include the UNIQUE constraint explanation where relevant.*

| Table | Index name | Columns | Type | Purpose |
|-------|-----------|---------|------|---------|
| | | | UNIQUE / INDEX | |

---

## 6. Foreign Key Relationships

> *[REQUIRED] List every foreign key constraint. State what referential integrity guarantee each constraint provides and the consequence of a constraint violation.*

| Constraint name | Child table | Child column | Parent table | Parent column | On delete |
|-----------------|-------------|-------------|-------------|--------------|-----------|
| | | | | | RESTRICT |

---

## 7. Migration Plan

> *[REQUIRED] List the migration files that implement this design, in the order they must be applied.*

| Migration file | Description | Reversible |
|---------------|-------------|-----------|
| `{number}_{name}.sql` | | Yes / No |

> *State any dependencies between migrations (e.g., migration 0032 requires 0031 to be applied first).*

---

## 8. Rollback Procedure

> *[REQUIRED] Describe how to reverse each migration. List the rollback file name and the exact SQL if the rollback is not trivial. State any conditions under which rollback is not safe (e.g., if data has already been written to the new column).*

| Migration file | Rollback file | Rollback safe if data written? |
|---------------|--------------|-------------------------------|
| | `.rollback.sql` | Yes / No — {reason} |

---

## 9. Data Volume Estimates

> *[OPTIONAL] Estimate the expected number of rows at launch, at 1 year, and at 3 years for each new table. This helps inform index and partitioning decisions. Omit if genuinely unknown and not material to the design.*

| Table | At launch | 1 year | 3 years | Notes |
|-------|-----------|--------|---------|-------|
| | | | | |

---

## 10. Risks and Limitations

> *[REQUIRED] List known risks, trade-offs, or limitations of this schema design. Include: missing constraints, missing indexes that may become necessary, and any known data integrity gap not currently enforced by the database.*
