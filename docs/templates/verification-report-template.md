# Verification Report: {Subject}

**Document type:** Verification Report  
**Document code:** VER-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending  
**Approver:** Pending  
**Project:** {project name}  
**Feature / Phase:** {describe what is being verified}  
**Related documents:** {links to the SEC, ARCH, or API document being verified — required}

---

> *Instructions: Copy this template to `docs/reports/ver-{subject}.md`. Sections 1 through 6 should be completed before running verification. Section 5 (Results table) and Section 6 (Observations) are filled in during and after verification. Section 7 (Conclusion) is completed after all scenarios have been executed.*
>
> *A Verification Report is not a test plan. It records what was actually tested and what was actually observed. Write it as a record of events, not as a specification of intent.*

---

## 1. Test Environment

> *[REQUIRED] Describe the environment in which verification was performed. Be specific enough that the test could be reproduced.*

| Item | Value |
|------|-------|
| Environment | {Production / Staging / Local} |
| Server | {hostname or IP} |
| Date of verification | {YYYY-MM-DD} |
| Framework | |
| PHP version | |
| Database | |
| Workspace | {workspace code} |
| Verified by | {name} |

### Pre-conditions

> *List every condition that must be true before any test scenario can run. Include: which migrations must have been applied, which seed data must exist, which tokens must have been created, and any configuration that must be in place.*

1. {pre-condition}

---

## 2. Test Scenarios

> *[REQUIRED] Provide an overview table of all scenarios. Use the ID convention: `PASS-{nn}` for expected successes, `FAIL-{nn}` for expected rejections.*

| Test ID | Description | Expected HTTP |
|---------|-------------|---------------|
| PASS-01 | | |
| FAIL-01 | | |

### Token and resource references

> *Define the placeholders used in test scenarios below so the reader does not need to look them up.*

| Placeholder | Meaning |
|-------------|---------|
| `{TOKEN_NAME}` | |
| `{RESOURCE_ID}` | |

---

## 3. PASS Cases

> *[REQUIRED] Document every scenario expected to succeed. Use one subsection per scenario.*

---

### PASS-{nn}: {Short description}

**Objective:**

> *One sentence: what property does this test confirm?*

**Request:**

```http
{METHOD} /api/v1/{path}
Authorization: Bearer {TOKEN}
Content-Type: application/json

{request body if applicable}
```

**Expected response: HTTP {status}**

```json
{
  "success": true,
  "data": {
  },
  "error": null,
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**

- HTTP status code is `{expected}`
- `success` is `true`
- {additional specific field checks}

---

## 4. FAIL Cases

> *[REQUIRED] Document every scenario expected to be rejected. Use one subsection per scenario. FAIL cases are as important as PASS cases — they verify that the security enforcement is actually working.*

---

### FAIL-{nn}: {Short description}

**Objective:**

> *One sentence: what denial or rejection does this test confirm?*

**Request:**

```http
{METHOD} /api/v1/{path}
Authorization: Bearer {TOKEN}
Content-Type: application/json

{request body if applicable}
```

**Expected response: HTTP {status}**

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "{ERROR_CODE}",
    "message": "{exact expected message}",
    "details": []
  },
  "meta": { "timestamp": "..." }
}
```

**Pass criteria:**

- HTTP status code is `{expected}`
- `success` is `false`
- `error.code` is `"{ERROR_CODE}"`
- `error.message` is `"{exact expected message}"`
- {additional checks, e.g., "No record written to the database"}

---

## 5. Verification Results

> *[REQUIRED] Fill in the Actual HTTP column and the Result column after executing each scenario on the target environment. Do not fill in this table from memory or expectation — record what you actually observed.*

| Test ID | Description | Expected HTTP | Actual HTTP | Result |
|---------|-------------|---------------|-------------|--------|
| PASS-01 | | | | PASS / FAIL |
| FAIL-01 | | | | PASS / FAIL |

**Overall result:**

- [ ] PASS — all scenarios matched expected HTTP status
- [ ] FAIL — one or more scenarios did not match

**Verified by:** {name}  
**Verification date:** {YYYY-MM-DD}

---

## 6. Observations

> *[REQUIRED] Record anything unexpected, any behaviour that differed from the design document, any edge case discovered during testing, and any manual checks performed in addition to the HTTP status codes (e.g., database state, audit trail records, `last_used_at` updates). If verification was entirely clean with no deviations, state "None — all scenarios matched expected behaviour exactly."*

---

## 7. Conclusion and Sign-off

> *[REQUIRED] Written after verification is complete. State whether the feature is cleared for use, whether any open items remain, and what action is required before the overall result can be considered PASS.*

**Feature status:** {Cleared for production use / Blocked — see open items}

**Open items:**

| # | Description | Owner | Target date |
|---|-------------|-------|-------------|
| | | | |

> *If there are no open items, replace the table with "None."*

**Signed off by:** {name}  
**Sign-off date:** {YYYY-MM-DD}
