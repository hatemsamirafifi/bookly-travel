# Data Model: Traveler Registration

**Feature**: 003-traveler-registration
**Date**: 2026-04-18
**Parent**: [001-traveler-auth/data-model.md](file:///f:/Travel%20Website/bookly%20travel/specs/001-traveler-auth/data-model.md)

## Scope

This feature does NOT create new tables. All tables were created in Phase 2 (002-foundational-implementation). This document defines which tables and columns are **written to** during the registration flow.

## Tables Written During Registration

### 1. `users` — New Row Created

| Column | Value Source | Notes |
|--------|-------------|-------|
| `name` | Request input | Required, max 255 |
| `email` | Request input | Required, unique, trimmed + lowercased |
| `password` | Request input | Bcrypt-hashed via Laravel `hashed` cast |
| `role` | Default: `'traveler'` | Always `traveler` for this endpoint |
| `locale` | Request input or default `'en'` | Optional, enum: en/es/it |
| `email_verified_at` | `null` | Not verified at registration time |
| `failed_login_count` | Default: `0` | Not applicable at registration |
| `locked_until` | Default: `null` | Not applicable at registration |
| `last_login_at` | `null` | Updated on first sign-in (Phase 4) |

### 2. `personal_access_tokens` — New Token Created

| Column | Value Source | Notes |
|--------|-------------|-------|
| `tokenable_type` | `App\Models\User` | Polymorphic type |
| `tokenable_id` | New user's ID | FK to users.id |
| `name` | `'auth-token'` | Token name for identification |
| `token` | Sanctum-generated SHA256 | Hashed token stored |
| `abilities` | `['*']` | Full abilities |
| `expires_at` | `now() + 7 days` | Per Sanctum config |

### 3. `auth_audit_logs` — New Log Entry Created

| Column | Value Source | Notes |
|--------|-------------|-------|
| `user_id` | New user's ID | FK to users.id |
| `event_type` | `'registration'` | Via LogAuthEvent listener |
| `ip_address` | `request()->ip()` | Client IP |
| `user_agent` | `request()->userAgent()` | Truncated to 500 chars |
| `metadata` | `null` | No additional metadata for registration |

### 4. `guest_identities` — Read + Conditional Write

Queried by email during `LinkGuestBookingsAction` to find guest records eligible for booking linkage.
When matching records are found, `converted_user_id` is updated to the new user's ID — marking the guest identity as converted. This write happens during the registration flow (Phase 3), not exclusively during the guest-conversion flow (Phase 6, spec 001 US4). Both flows write `converted_user_id`; Phase 6 performs the full conversion with a password-set step, while Phase 3 handles the "silent linkage" case for travelers who register with an email they previously used for guest checkout.

| Column | Operation | Value |
|--------|-----------|-------|
| `converted_user_id` | UPDATE (WHERE email = user.email) | New user's ID |


### 5. `bookings` — Conditional Write (May Not Exist)

If the `bookings` table exists (spec 007), `LinkGuestBookingsAction` updates `user_id` on matching booking records. If the table does not exist, the action no-ops gracefully.

## Data Flow Sequence

```
Request → RegisterRequest (validate) → RegisterTravelerAction:
  1. Create User row (users table)
  2. LinkGuestBookingsAction (read guest_identities, update bookings.user_id if table exists)
  3. Dispatch TravelerRegistered event → LogAuthEvent → auth_audit_logs row
  4. Queue SendVerificationEmail job
  5. Create Sanctum token (personal_access_tokens row)
  6. Return UserResource + token
```

## Validation Rules (RegisterRequest)

| Field | Rules | Error Key |
|-------|-------|-----------|
| `name` | required, string, max:255 | auth.errors.nameRequired |
| `email` | required, email, unique:users,email | auth.errors.emailTaken |
| `password` | required, string, min:8, regex:/[A-Z]/, regex:/[a-z]/, regex:/[0-9]/ | auth.errors.weakPassword |
| `locale` | sometimes, in:en,es,it | auth.errors.invalidLocale |
