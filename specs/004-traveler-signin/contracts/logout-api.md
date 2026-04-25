# API Contract: POST /api/public/auth/logout

## Endpoint

```
POST /api/public/auth/logout
```

## Description

Revokes the current Sanctum personal access token, effectively signing the traveler out from the current device/session. Other active sessions for the same account remain unaffected.

## Middleware

- `auth:sanctum` — Requires valid Bearer token

## Request

### Headers

| Header | Value | Required |
|--------|-------|----------|
| `Authorization` | `Bearer <token>` | Yes |
| `Content-Type` | `application/json` | Yes |
| `Accept` | `application/json` | Yes |

### Body

No body required.

## Responses

### 204 No Content — Successful Logout

No response body.

**Side effects**:
- The specific Sanctum token used in the `Authorization` header is deleted from `personal_access_tokens`
- Other tokens belonging to the same user remain valid
- Client should clear local auth state (user + token)

---

### 401 Unauthorized — Missing or Invalid Token

```json
{
  "message": "Unauthenticated."
}
```

**Returned when**:
- `Authorization` header is missing
- Token is invalid or already revoked
- Token has expired

## Error Codes

| HTTP Status | Code | Meaning |
|-------------|------|---------|
| 204 | — | Success (token revoked) |
| 401 | `unauthenticated` | Missing, invalid, or expired token |

## State Diagram

```
Request Received
    │
    ▼
Check Bearer Token
    │
    ├── Missing/Invalid ──401──→ Exit
    │
    ▼
Retrieve Authenticated User (auth:sanctum)
    │
    ▼
Get Current Access Token ($request->user()->currentAccessToken())
    │
    ▼
Delete Token (token->delete())
    │
    ▼
Return 204 No Content
```

## Multi-Session Behavior

| Scenario | Token A (Phone) | Token B (Laptop) | Result |
|----------|-----------------|-------------------|--------|
| Logout from Phone | Revoked | Active | Phone signed out, Laptop still signed in |
| Logout from Laptop | Active | Revoked | Laptop signed out, Phone still signed in |

This is verified by the test: `test_other_sessions_remain_active_after_logout`.
