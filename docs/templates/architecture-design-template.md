# Architecture Design: {Subject}

**Document type:** Architecture Design  
**Document code:** ARCH-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending  
**Approver:** Pending  
**Project:** {project name}  
**Related documents:** {links to related SEC, API, DB, ADR documents — or None}

---

> *Instructions: Copy this template to `docs/architecture/arch-{subject}.md`. Fill in every section. Remove all instruction blocks (lines beginning with `> *`) before submitting for review. Sections marked [REQUIRED] must be complete before the document can move to `In Review`. Sections marked [OPTIONAL] may be omitted with a written justification.*

---

## 1. Objective

> *[REQUIRED] One to three sentences. What does this design accomplish and why does it exist? Write this section so that someone unfamiliar with the project can understand the purpose without reading the rest of the document.*

---

## 2. Context and Background

> *[REQUIRED] Describe the situation that makes this design necessary. What was the state before this design? What problem or requirement prompted it? Reference related documents, prior phases, or CTO decisions where relevant.*

---

## 3. Design

> *[REQUIRED] Describe the design in detail. Explain what was built, how it works, and why this approach was chosen over alternatives. If the design reuses existing patterns from this project, name them explicitly. If it introduces a new pattern, justify why the existing ones were not sufficient.*

### 3.1 Overview

### 3.2 Key Components

> *List the main classes, modules, services, or layers involved. For each, give the file path and its responsibility.*

| Component | File path | Responsibility |
|-----------|-----------|----------------|
| | | |

### 3.3 Data Flow

> *Describe how data moves through the system for the primary use case. A numbered list of steps is acceptable if a diagram is not practical.*

---

## 4. Component Diagram or Data Flow

> *[REQUIRED] Provide a diagram (ASCII, Mermaid, or linked image) showing how the components relate to each other and how a request flows through the system. If a diagram is genuinely not useful for this design, replace this section with a detailed step-by-step narrative.*

```
{diagram here}
```

---

## 5. Interface Contracts

> *[REQUIRED] Define the public interfaces introduced or changed by this design. For each interface, list the method signatures, parameters, and return types. If this design has no public interfaces (e.g., it is purely internal), state that explicitly.*

---

## 6. Dependencies

> *[REQUIRED] List every external dependency introduced or relied upon by this design.*

| Dependency | Version | Purpose |
|------------|---------|---------|
| | | |

> *Internal dependencies (other modules or components in this project):*

| Component | File path | How it is used |
|-----------|-----------|----------------|
| | | |

---

## 7. Security Considerations

> *[REQUIRED] Describe the security implications of this design. Consider: authentication, authorisation, data isolation, input validation, audit logging, and least-privilege. If this design has no security implications, explain why.*

---

## 8. Performance Considerations

> *[OPTIONAL] Describe any performance implications. If load patterns, database query counts, or caching behaviour are affected, note them here. Omit this section only if the design has no meaningful performance implications.*

---

## 9. Backward Compatibility

> *[REQUIRED] Describe the impact on existing functionality. List every existing endpoint, module, or behaviour that is changed. If there is no impact, state "None — this design adds new functionality without modifying existing components."*

---

## 10. Risks and Limitations

> *[REQUIRED] List known risks, trade-offs, or limitations of this design. Be specific. "It might be slow" is not acceptable; "the N+1 query pattern on the project listing endpoint may become a bottleneck above 500 projects" is acceptable. Include any validation or constraint that is not enforced by the current implementation.*

---

## 11. Future Considerations

> *[OPTIONAL] List improvements or extensions that were deliberately deferred from this design. Explain why they were deferred. This section helps future developers understand what is intentionally incomplete.*
