# API Contract: Registration Endpoint

**Feature**: 003-traveler-registration
**Base Path**: `/api/public/auth`
**Authentication**: None (public endpoint)
**Rate Limit**: 10 requests/minute per IP (`throttle:auth`)

---

## POST /register

**Description**: Create a new traveler account (FR-001)

**Request Body**:
```json
{
  "name": "string, required, max 255",
  "email": "string, required, valid email, unique",
  "password": "string, required, min 8, mixed case + number",
  "locale": "string, optional, enum: en|es|it, default: en"
}
```

**Note**: No `password_confirmation` field (per clarification session 2026-04-18).

**Success Response** (201 Created):
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Traveler",
      "email": "jane@example.com",
      "role": "traveler",
      "locale": "en",
      "email_verified": false,
      "created_at": "2026-04-18T10:00:00Z",
      "last_login_at": null
    },
    "token": "1|abcdef123456..."
  }
}
```

**Error Responses**:

### 422 Unprocessable Entity — Validation Errors
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

### 429 Too Many Requests — Rate Limited
```json
{
  "error": {
    "code": "RATE_LIMITED",
    "message": "Too many requests. Please try again later.",
    "details": {
      "retry_after_seconds": 60
    }
  }
}
```

**Side Effects** (non-blocking, do not affect response time):
1. Verification email queued to Redis (delivered via SMTP)
2. `registration` entry created in `auth_audit_logs`
3. Guest bookings with matching email linked to new account (if bookings table exists)

**Response Headers**:
- `Content-Type: application/json`
- `X-RateLimit-Limit: 10`
- `X-RateLimit-Remaining: [n]`
