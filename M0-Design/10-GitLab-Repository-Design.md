# PMOIS v2 — GitLab Repository Design (M0 Item 11)

**Revision 1** — removes auto-create GitLab group/branch automation (flagged in CTO Review §8 as an unconfirmed requirement). Repository registration is **manual metadata entry + read-only sync**, not an automation that creates things in GitLab on PMOIS's behalf.

## 1. Table

`repositories` — **NEW** table, no existing equivalent (`02-MySQL-ER-Diagram-Database-Table-Design.md` §3.5). One project may have multiple rows (`repository_type`: main/supporting/docs/test/infra/custom).

## 2. Registration Flow (manual — no auto-provisioning)

```
CTO/Dev already created the repository in GitLab themselves (out of PMOIS's control).
POST /api/v1/repositories   (permission: repository.manage)
{
  "project_id": 892,
  "repository_type": "main",
  "repository_name": "mar-00892",
  "gitlab_url": "https://gitlab.com/pmois/mar-00892",
  "default_branch": "main"
}
→ INSERT INTO repositories (...)
```

PMOIS does **not** call the GitLab API to create a group, project, or branch. This was in the original draft (`Auto-create GitLab Group`, `Auto-create branches`) and is explicitly removed per CTO instruction §8 — it is listed only as a **future proposal**, not baseline (Section 5).

## 3. Sync (read-only)

If/when a GitLab Personal Access Token is configured for a workspace, an optional sync job may `GET` repository metadata (default branch, last commit) from the GitLab API to refresh `repositories` columns. This is read-only — it never writes to GitLab. Not required for M0; documented here as the intended shape if implemented in M1+.

## 4. Secrets

`repositories.credential_reference` stores a pointer only (e.g. an environment variable name or a key into whatever secret store is actually chosen later — see `16-Proposed-Technology-Stack-with-Rationale.md`). No GitLab token is ever stored as a plain column value. This matches the constraint stated in the original brief and is unchanged.

## 5. Explicitly Marked as Future Proposal (not M0/M1 baseline)

| Feature | Status |
|---|---|
| Auto-create GitLab group per workspace | Proposal — removed from baseline |
| Auto-create branches (main/develop/release) on registration | Proposal — removed from baseline |
| Automated branch protection rule configuration via GitLab API | Proposal — removed from baseline |
| GitLab webhook → automatic `revisions` update on push | Proposal — plausible M2 feature, not required for M0 |

Registering a repository in M0 means: CTO/Dev types in the URL and branch names of a repository that already exists; PMOIS stores that metadata for reference and linking from the Project Detail "Repository" tab. Nothing more.

---

*Document Version: 2.0 (Revision 1)*
*Status: Revised per CTO Review — automation claims removed, marked as manual registration*
*Date: 2026-08-30*