# Data Model: Traveler Authentication

**Feature**: 001-traveler-auth
**Date**: 2026-04-13

## Entity Relationship Diagram

```
┌──────────────────────┐       ┌──────────────────────────┐
│       users          │       │    guest_identities      │
├──────────────────────┤       ├──────────────────────────┤
│ id (PK, bigint)      │       │ id (PK, bigint)          │
│ name (string, 255)   │       │ email (string, 255)      │
│ email (string, uniq) │       │ name (string, 255)       │
│ password (string)    │       │ phone (string, 50, null) │
│ role (enum)          │       │ converted_user_id (FK→   │
│ locale (enum)        │       │   users.id, nullable)    │
│ email_verified_at    │       │ anonymized_at (ts, null) │
│   (timestamp, null)  │       │ created_at (timestamp)   │
│ failed_login_count   │       │ updated_at (timestamp)   │
│   (int, default 0)   │       └─────────────┬────────────┘
│ locked_until         │                     │
│   (timestamp, null)  │                     │ bookings reference
│ last_login_at        │                     │ guest_identity_id
│   (timestamp, null)  │                     ↓
│ created_at (ts)      │       ┌──────────────────────────┐
│ updated_at (ts)      │       │      bookings            │
└──────────┬───────────┘       │ (defined in spec 007)    │
           │                   ├──────────────────────────┤
           │ 1:N               │ user_id (FK→users, null) │
           ↓                   │ guest_identity_id (FK→   │
┌──────────────────────┐       │   guest_identities, null)│
│ personal_access_     │       └──────────────────────────┘
│ tokens (Sanctum)     │
├──────────────────────┤
│ id (PK, bigint)      │
│ tokenable_type       │
│ tokenable_id (FK→    │
│   users.id)          │
│ name (string)        │
│ token (string, uniq) │       ┌──────────────────────────┐
│ abilities (text)     │       │   auth_audit_logs        │
│ last_used_at (ts)    │       ├──────────────────────────┤
│ expires_at (ts,null) │       │ id (PK, bigint)          │
│ created_at (ts)      │       │ user_id (FK→users, null) │
│ updated_at (ts)      │       │ event_type (string)      │
└──────────────────────┘       │ ip_address (string, 45)  │
                               │ user_agent (string, 500) │
┌──────────────────────┐       │ metadata (jsonb, null)   │
│ password_reset_      │       │ created_at (timestamp)   │
│ tokens (Laravel)     │       └──────────────────────────┘
├──────────────────────┤
│ email (string, idx)  │
│ token (string)       │
│ created_at (ts)      │
└──────────────────────┘
```

## Entity Definitions

### 1. User

**Table**: `users`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, auto-increment | Primary identifier |
| `name` | varchar(255) | NOT NULL | Full name |
| `email` | varchar(255) | NOT NULL, UNIQUE, INDEX | Email (unique account identifier, FR-018) |
| `password` | varchar(255) | NOT NULL | Bcrypt-hashed password |
| `role` | enum('traveler','partner','admin') | NOT NULL, DEFAULT 'traveler' | User role on the platform |
| `locale` | enum('en','es','it') | NOT NULL, DEFAULT 'en' | Preferred language (FR-016) |
| `email_verified_at` | timestamp | NULLABLE | When email was verified (FR-022); null = unverified |
| `failed_login_count` | integer | NOT NULL, DEFAULT 0 | Consecutive failed login count (FR-014) |
| `locked_until` | timestamp | NULLABLE | Lockout expiry timestamp; null = not locked (FR-014) |
| `last_login_at` | timestamp | NULLABLE | Last successful sign-in timestamp |
| `remember_token` | varchar(100) | NULLABLE | Laravel remember me token (standard) |
| `created_at` | timestamp | NOT NULL | Account creation time |
| `updated_at` | timestamp | NOT NULL | Last update time |

**Validation rules** (from spec FRs):
- `name`: required, string, max 255
- `email`: required, valid email format, unique in `users.email` (FR-002)
- `password`: required, min 8 chars, 1 uppercase, 1 lowercase, 1 number (FR-003)
- `locale`: optional, one of `en`, `es`, `it`

**Indexes**:
- `users_email_unique` (UNIQUE) on `email`
- `users_role_index` on `role`

---

### 2. Guest Identity

**Table**: `guest_identities`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, auto-increment | Primary identifier |
| `email` | varchar(255) | NOT NULL, INDEX | Guest's email (not unique — same email may map to user) |
| `name` | varchar(255) | NOT NULL | Guest's name at checkout time |
| `phone` | varchar(50) | NULLABLE | Guest's phone number |
| `converted_user_id` | bigint | NULLABLE, FK→users.id | Set when guest converts to account (FR-007) |
| `anonymized_at` | timestamp | NULLABLE | Set when PII is anonymized (FR-026) |
| `created_at` | timestamp | NOT NULL | Record creation time |
| `updated_at` | timestamp | NOT NULL | Last update time |

**Validation rules**:
- `email`: required, valid email format
- `name`: required, string, max 255
- `phone`: optional, string, max 50

**State transitions**:
```
[Active] ──guest converts──→ [Converted] (converted_user_id set)
[Active] ──24 months pass──→ [Anonymized] (anonymized_at set, PII cleared)
```

**Anonymization behavior** (FR-026):
- When anonymized: `email` → `'anonymized'`, `name` → `'anonymized'`, `phone` → `null`
- `anonymized_at` set to current timestamp
- Associated booking records retained (booking.guest_identity_id still references this record)
- Never anonymized if any linked booking has a future tour date

**Indexes**:
- `guest_identities_email_index` on `email`
- `guest_identities_converted_user_id_index` on `converted_user_id`

---

### 3. Personal Access Token (Sanctum)

**Table**: `personal_access_tokens` (Laravel Sanctum default)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, auto-increment | Primary identifier |
| `tokenable_type` | varchar(255) | NOT NULL | Polymorphic type (always `App\Models\User`) |
| `tokenable_id` | bigint | NOT NULL, FK→users.id | User who owns the token |
| `name` | varchar(255) | NOT NULL | Token name (e.g., 'web-session', 'mobile-session') |
| `token` | varchar(64) | NOT NULL, UNIQUE | Hashed token value |
| `abilities` | text | NULLABLE | Token abilities (JSON array) |
| `last_used_at` | timestamp | NULLABLE | Last API call with this token |
| `expires_at` | timestamp | NULLABLE | Token expiration (7-day inactivity, FR-012) |
| `created_at` | timestamp | NOT NULL | Token creation time |
| `updated_at` | timestamp | NOT NULL | Token update time |

**Session management notes**:
- One token per device/session (FR-013: multiple concurrent sessions allowed)
- `expires_at` set to `now() + 7 days` on creation, extended on each authenticated request
- Token revoked (deleted) on explicit sign-out (FR-005)
- Expired tokens cleaned up by scheduled job

---

### 4. Password Reset Token

**Table**: `password_reset_tokens` (Laravel default)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `email` | varchar(255) | INDEX | Email of the account requesting reset |
| `token` | varchar(255) | NOT NULL | Hashed reset token |
| `created_at` | timestamp | NULLABLE | Token creation time (60-min validity, FR-009) |

**Behavior**:
- Only one active token per email (old tokens overwritten on new request)
- Token validated against `created_at` — rejected if older than 60 minutes (FR-009)
- All tokens for email invalidated when password is successfully changed (FR-011)
- Password reset only works for verified emails (FR-023)

---

### 5. Auth Audit Log

**Table**: `auth_audit_logs`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | PK, auto-increment | Primary identifier |
| `user_id` | bigint | NULLABLE, FK→users.id | User involved (null for failed logins with unknown email) |
| `event_type` | varchar(50) | NOT NULL, INDEX | Event type identifier (FR-025) |
| `ip_address` | varchar(45) | NULLABLE | Client IP address (IPv4 or IPv6) |
| `user_agent` | varchar(500) | NULLABLE | Client user agent string |
| `metadata` | jsonb | NULLABLE | Additional context (e.g., failure reason, lockout duration) |
| `created_at` | timestamp | NOT NULL | Event timestamp |

**Event types** (per FR-025):
- `login_success`
- `login_failed`
- `account_lockout`
- `registration`
- `guest_conversion`
- `password_reset_requested`
- `password_reset_completed`
- `password_changed`
- `email_verified`
- `verification_email_sent`

**Indexes**:
- `auth_audit_logs_user_id_index` on `user_id`
- `auth_audit_logs_event_type_index` on `event_type`
- `auth_audit_logs_created_at_index` on `created_at`

---

## Relationships

| From | To | Type | FK Column | Notes |
|------|----|------|-----------|-------|
| `personal_access_tokens` | `users` | N:1 | `tokenable_id` | User's active sessions |
| `auth_audit_logs` | `users` | N:1 | `user_id` | Nullable (failed login for unknown email) |
| `guest_identities` | `users` | N:1 | `converted_user_id` | Set on guest→account conversion |
| `bookings` | `users` | N:1 | `user_id` | Nullable (guest bookings have no user_id until conversion) |
| `bookings` | `guest_identities` | N:1 | `guest_identity_id` | Nullable (registered user bookings have no guest_identity_id) |

## Migration Order

1. `create_users_table` — no dependencies
2. `create_personal_access_tokens_table` — depends on `users`
3. `create_password_reset_tokens_table` — no FK dependency (uses email string)
4. `create_guest_identities_table` — depends on `users` (converted_user_id FK)
5. `create_auth_audit_logs_table` — depends on `users`
