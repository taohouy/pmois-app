# API Design: {Subject}

**Document type:** API Design  
**Document code:** API-{subject-in-kebab-case}  
**Version:** 1  
**Status:** Draft  
**Review status:** Pending  
**Approval status:** Pending  
**Date:** {YYYY-MM-DD}  
**Author:** {name}  
**Reviewer:** Pending  
**Approver:** Pending  
**Project:** {project name}  
**Related documents:** {links to related ARCH, SEC, DB documents — or None}

---

> *Instructions: Copy this template to `docs/api/api-{subject}.md`. Fill in every section. Remove all instruction blocks (lines beginning with `> *`) before submitting for review. Document every endpoint in Section 7, including request body, response body, and all possible error responses.*

---

## 1. Objective

> *[REQUIRED] What does this API surface enable? Who is the intended consumer (human user, internal service, external integration)?*

---

## 2. Background

> *[REQUIRED] What requirement or phase of work introduced this API? Reference the relevant design decision or CTO decision.*

---

## 3. Base URL and Versioning

> *[REQUIRED] State the base URL and versioning strategy.*

```
Base URL: /api/v1
```

> *If this document covers only a subset of routes, state which prefix they share.*

---

## 4. Authentication

> *[REQUIRED] Describe the authentication mechanism for these endpoints.*

All endpoints require a valid bearer token in the `Authorization` header:

```
Authorization: Bearer {raw_token}
```

> *State any exceptions (e.g., public endpoints that do not require authentication).*

> *State which token types are accepted (workspace-level, project-scoped, AI consumer) and any restrictions that apply to each.*

---

## 5. Request Format

> *[REQUIRED] Describe the request format requirements that apply to all endpoints in this document.*

- **Content-Type:** `application/json` (for all POST, PUT, PATCH requests)
- **Character encoding:** UTF-8
- **Date format:** ISO 8601 (`YYYY-MM-DDTHH:mm:ssZ`)

> *List any other global constraints.*

---

## 6. Response Envelope

> *[REQUIRED] Show the standard response envelope used by all endpoints. This should match the `ApiResponse` class in the implementation.*

All responses follow this envelope:

**Success:**

```json
{
  "success": true,
  "data": {},
  "error": null,
  "meta": {
    "timestamp": "2026-06-25T10:00:00+00:00"
  }
}
```

**Error:**

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable description",
    "details": []
  },
  "meta": {
    "timestamp": "2026-06-25T10:00:00+00:00"
  }
}
```

---

## 7. Endpoints

> *[REQUIRED] Document every endpoint. Use one subsection per endpoint.*

---

### 7.{n} {METHOD} {path}

> *Repeat this subsection for each endpoint.*

**Method:** {GET | POST | PUT | PATCH | DELETE}  
**Path:** `/api/v1/{path}`  
**Permission:** `{permission.code}`  
**Token types accepted:** {workspace-level | project-scoped | AI consumer | all}

**Description:**

> *One to three sentences describing what this endpoint does.*

**Path parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `{name}` | integer | Yes | |

**Query parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| | | | | |

**Request body:**

```json
{
  "field_name": "value"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `field_name` | string | Yes | |

**Response: {HTTP status} {Status text}**

```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "error": null,
  "meta": { "timestamp": "..." }
}
```

**Error responses:**

| HTTP | Error code | Condition |
|------|------------|-----------|
| 400 | `VALIDATION_ERROR` | Missing or invalid request body field |
| 401 | `UNAUTHORIZED` | Missing, invalid, revoked, or expired token |
| 403 | `FORBIDDEN` | Token does not have the required permission |
| 404 | `NOT_FOUND` | Resource not found in this workspace |
| 422 | `VALIDATION_ERROR` | |

---

## 8. Error Codes

> *[REQUIRED] List all error codes used across the endpoints in this document.*

| Error code | HTTP status | Description |
|------------|-------------|-------------|
| `UNAUTHORIZED` | 401 | Authentication failed |
| `FORBIDDEN` | 403 | Authenticated but not authorised |
| `NOT_FOUND` | 404 | Resource does not exist in this workspace |
| `VALIDATION_ERROR` | 422 | Request body fails validation |
| `CONFLICT` | 409 | Request conflicts with existing state |

> *Add any additional codes introduced by these endpoints.*

---

## 9. Idempotency

> *[REQUIRED] Describe the idempotency behaviour of mutating endpoints. If an idempotency key mechanism is used, document it here. If idempotency is not applicable (e.g., this document covers read-only endpoints only), state "Not applicable."*

---

## 10. Rate Limiting

> *[REQUIRED] State whether rate limiting applies to these endpoints. If not currently implemented, state "Not implemented. Planned for {version or phase}." Do not omit this section.*

---

## 11. Changelog

> *[REQUIRED] Record every version of this API document and what changed.*

| Version | Date | Change |
|---------|------|--------|
| 1 | {YYYY-MM-DD} | Initial specification |
