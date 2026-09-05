# PMOIS v2 — Repository Registry Design — Revision 6

**Supersedes:** `M0-Design/10-GitLab-Repository-Design.md` §1–2 (provider model). R5's manual-registration principle, read-only sync stance, secrets rule (§3–5) remain fully in force.

---

## 1. What changed and why

R5 hardcoded GitLab (`gitlab_url` column). CTO Requirement #4 requires a proper Git Provider dimension while keeping all R5 fields:

| R5 field | R6 status |
|---|---|
| Repository Name | ✅ `repositories.repository_name` (existing) |
| Git Provider | 🆕 `repositories.git_provider_id` → `git_providers` (seeded: GitLab) |
| Repository URL | ✏️ `gitlab_url` renamed → `repository_url` (values unchanged) |
| Default Branch | ✅ `repositories.default_branch` (existing) |
| Development Branch | ✅ `repositories.development_branch` (existing) |
| Release Branch | ✅ `repositories.release_branch` (existing) |
| Production Branch | ✅ `repositories.production_branch` (existing) |
| Repository Status | ✅ `repositories.repository_status` (existing: `active`/`archived`) |
| Multiple repos per project | ✅ `repositories.project_id` is 1:N (existing, `repository_type` main/supporting/docs/test/infra/custom) |

## 2. Registration Flow (manual — unchanged principle)

```
CTO/Dev created the repository in the Git provider themselves (out of PMOIS control).
POST /api/v1/repositories   (permission: repository.manage)
{
  "project_id": 892,
  "git_provider_id": 1,              -- resolved default: wds.default_git_provider_id ?? 'gitlab'
  "repository_type": "main",
  "repository_name": "mar-00892",
  "repository_url": "https://gitlab.com/pmois/mar-00892",
  "default_branch": "main",
  "development_branch": "develop",
  "release_branch": "release/*",
  "production_branch": "main"
}
→ INSERT INTO repositories (...)
```

PMOIS does **not** call any Git provider API to create groups/projects/branches — auto-provisioning stays a future proposal exactly as R5 §5 ruled.

## 3. Secrets (unchanged, restated)

`repositories.credential_reference` (and `project_environments.credential_reference`) store a **pointer** to a secret store only. No token/password is ever stored as a column value.

## 4. Sync (read-only, unchanged shape)

Optional workspace-level PAT per `git_providers` entry → read-only metadata refresh (default branch, last commit). Not required for M1; same conditions as R5 §3.
