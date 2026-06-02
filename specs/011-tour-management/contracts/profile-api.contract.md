# Contract: Profile API

**Consumer**: Next.js frontend (authenticated traveler pages)  
**Provider**: Laravel API (`/api/traveler/*`)  
**Authentication**: Laravel Sanctum cookie-based session

---

## 1. Get Profile

```
GET /api/traveler/profile
```

### Success Response (200)
```json
{
  "data": {
    "id": "uuid",
    "first_name": "Alice",
    "last_name": "Smith",
    "email": "alice@example.com",
    "phone": "+39 333 1234567",
    "preferred_language": "en",
    "preferred_currency": "EUR",
    "marketing_emails": true,
    "avatar_url": "https://cdn.example.com/avatars/alice.jpg"
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |

---

## 2. Update Profile

```
PUT /api/traveler/profile
```

### Request Body
```json
{
  "first_name": "Alice",
  "last_name": "Smith",
  "phone": "+39 333 1234567",
  "preferred_language": "it",
  "preferred_currency": "EUR",
  "marketing_emails": false
}
```

### Validation Rules
- `first_name`: required, string, max 255 chars
- `last_name`: required, string, max 255 chars
- `phone`: optional, string, max 50 chars
- `preferred_language`: required, in `[en, es, it]`
- `preferred_currency`: required, ISO 4217 code
- `marketing_emails`: boolean

### Success Response (200)
```json
{
  "data": {
    "id": "uuid",
    "first_name": "Alice",
    "last_name": "Smith",
    "email": "alice@example.com",
    "phone": "+39 333 1234567",
    "preferred_language": "it",
    "preferred_currency": "EUR",
    "marketing_emails": false,
    "avatar_url": "https://cdn.example.com/avatars/alice.jpg"
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 422  | Validation error (field-level errors returned) |

---

## 3. Change Password

```
POST /api/traveler/profile/change-password
```

### Request Body
```json
{
  "current_password": "oldSecret123",
  "new_password": "newStrong456!",
  "new_password_confirmation": "newStrong456!"
}
```

### Validation Rules
- `current_password`: required
- `new_password`: required, min 8 chars, confirmed
- `new_password_confirmation`: required, must match `new_password`

### Success Response (200)
```json
{
  "message": "Password updated successfully."
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 403  | Current password is incorrect |
| 422  | Validation error (e.g., confirmation mismatch, too short) |
