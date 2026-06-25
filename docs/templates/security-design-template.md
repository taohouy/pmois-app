# Security Design: {Subject}

**Document type:** Security Design  
**Document code:** SEC-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending (CTO)  
**Approver:** Pending (CTO)  
**Project:** {project name}  
**Related documents:** {links to related ARCH, API, VER, ADR documents — or None}

---

> *Instructions: Copy this template to `docs/security/sec-{subject}.md`. Fill in every section. Remove all instruction blocks (lines beginning with `> *`) before submitting for review. All 14 required sections must be complete. Section 15 is optional.*
>
> *Security Design documents require CTO review and approval before the associated code is deployed to production.*

---

## 1. Objective

> *[REQUIRED] One to three sentences. What security property does this design establish, enforce, or improve? State the specific threat or requirement that motivated the design.*

---

## 2. Background

> *[REQUIRED] Describe the state of the system before this design. What security property was missing or insufficient? What event, requirement, or CTO decision prompted this work?*

---

## 3. Existing Architecture

> *[REQUIRED] Describe the existing security architecture that this design builds on or modifies. Include: the current authentication mechanism, the current authorisation model, the current middleware chain, and any existing isolation mechanisms. The reader should be able to understand the before-state without reading any other document.*

---

## 4. Security Design

> *[REQUIRED] Describe the security mechanism being introduced. Explain:*
> - *What the mechanism enforces*
> - *Where in the request lifecycle it operates*
> - *What principle it is based on (least-privilege, deny-by-default, defence-in-depth, etc.)*
> - *Why this approach was chosen over alternatives*

---

## 5. Database Changes

> *[REQUIRED] Describe every database change introduced by this design. Include the migration file name, the exact SQL, and the reason for each change. If no database changes are required, state "None."*

---

## 6. Authorization Flow

> *[REQUIRED] Describe the complete request lifecycle from the point a request arrives to the point a response is returned. Include every middleware layer, every check performed, and every possible exit point (rejection or pass-through). A step-by-step narrative with the exact logic from the implementation is required.*

---

## 7. Permission Matrix

> *[REQUIRED] Provide a table listing every relevant combination of token type, route, and expected outcome (PASS or FAIL with HTTP status and reason). This matrix must be complete enough to drive the verification test plan.*

| Token type | Route | Expected result | Reason |
|------------|-------|----------------|--------|
| | | | |

---

## 8. Middleware Changes

> *[REQUIRED] List every middleware that was added, modified, or removed. For each, state: file path, class name, its position in the execution chain, and what changed. If no middleware was changed, state "None."*

---

## 9. API Behavior

> *[REQUIRED] Describe any changes to the API surface introduced by this design. Include: new request fields, new response fields, changed HTTP status codes, and changed error messages. If no API surface changed, state "None."*

---

## 10. Response Envelope Compatibility

> *[REQUIRED] Confirm that all new error and success responses produced by this design conform to the standard API response envelope. Show a representative example of each new response shape.*

---

## 11. Audit Trail Impact

> *[REQUIRED] Describe how this design affects the audit trail. State clearly: which events are logged, which are not, and why. Include the `action` value written for each logged event.*

---

## 12. Workspace Isolation Considerations

> *[REQUIRED] Describe how this design interacts with the workspace isolation model. Confirm that the existing workspace scoping guarantees (`applyWorkspaceScope`, `assertWorkspaceMatch`) are preserved. If this design adds a new isolation layer, describe how the two layers interact.*

---

## 13. Backward Compatibility

> *[REQUIRED] List every existing token, endpoint, or behaviour that is affected by this design. For each, confirm whether the existing behaviour is preserved. If the document claims "None," it must justify that claim explicitly.*

---

## 14. Risks and Limitations

> *[REQUIRED] List all known risks, trade-offs, and limitations of this design. Be specific. Include any security property that is not fully enforced by the current implementation and should be addressed in a future hardening step.*

---

## 15. Future Considerations

> *[OPTIONAL] List security improvements that were deliberately deferred. Explain the reason for deferral and any interim mitigations.*
