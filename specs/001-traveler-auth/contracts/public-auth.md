# API Contract: Public Auth Endpoints

**Feature**: 001-traveler-auth
**Base Path**: `/api/public/auth`
**Authentication**: None (public endpoints) unless noted

---

## POST /register

**Description**: Create a new traveler account (FR-001)

**Request Body**:
```json
{
  "name": "string, required, max 255",
  "email": "string, required, valid email, unique",
  "password": "string, required, min 8, mixed case + number",
  "password_confirmation": "string, required, must match password",
  "locale": "string, optional, enum: en|es|it, default: en"
}
```

**Success Response** (201 Created):
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Traveler",
      "email": "jane@example.com",
      "locale": "en",
      "email_verified": false,
      "created_at": "2026-04-13T10:00:00Z"
    },
    "token": "1|abcdef123456..."
  }
}
```

**Error Responses**:
- `422 Unprocessable Entity` — Validation errors (email taken, weak password, missing fields)
  ```json
  {
    "error": {
      "code": "VALIDATION_ERROR",
      "message": "The given data was invalid.",
      "details": {
        "email": ["The email has already been taken."],
        "password": ["The password must be at least 8 characters."]
      }
    }
  }
  ```

**Side Effects**:
- Verification email queued (FR-021)
- `registration` audit log created (FR-025)
- Guest bookings with this email linked to new account (FR-008)

---

## POST /login

**Description**: Sign in with email and password (FR-004)

**Request Body**:
```json
{
  "email": "string, required, valid email",
  "password": "string, required"
}
```

**Success Response** (200 OK):
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Traveler",
      "email": "jane@example.com",
      "locale": "en",
      "email_verified": true,
      "last_login_at": "2026-04-13T10:00:00Z"
    },
    "token": "2|xyz789..."
  }
}
```

**Error Responses**:
- `401 Unauthorized` — Invalid credentials (generic message per FR-020)
  ```json
  {
    "error": {
      "code": "INVALID_CREDENTIALS",
      "message": "Invalid email or password."
    }
  }
  ```
- `429 Too Many Requests` — Account locked out (FR-014)
  ```json
  {
    "error": {
      "code": "ACCOUNT_LOCKED",
      "message": "Too many failed attempts. Please try again later.",
      "details": {
        "retry_after_seconds": 60
      }
    }
  }
  ```

**Side Effects**:
- `login_success` or `login_failed` audit log (FR-025)
- Failed login counter incremented/reset
- Lockout applied if threshold reached (FR-014)

---

## POST /logout

**Description**: Sign out and revoke current session token (FR-005)

**Authorization Layers**:
1. **Authentication**: Required (Valid Bearer token)
2. **Role**: Require `traveler` role
3. **Permission**: Require `auth:logout` permission
4. **Ownership**: Token subject must match the target traveler resource

**Request Body**: None

**Success Response** (200 OK):
```json
{
  "data": {
    "message": "Successfully signed out."
  }
}
```

**Error Responses**:
- `401 Unauthorized` — Token invalid or expired

**Side Effects**:
- Current token revoked (deleted from `personal_access_tokens`)
- Other sessions remain active (FR-013)

---

## POST /forgot-password

**Description**: Request a password reset email (FR-009)

**Request Body**:
```json
{
  "email": "string, required, valid email"
}
```

**Success Response** (200 OK) — Always returns same response regardless of email existence (FR-010):
```json
{
  "data": {
    "message": "If an account exists with this email, a password reset link has been sent."
  }
}
```

**Side Effects**:
- If email exists AND is verified: Reset email queued
- If email exists AND is NOT verified: Verification email queued instead (FR-023)
- If email doesn't exist: No email sent, same response returned
- `password_reset_requested` audit log (FR-025)

---

## POST /reset-password

**Description**: Reset password using token from email link (FR-009)

**Request Body**:
```json
{
  "email": "string, required",
  "token": "string, required",
  "password": "string, required, min 8, mixed case + number",
  "password_confirmation": "string, required, must match"
}
```

**Success Response** (200 OK):
```json
{
  "data": {
    "message": "Password has been reset successfully."
  }
}
```

**Error Responses**:
- `422 Unprocessable Entity` — Invalid/expired token, weak password
  ```json
  {
    "error": {
      "code": "INVALID_TOKEN",
      "message": "This password reset link is invalid or has expired."
    }
  }
  ```

**Side Effects**:
- Password updated
- All reset tokens for this email invalidated (FR-011)
- `password_reset_completed` audit log (FR-025)

---

## GET /verify-email

**Description**: Verify email address via signed URL (FR-022)

**Request Parameters** (URL query params from signed link):
```
/api/public/auth/verify-email/{id}/{hash}?expires=...&signature=...
```

**Success Response** (200 OK):
```json
{
  "data": {
    "message": "Email verified successfully."
  }
}
```

**Error Responses**:
- `403 Forbidden` — Invalid or expired signature
- `409 Conflict` — Email already verified

**Side Effects**:
- `email_verified_at` updated
- `email_verified` audit log (FR-025)

---

## POST /resend-verification

**Description**: Request a new verification email (FR-024)

**Authorization Layers**:
1. **Authentication**: Required (Valid Bearer token)
2. **Role**: Require `traveler` role
3. **Permission**: Require `auth:resend_verification` permission
4. **Ownership**: Token subject must match the target traveler resource

**Request Body**: None

**Success Response** (200 OK):
```json
{
  "data": {
    "message": "Verification email has been sent."
  }
}
```

**Error Responses**:
- `409 Conflict` — Email already verified
- `429 Too Many Requests` — Rate limited (max 3 per hour)

**Side Effects**:
- Verification email queued
- `verification_email_sent` audit log

---

## POST /guest/convert

**Description**: Convert a guest checkout identity to a full account (FR-007)

**Request Body**:
```json
{
  "email": "string, required, valid email",
  "name": "string, required, max 255",
  "password": "string, required, min 8, mixed case + number",
  "password_confirmation": "string, required, must match",
  "booking_reference": "string, required"
}
```

**Success Response** (201 Created):
```json
{
  "data": {
    "user": {
      "id": 5,
      "name": "Jane Guest",
      "email": "jane.guest@example.com",
      "locale": "en",
      "email_verified": false,
      "created_at": "2026-04-13T12:00:00Z"
    },
    "token": "5|convert123...",
    "linked_bookings_count": 2
  }
}
```

**Error Responses**:
- `409 Conflict` — Email already registered
  ```json
  {
    "error": {
      "code": "EMAIL_ALREADY_REGISTERED",
      "message": "An account already exists with this email. Please sign in."
    }
  }
  ```
- `422 Unprocessable Entity` — Validation errors

**Side Effects**:
- User account created
- All guest bookings with this email linked (FR-008)
- Guest identity record updated with `converted_user_id`
- Verification email queued (FR-021)
- `guest_conversion` audit log (FR-025)
