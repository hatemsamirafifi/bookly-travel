# Data Model: Traveler Sign-In and Sign-Out

**Feature**: 004-traveler-signin | **Date**: 2026-04-25

> **Note**: This feature introduces **no new entities, tables, or columns**. All required data structures were created by spec 002 (foundational implementation) and are reused here. This document describes the existing entities as they are utilized by the sign-in flow.

---

## Existing Entities

### `User` (`users` table)

The primary authentication entity. Already supports all sign-in requirements.

| Column | Type | Used By | Description |
|--------|------|---------|-------------|
| `id` | bigint PK | — | Primary key |
| `name` | string | `UserResource` | Display name |
| `email` | string, unique | Credential lookup, audit log | Normalized to lowercase + trim |
| `password` | string (hashed) | Credential verification | bcrypt hash |
| `role` | enum (`traveler`, `partner`, `admin`) | `UserResource` | Default `traveler` |
| `locale` | enum (`en`, `es`, `it`) | `UserResource`, i18n | Default `en` |
| `email_verified_at` | datetime, nullable | — | Email verification timestamp |
| `failed_login_count` | integer, default 0 | Brute-force logic | Consecutive failed attempts |
| `locked_until` | datetime, nullable | Brute-force logic | Account lockout expiration |
| `last_login_at` | datetime, nullable | `UserResource` | Last successful sign-in |
| `created_at` / `updated_at` | datetime | — | Timestamps |

**Model**: `App\Models\User`  
**Casts**: `locked_until` → datetime, `last_login_at` → datetime, `password` → hashed  
**Traits**: `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`

**Relationships**:
- `guestIdentities(): HasMany` — Guest identities linked to this account
- `auditLogs(): HasMany` — `AuthAuditLog` entries for this user

---

### `PersonalAccessToken` (`personal_access_tokens` table)

Managed by Laravel Sanctum. Represents an active authenticated session.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Primary key |
| `tokenable_type` | string | Polymorphic type (`App\Models\User`) |
| `tokenable_id` | bigint | Foreign key to `users.id` |
| `name` | string | Token name — always `auth-token` |
| `token` | string (hashed) | Hashed token value |
| `abilities` | json | Token abilities — `['*']` |
| `last_used_at` | datetime, nullable | Last usage timestamp |
| `expires_at` | datetime, nullable | Expiration timestamp |
| `created_at` / `updated_at` | datetime | Timestamps |

**Usage in this feature**:
- **Login**: `$user->createToken('auth-token')` creates a new token
- **Logout**: `$request->user()->currentAccessToken()->delete()` revokes only the current token
- **Multi-device**: Other tokens for the same `tokenable_id` remain untouched

---

### `AuthAuditLog` (`auth_audit_logs` table)

Append-only audit trail for authentication events.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Primary key |
| `user_id` | bigint, nullable FK | Linked user (null for failed attempts with unknown email) |
| `event_type` | string | `login_success`, `login_failed`, `account_lockout` |
| `ip_address` | string, nullable | Client IP |
| `user_agent` | string, nullable | Client user agent (max 500 chars) |
| `metadata` | json, nullable | Additional context (hashed email for failed attempts) |
| `created_at` | datetime | Timestamp (no `updated_at`) |

**Model**: `App\Models\AuthAuditLog`  
**Listener**: `App\Domains\Auth\Listeners\LogAuthEvent`  
**Events handled**: `TravelerLoggedIn` → `login_success`, `LoginFailed` → `login_failed`, `AccountLockedOut` → `account_lockout`

---

## State Transitions

### Brute-Force Lockout State Machine

```text
[Normal State]
   │
   │ Failed login
   ▼
[failed_login_count += 1]
   │
   │ count == 5?
   ├── No ──→ [Remain in Normal State]
   │
   └── Yes ──→ [locked_until = now + tier_duration]
                    │
                    ▼
             [Locked State]
                    │
   ┌────────────────┼────────────────┐
   │                │                │
   │  lock expired  │  successful    │  failed login
   │                │  login         │  (rejected)
   ▼                ▼                ▼
[Reset to    [Reset to        [Remain in
 Normal]      Normal]          Locked State]
   │
   │ Next lockout triggered
   ▼
[Escalate tier: 1min → 5min → 30min]
```

**Tier escalation rules**:
- Determine tier by inspecting `locked_until` history from `auth_audit_logs` (`account_lockout` events) for this user
- 1st lockout: 1 minute
- 2nd lockout: 5 minutes
- 3rd+ lockout: 30 minutes
- Reset on successful login: `failed_login_count = 0`, `locked_until = null`

---

## Validation Rules

### Login Request (`App\Http\Requests\Auth\LoginRequest`)

| Field | Rules | Error Message Key |
|-------|-------|-------------------|
| `email` | required, string, email format | `auth.errors.invalidCredentials` |
| `password` | required, string, min:1 | `auth.errors.invalidCredentials` |

**Normalization** (in `prepareForValidation`):
- `email`: `trim()` + `strtolower()`

---

## No New Migrations

All tables and columns required by this feature already exist:
- `users` table: `failed_login_count`, `locked_until`, `last_login_at` (added by 002)
- `personal_access_tokens` table: Sanctum default migration (added by 002)
- `auth_audit_logs` table: Custom migration (added by 002)
- Events and listeners: `TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut`, `LogAuthEvent` (added by 002)
