# API Contract: Authenticated Account Endpoints

**Feature**: 001-traveler-auth
**Base Path**: `/api/public/account`
**Authentication**: Required (Bearer token) for all endpoints

---

## GET /profile

**Description**: Get the authenticated traveler's profile

**Success Response** (200 OK):
```json
{
  "data": {
    "id": 1,
    "name": "Jane Traveler",
    "email": "jane@example.com",
    "phone": null,
    "locale": "en",
    "email_verified": true,
    "created_at": "2026-04-13T10:00:00Z",
    "last_login_at": "2026-04-13T14:00:00Z"
  }
}
```

**Error Responses**:
- `401 Unauthorized` — Token invalid, expired, or missing

---

## PUT /profile

**Description**: Update the authenticated traveler's profile

**Request Body** (all fields optional):
```json
{
  "name": "string, optional, max 255",
  "phone": "string, optional, max 50",
  "locale": "string, optional, enum: en|es|it"
}
```

**Success Response** (200 OK):
```json
{
  "data": {
    "id": 1,
    "name": "Jane Updated",
    "email": "jane@example.com",
    "phone": "+1234567890",
    "locale": "es",
    "email_verified": true,
    "created_at": "2026-04-13T10:00:00Z",
    "last_login_at": "2026-04-13T14:00:00Z"
  }
}
```

**Error Responses**:
- `401 Unauthorized` — Not authenticated
- `422 Unprocessable Entity` — Validation errors

**Notes**:
- Email changes are NOT handled by this endpoint (separate flow with verification required per edge case)
- Phone is not required for registered accounts (only at guest checkout)

---

## PUT /change-password

**Description**: Change password while signed in (FR-027)

**Request Body**:
```json
{
  "current_password": "string, required",
  "password": "string, required, min 8, mixed case + number",
  "password_confirmation": "string, required, must match"
}
```

**Success Response** (200 OK):
```json
{
  "data": {
    "message": "Password changed successfully."
  }
}
```

**Error Responses**:
- `401 Unauthorized` — Not authenticated
- `403 Forbidden` — Current password is incorrect
  ```json
  {
    "error": {
      "code": "INCORRECT_PASSWORD",
      "message": "The current password is incorrect."
    }
  }
  ```
- `422 Unprocessable Entity` — New password validation errors

**Side Effects**:
- Password updated (bcrypt re-hashed)
- `password_changed` audit log (FR-025)
- All reset tokens invalidated (FR-011)
- Current session preserved; other sessions remain unaffected

---

## GET /sessions

**Description**: List the traveler's active sessions (informational)

**Success Response** (200 OK):
```json
{
  "data": [
    {
      "id": 1,
      "name": "web-session",
      "last_used_at": "2026-04-13T14:00:00Z",
      "created_at": "2026-04-13T10:00:00Z",
      "is_current": true
    },
    {
      "id": 2,
      "name": "web-session",
      "last_used_at": "2026-04-12T09:00:00Z",
      "created_at": "2026-04-11T08:00:00Z",
      "is_current": false
    }
  ]
}
```

**Notes**:
- Token values are never returned (security)
- `is_current` flag marks the session making this request
