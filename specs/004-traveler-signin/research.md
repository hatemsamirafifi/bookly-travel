# Research: Traveler Sign-In and Sign-Out

**Date**: 2026-04-25 | **Feature**: 004-traveler-signin

## Unknowns Resolved

### 1. Database Schema — Are all required columns present?

- **Decision**: No new migrations needed. The `users` table already contains `failed_login_count`, `locked_until`, `last_login_at`, and `locale` columns (verified in migration `0001_01_01_000000_create_users_table.php` and `User` model casts).
- **Rationale**: The foundational implementation (002) pre-created all auth-related columns. The spec explicitly confirms no schema changes.
- **Alternatives considered**: Adding a separate `login_attempts` table — rejected as unnecessary overhead when the existing columns satisfy the requirement exactly.

### 2. Event Infrastructure — Do events and listeners exist?

- **Decision**: Reuse all existing events and listeners. `TravelerLoggedIn`, `LoginFailed`, and `AccountLockedOut` events already exist in `backend/app/Domains/Auth/Events/`. The `LogAuthEvent` listener is already mapped to all three events in `EventServiceProvider` and writes to `auth_audit_logs`.
- **Rationale**: The spec explicitly states these events exist and do not need creation. The listener already handles `login_success`, `login_failed`, and `account_lockout` event types.
- **Alternatives considered**: Creating new listeners or modifying the existing one — rejected because the existing listener already covers all required audit types.

### 3. Brute-Force Storage — Redis or database?

- **Decision**: Store brute-force state (`failed_login_count`, `locked_until`) in the `users` database table, not in Redis cache.
- **Rationale**: The spec explicitly requires DB storage (edge case: "Lockout state is stored in the `users` database table... NOT in Redis cache, so a cache flush does not affect lockout enforcement"). This also simplifies testing and guarantees consistency across cache flushes.
- **Alternatives considered**: Redis-based rate limiting (Laravel's `RateLimiter` facade) — rejected because cache flushes would silently clear lockout state, creating a security gap.

### 4. Token Strategy — How are sessions managed?

- **Decision**: Laravel Sanctum personal access tokens named `auth-token`, created via `$user->createToken('auth-token')` and revoked via `$request->user()->currentAccessToken()->delete()`.
- **Rationale**: This is the exact pattern used in `RegisterTravelerAction`. The spec requires ONLY the current token be revoked on logout (FR-007). Sanctum's `currentAccessToken()` gives precise single-token revocation. The existing `authApi.logout()` already passes the Bearer token.
- **Alternatives considered**: Session-based authentication (Laravel's default `web` guard) — rejected because the API-first architecture requires stateless token auth. Passport OAuth — rejected as explicitly out of scope per constitution.

### 5. Frontend Pattern — What component structure?

- **Decision**: Mirror the registration flow exactly: server component page (`page.tsx`) with `generateMetadata`, `AuthGuard` with `requireAuth={false}`, and a client `LoginForm` using `react-hook-form` + `zodResolver` + `useTranslations('auth')`.
- **Rationale**: Consistency reduces cognitive load and ensures styling, accessibility, error handling, and localization patterns are identical. The `RegisterForm` already demonstrates the exact pattern needed.
- **Alternatives considered**: A fully server-component form with Server Actions — rejected because the Next.js 16 App Router in this project uses client-side API calls via `authApi` for auth endpoints, matching the registration architecture.

### 6. Rate Limiting — Is additional middleware needed?

- **Decision**: Use the existing `throttle:auth` middleware on the auth route group. No additional middleware needed.
- **Rationale**: The route group in `routes/api/public.php` already applies `throttle:auth` (10 requests per minute per IP). The spec explicitly references this (FR-014).
- **Alternatives considered**: Adding per-account rate limiting in the action class — rejected because IP-based rate limiting is already in place and per-account limiting would overlap with the brute-force lockout mechanism.

### 7. Email Normalization — What normalization rules?

- **Decision**: Trim whitespace and lowercase the email before credential verification, matching the registration behavior.
- **Rationale**: The spec requires this explicitly (FR-013) and the existing `RegisterRequest` already normalizes this way. Consistency prevents mismatches where a user registers with `"John@Example.COM "` but can't sign in with `john@example.com`.
- **Alternatives considered**: No normalization (rejected — breaks UX), Unicode normalization (rejected — overkill for Phase 1, not required by spec).

### 8. Login Error Response — 422 vs 401?

- **Decision**: Return `422 Unprocessable Entity` for both validation failures and credential failures.
- **Rationale**: The existing `RegisterController` returns 422 for validation errors. The `authApi` client treats any non-2xx response as an error and reads `message` and `errors`. Using 422 for all sign-in failures (including generic "Invalid email or password") keeps the frontend error handling uniform. The spec does not mandate a specific status code for credential failures, only the message content.
- **Alternatives considered**: 401 Unauthorized for wrong credentials — rejected because 401 semantically implies the client should re-authenticate, which is confusing for a login endpoint. 403 Forbidden — rejected because the credentials are simply wrong, not forbidden.

## Technology Decisions Summary

| Area | Technology | Rationale |
|------|------------|-----------|
| Backend auth | Laravel Sanctum | Existing; API-first architecture; single-token revocation |
| Backend validation | Laravel Form Request | Constitution mandate; authoritative server-side validation |
| Business logic | Domain Action classes | Existing pattern (`RegisterTravelerAction`); thin controllers |
| Frontend forms | react-hook-form + zod | Existing pattern; type-safe; consistent with registration |
| Frontend i18n | next-intl | Existing; supports EN/ES/IT with locale-prefixed routes |
| Styling | Tailwind CSS | Existing design system tokens; consistent with registration page |
| Testing | Pest (Laravel) | Existing test suite; `RegistrationTest.php` serves as reference |
| Brute-force storage | PostgreSQL `users` table | Spec requirement; survives cache flushes |

## Dependencies on Other Specs

| Spec | Dependency | Status |
|------|-----------|--------|
| 002-foundational-implementation | Database schema, events, listeners, middleware | Complete |
| 003-traveler-registration | `RegisterTravelerAction` pattern, `UserResource`, `authApi`, `useAuth`, `AuthGuard`, `RegisterForm` | Complete |
| 005-password-reset | "Forgot password?" link is a placeholder pointing to future route | Not a blocker |
