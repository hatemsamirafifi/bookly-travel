# API Contract: Authentication Endpoints

**Feature**: 010-public-frontend | **Base**: `{API_URL}/api`

Session-based authentication via Laravel Sanctum cookies. No token storage required on the frontend; the cookie is set automatically by the browser.

---

## POST /auth/login

Authenticate a registered user.

### Request Body

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

### Response (200)

```json
{
  "data": {
    "user": {
      "id": 15,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "phone": "+391234567890"
    },
    "message": "Login successful."
  }
}
```

Set-Cookie header with Sanctum session cookie is returned automatically.

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 401 | `{ "message": "Invalid email or password." }` | Bad credentials |
| 422 | `{ "message": "Validation error", "errors": { "email": ["Required"], "password": ["Required"] } }` | Missing fields |
| 429 | `{ "message": "Too many attempts. Please wait 60 seconds and try again." }` | Rate limited (5 req/min/IP) |

---

## POST /auth/register

Create a new traveler account.

### Request Body

```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "securePassword123",
  "password_confirmation": "securePassword123",
  "phone": "+391234567890",
  "locale": "en"
}
```

### Response (201)

```json
{
  "data": {
    "user": {
      "id": 15,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "message": "Account created. Please check your email to verify your address."
  }
}
```

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 422 | `{ "message": "Validation error", "errors": {...} }` | Invalid or duplicate email, weak password, etc. |
| 429 | `{ "message": "Too many attempts. Please wait 60 seconds and try again." }` | Rate limited (5 req/min/IP) |

---

## POST /auth/logout

End the current session. Requires authentication.

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | yes | `Bearer {token}` |

### Response (200)

```json
{
  "message": "Logged out."
}
```

---

## GET /auth/user

Get the currently authenticated user. Verifies the Sanctum session cookie.

### Response (200) — Authenticated

```json
{
  "data": {
    "id": 15,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+391234567890"
  }
}
```

### Response (401) — Guest

```json
{
  "message": "Unauthenticated."
}
```

---

## POST /auth/forgot-password

Request a password reset email.

### Request Body

```json
{
  "email": "jane@example.com"
}
```

### Response (200)

```json
{
  "message": "If an account with that email exists, a reset link has been sent."
}
```

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 429 | `{ "message": "Too many attempts. Please wait 60 seconds and try again." }` | Rate limited (3 req/min/IP) |

---

## Notes for Frontend Developers

- **No token management**: Sanctum session cookie is handled by the browser. The frontend calls `GET /auth/user` on app load to check auth state.
- **CSRF protection**: Call `GET /sanctum/csrf-cookie` before `POST /auth/login` and `POST /auth/register`. The backend sets an `XSRF-TOKEN` cookie.
- **Auth state**: Maintain a lightweight Zustand store or React Context. Do NOT store tokens in localStorage/sessionStorage.
- **Guest checkout**: No auth required. Booking endpoints accept guest requests without `Authorization` header.
- **Account creation offer**: After successful guest booking, show an "Create an Account" prompt on the confirmation page. Pre-fill email from checkout data.
