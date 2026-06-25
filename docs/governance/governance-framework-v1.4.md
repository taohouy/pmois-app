# Governance Framework v1.4

**Document type:** Governance Framework  
**Version:** 1.4  
**Status:** Active  
**Effective date:** 2026-06-25  
**Approved by:** CTO  
**Previous version:** v1.3

---

## Contents

1. [Purpose and Scope](#1-purpose-and-scope)
2. [Core Principles (carried from v1.3)](#2-core-principles-carried-from-v13)
3. [What Is New in v1.4](#3-what-is-new-in-v14)
4. [Documentation Standards](#4-documentation-standards)
   - 4.1 [Document Types](#41-document-types)
   - 4.2 [Required Sections per Document Type](#42-required-sections-per-document-type)
   - 4.3 [Naming Convention](#43-naming-convention)
   - 4.4 [Status Taxonomy](#44-status-taxonomy)
   - 4.5 [Repository Location](#45-repository-location)
   - 4.6 [Minimum Documentation Requirements](#46-minimum-documentation-requirements)
   - 4.7 [Template Index](#47-template-index)
5. [Review and Approval Process](#5-review-and-approval-process)
6. [Version History](#6-version-history)

---

## 1. Purpose and Scope

This framework defines the governance standards for all software projects within the organisation. It applies to every project from initial design through production operation.

Governance is not a gate. It is a practice that makes intent legible, decisions traceable, and systems maintainable by people who were not present when the decisions were made.

This framework applies to:
- All internal projects developed by or on behalf of the organisation
- All external integrations with organisation systems
- All infrastructure changes that affect production systems

This framework does not apply to:
- Proof-of-concept work explicitly scoped as non-production
- Personal development environments

---

## 2. Core Principles (carried from v1.3)

These principles are unchanged from v1.3. They are reproduced here so that v1.4 is a complete standalone reference.

### Documentation First

Design and document before implementing. A feature that cannot be explained in a document is not ready to be built. The document does not need to be long; it needs to be clear.

### Architecture Traceability

Every architectural decision must be traceable to a document. If the reason for a structural choice is not written down, it will be lost when the person who made it is no longer available.

### Decision Traceability

Every significant decision — what to build, what not to build, which approach to take, which approach to reject — must be recorded. Use an Architecture Decision Record (ADR) for decisions that affect the system architecture, API surface, or security posture.

### CTO Review

All implementations that affect the API surface, database schema, security model, or authentication system require CTO review before deployment to production. CTO review is signalled by a completed review document, not merely by verbal approval.

### PMO Review

All Phases and major deliverables require PMO review for completeness, traceability, and alignment with project scope. PMO review confirms that documentation is present and complete, not that the technical content is correct.

### Reuse Existing Architecture

New implementations must reuse existing patterns, middleware, repositories, and abstractions before introducing new ones. Introducing a new pattern requires explicit justification in the design document.

---

## 3. What Is New in v1.4

v1.4 adds one new section to the framework: **Documentation Standards**.

The core principles of v1.3 are unchanged. v1.4 does not alter any governance requirements; it makes the documentation requirement more concrete by specifying:

- What document types exist
- What sections each type must contain
- How documents are named
- Where documents live in the repository
- What the minimum documentation set is for each type of work

The intent is that any developer, regardless of experience with this organisation's conventions, can produce a correct and complete document by following the templates without needing to invent or negotiate a structure.

---

## 4. Documentation Standards

### 4.1 Document Types

| Code | Document Type | Purpose |
|------|--------------|---------|
| `ARCH` | Architecture Design | Describe a system component, layer, or integration pattern |
| `SEC` | Security Design | Describe a security mechanism, threat model, or enforcement change |
| `API` | API Design | Specify endpoints, request/response shapes, error codes, and versioning |
| `DB` | Database Design | Describe schema, migrations, indexing strategy, and data relationships |
| `VER` | Verification Report | Record test scenarios, expected behaviour, actual results, and sign-off |
| `DEP` | Deployment Report | Record what was deployed, when, to which environment, and any incidents |
| `ADR` | Architecture Decision Record | Record a single significant decision and the reasoning behind it |
| `MIG` | Migration Report | Record database migrations applied, their effect, and rollback procedure |

### 4.2 Required Sections per Document Type

#### Architecture Design (`ARCH`)

| # | Section | Required |
|---|---------|----------|
| 1 | Objective | Yes |
| 2 | Context and Background | Yes |
| 3 | Design | Yes |
| 4 | Component Diagram or Data Flow | Yes |
| 5 | Interface Contracts | Yes |
| 6 | Dependencies | Yes |
| 7 | Security Considerations | Yes |
| 8 | Performance Considerations | Optional |
| 9 | Backward Compatibility | Yes |
| 10 | Risks and Limitations | Yes |
| 11 | Future Considerations | Optional |

#### Security Design (`SEC`)

| # | Section | Required |
|---|---------|----------|
| 1 | Objective | Yes |
| 2 | Background | Yes |
| 3 | Existing Architecture | Yes |
| 4 | Security Design | Yes |
| 5 | Database Changes | Yes (or "None") |
| 6 | Authorization Flow | Yes |
| 7 | Permission Matrix | Yes |
| 8 | Middleware Changes | Yes (or "None") |
| 9 | API Behavior | Yes |
| 10 | Response Envelope Compatibility | Yes |
| 11 | Audit Trail Impact | Yes |
| 12 | Workspace Isolation Considerations | Yes |
| 13 | Backward Compatibility | Yes |
| 14 | Risks and Limitations | Yes |
| 15 | Future Considerations | Optional |

#### API Design (`API`)

| # | Section | Required |
|---|---------|----------|
| 1 | Objective | Yes |
| 2 | Background | Yes |
| 3 | Base URL and Versioning | Yes |
| 4 | Authentication | Yes |
| 5 | Request Format | Yes |
| 6 | Response Envelope | Yes |
| 7 | Endpoints | Yes |
| 8 | Error Codes | Yes |
| 9 | Idempotency | Yes (or "Not applicable") |
| 10 | Rate Limiting | Yes (or "Not applicable") |
| 11 | Changelog | Yes |

#### Database Design (`DB`)

| # | Section | Required |
|---|---------|----------|
| 1 | Objective | Yes |
| 2 | Background | Yes |
| 3 | Entity-Relationship Summary | Yes |
| 4 | Table Definitions | Yes |
| 5 | Index Strategy | Yes |
| 6 | Foreign Key Relationships | Yes |
| 7 | Migration Plan | Yes |
| 8 | Rollback Procedure | Yes |
| 9 | Data Volume Estimates | Optional |
| 10 | Risks and Limitations | Yes |

#### Verification Report (`VER`)

| # | Section | Required |
|---|---------|----------|
| 1 | Test Environment | Yes |
| 2 | Test Scenarios | Yes |
| 3 | PASS Cases | Yes |
| 4 | FAIL Cases | Yes |
| 5 | Verification Results Table | Yes |
| 6 | Observations | Yes |
| 7 | Conclusion and Sign-off | Yes |

#### Deployment Report (`DEP`)

| # | Section | Required |
|---|---------|----------|
| 1 | Deployment Summary | Yes |
| 2 | Pre-deployment Checklist | Yes |
| 3 | Deployment Steps | Yes |
| 4 | Post-deployment Verification | Yes |
| 5 | Rollback Procedure | Yes |
| 6 | Incidents and Observations | Yes (or "None") |
| 7 | Sign-off | Yes |

#### Architecture Decision Record (`ADR`)

| # | Section | Required |
|---|---------|----------|
| 1 | Title and ID | Yes |
| 2 | Status | Yes |
| 3 | Context | Yes |
| 4 | Decision | Yes |
| 5 | Consequences | Yes |
| 6 | Alternatives Considered | Yes |
| 7 | Related Decisions | Optional |

#### Migration Report (`MIG`)

| # | Section | Required |
|---|---------|----------|
| 1 | Migration Summary | Yes |
| 2 | Environment | Yes |
| 3 | Migration Files | Yes |
| 4 | Pre-migration State | Yes |
| 5 | Migration Steps Executed | Yes |
| 6 | Post-migration Verification | Yes |
| 7 | Rollback Procedure | Yes |
| 8 | Incidents and Observations | Yes (or "None") |
| 9 | Sign-off | Yes |

---

### 4.3 Naming Convention

#### File names

```
{document-type-code-lowercase}-{kebab-case-subject}.md
```

Examples:
- `sec-project-scoped-token-enforcement.md`
- `api-inbound-status.md`
- `db-project-status-updates.md`
- `ver-project-scoped-token-enforcement.md`
- `dep-phase-4-production.md`
- `adr-0001-project-scoped-token-enforcement.md`
- `mig-0031-0032-project-scoped-tokens.md`

#### ADR numbering

ADRs are numbered sequentially within a project: `adr-0001-`, `adr-0002-`, and so on. Numbers are not reused even if an ADR is superseded. The superseded ADR is updated to reference its replacement.

#### Version suffix

Documents that are versioned (e.g., revised after major changes) append `-v{n}`:
- `api-inbound-status-v2.md`

The previous version is retained in the repository for traceability. It is not deleted.

---

### 4.4 Status Taxonomy

Every document must declare three status fields in its header block.

#### Document Status

| Value | Meaning |
|-------|---------|
| `Draft` | Work in progress; not ready for review |
| `In Review` | Submitted for CTO or PMO review |
| `Active` | Approved and currently in effect |
| `Superseded` | Replaced by a newer version; link to replacement in header |
| `Deprecated` | No longer in effect; system described has been removed |

#### Review Status

| Value | Meaning |
|-------|---------|
| `Pending` | Review not yet started |
| `In Progress` | Reviewer has started but not completed |
| `Approved` | Reviewer has approved |
| `Changes Requested` | Reviewer has requested changes |

#### Approval Status

| Value | Meaning |
|-------|---------|
| `Pending` | Awaiting CTO or PMO approval |
| `Approved` | Approved by named approver on named date |
| `Rejected` | Rejected; reason noted in document |

#### Header block format

Every document begins with this block, adapted to the document type:

```markdown
**Document type:** {type}
**Document code:** {CODE}-{subject}
**Version:** {n}
**Status:** {Document Status}
**Review status:** {Review Status}
**Approval status:** {Approval Status}
**Date:** {YYYY-MM-DD}
**Author:** {name}
**Reviewer:** {name or Pending}
**Approver:** {name or Pending}
**Project:** {project name}
**Related documents:** {links or None}
```

---

### 4.5 Repository Location

All project documentation lives under the `docs/` directory in the project repository. The directory structure is fixed:

```
docs/
  governance/          ← Governance framework documents
  templates/           ← Document templates (this framework's templates)
  architecture/        ← ARCH documents
  security/            ← SEC documents
  api/                 ← API documents
  database/            ← DB documents
  reports/             ← VER and DEP documents
  adr/                 ← ADR documents
  migrations/          ← MIG documents
```

Documents are never placed outside the `docs/` tree except for the repository root `README.md`.

Documents are never stored outside the repository (e.g., in Google Drive or Confluence) as the sole copy. External copies are permitted for sharing purposes but the repository is the source of truth.

---

### 4.6 Minimum Documentation Requirements

The following table defines what documents are required for each category of work. "Required" means the document must exist and have `Status: Active` before the associated work is merged to the main branch.

| Work Category | Required Documents |
|--------------|-------------------|
| New API endpoint | `API` |
| New database table or column | `DB` |
| New authentication or authorisation mechanism | `SEC`, `VER` |
| New middleware component | `SEC` or `ARCH` (whichever is more appropriate) |
| Schema migration applied to production | `MIG` |
| Production deployment | `DEP` |
| Significant architectural decision | `ADR` |
| Phase completion | `VER` for all features in the phase |

A "significant architectural decision" is defined as any decision that:
- Introduces a new dependency
- Changes the authentication or authorisation model
- Changes the API surface in a breaking way
- Removes or replaces an existing component
- Departs from an existing pattern established in this project

---

### 4.7 Template Index

All templates are located in `docs/templates/`:

| Template file | Document type |
|---------------|--------------|
| `architecture-design-template.md` | Architecture Design (`ARCH`) |
| `security-design-template.md` | Security Design (`SEC`) |
| `api-design-template.md` | API Design (`API`) |
| `database-design-template.md` | Database Design (`DB`) |
| `verification-report-template.md` | Verification Report (`VER`) |
| `deployment-report-template.md` | Deployment Report (`DEP`) |
| `adr-template.md` | Architecture Decision Record (`ADR`) |
| `migration-report-template.md` | Migration Report (`MIG`) |

Copy the relevant template to the appropriate `docs/` subdirectory, rename it according to the naming convention in Section 4.3, and fill in every section. Remove all instruction text (marked in templates with `> *instruction*`) before marking the document as `In Review`.

---

## 5. Review and Approval Process

### Review triggers

A document moves from `Draft` to `In Review` when:
- All required sections are complete
- Instruction placeholders have been removed
- The author believes the content is accurate

### CTO review

CTO review is required for:
- All `SEC` documents
- All `ADR` documents
- All `VER` documents for security features
- Any document the PMO escalates

CTO review is complete when the reviewer updates the document's `Review status` to `Approved` or `Changes Requested`.

### PMO review

PMO review is required for:
- All `VER` documents (phase completions)
- All `DEP` documents
- All `MIG` documents applied to production

PMO review confirms completeness and traceability, not technical correctness.

### Approval

A document reaches `Approval status: Approved` when the named approver confirms it. For `SEC` and `ADR` documents the approver is the CTO. For `DEP` and `MIG` documents the approver is the PMO lead.

---

## 6. Version History

| Version | Date | Change summary |
|---------|------|----------------|
| v1.3 | (prior) | Core governance principles: Documentation First, Architecture Traceability, Decision Traceability, CTO Review, PMO Review, Reuse Existing Architecture |
| v1.4 | 2026-06-25 | Added Documentation Standards (Section 4): document types, required sections, naming convention, status taxonomy, repository location, minimum requirements, template index |
