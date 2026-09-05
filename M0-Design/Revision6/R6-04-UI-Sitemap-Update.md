# PMOIS v2 — Web UI Sitemap Update — Revision 6

**Supersedes:** `M0-Design/11-Web-UI-Sitemap-Screen-List.md` §2–3 (adds screens; nothing removed). Frontend framework remains Open Decision Q09; the updated sitemap applies to either Option A (server-rendered) or Option B (SPA).

## 1. New / Changed Screens

### 1.1 Project Detail — from 13 to **17 tabs**

| Tab | Path | Key Data | Added |
|---|---|---|---|
| Overview | `/projects/:id` | Basics, status, mode, **profile completeness meter**, progress meter (separate visual elements — CTO Requirement #5) | changed |
| Milestone | `/projects/:id/milestones` | (unchanged) | |
| Timeline | `/projects/:id/timeline` | (unchanged — PMO activity feed; **does not** include Release history) | |
| Repository | `/projects/:id/repositories` | Repo list per git provider, branches, status | changed |
| **Tech Stack** | `/projects/:id/tech-stack` | `project_technology_stack` by layer | **new** |
| **Environment** | `/projects/:id/environments` | `project_environments` (Dev/UAT/Prod; no secrets shown) | **new** |
| **Dependencies** | `/projects/:id/dependencies` | Depends On / Blocked By lists + mini graph | **new** |
| **Releases** | `/projects/:id/releases` | `project_releases` history (alpha→hotfix) | **new** |
| CTO Review / Commit / Test / Deployment | (4 tabs unchanged) | | |
| AI Team | `/projects/:id/ai` | + provider badge per agent | changed |
| Team | `/projects/:id/team` | Human assignments **with history** (`project_member_assignments`) | changed |
| Risk / Known Issue / Decision / Governance | (4 tabs unchanged) | | |

### 1.2 New top-level screens

| Screen | Path | Access | Description |
|---|---|---|---|
| Workspace Settings | `/workspaces/:id/settings` | `workspace.settings.manage` (ADMIN/is_platform_admin) | Defaults: CTO, Dev, Governance version, Git Provider, Permission preset, Default template, Default development mode |
| Project Templates | `/project-templates` | `project.template.manage` | Template list/create/edit (payload editor with preview), set default |
| AI Provider Registry | `/ai-providers` | is_platform_admin | Global provider list (OpenAI, Anthropic, Google, Microsoft, Human) |
| AI Agent Registry | `/ai-consumers` | CTO/is_platform_admin | Agents **with provider column**; create requires provider |
| Git Provider Registry | `/git-providers` | is_platform_admin | Global provider list (GitLab, …) |
| Dependency Graph (Portfolio) | `/dashboard/dependencies` | All roles (read-only) | Workspace-wide dependency graph for Portfolio Dashboard |

### 1.3 Changed creation form

Project creation (`/projects/new`) — **CEO fills minimal fields**; everything else optional/deferred:

- Always shown (7 fields): Workspace, Name, Code/Abbrev, CTO (pre-filled from workspace default), Dev (pre-filled), Development Mode (pre-filled), optional Template selector (defaults to workspace default template).
- Collapsible "Advanced (CTO/Dev can fill later)" hint listing what will be added progressively: Repository, Tech Stack, Environments, Releases, Dependencies.

## 2. Dashboard additions (CTO Requirement #3 + #8)

- Portfolio Dashboard gains: **Development Mode breakdown** (Manual / AI Assisted / AI Dev Auto), **Dependency graph widget**, **Profile Completeness column** next to Progress column (clearly labeled separately).

## 3. Access control deltas

| Screen | CEO | CTO | Dev | Viewer |
|---|---|---|---|---|
| Workspace Settings | Yes | No | No | No |
| Project Templates | Yes | No | No | No |
| Provider Registries | Yes | read (agents: manage) | read | No |
| Project tech tabs (Stack/Env/Dep/Release) | Yes | Yes | Yes (per matrix) | read-only |
| Dependency Graph | Yes | Yes | Yes | Yes |

---

*Document Version: 6.0 — For CTO Review (Design Freeze candidate)*
*Date: 2026-09-05*
