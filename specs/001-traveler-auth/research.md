# Research: Traveler Authentication

**Feature**: 001-traveler-auth
**Date**: 2026-04-13
**Status**: Complete

## Research Tasks

### 1. Laravel Sanctum Token-Based Auth for SPA + API

**Decision**: Use Laravel Sanctum with API token authentication (not SPA cookie mode) for the Next.js frontend.

**Rationale**: The Next.js frontend is a separate application from the Laravel backend (different origins in production). Token-based auth via Sanctum fits cross-origin architectures cleanly — the frontend stores the token and sends it as a Bearer token in the Authorization header. This avoids CSRF complexity and works seamlessly with mobile clients if needed later.

**Alternatives considered**:
- Sanctum SPA mode (cookie-based): Requires same-origin or carefully configured CORS + CSRF. Adds complexity with Next.js SSR since cookies must be forwarded through the SSR layer. Rejected for cross-origin simplicity.
- Laravel Passport (OAuth2): Overkill for first-party auth. Constitution explicitly puts third-party OAuth out of scope. Rejected.
- JWT (tymon/jwt-auth): Not in approved stack. Sanctum is constitutionally approved. Rejected.

**Implementation notes**:
- Token issued upon login/registration, returned in JSON response
- Frontend stores token in memory (React context) + httpOnly cookie for SSR requests
- Token sent via `Authorization: Bearer {token}` header
- Token revoked on logout (DELETE the token record)
- Multiple tokens per user allowed (supports concurrent sessions on different devices)

---

### 2. Brute-Force Protection Strategy

**Decision**: Implement rate limiting at two layers — per-account lockout (application-level) + per-IP throttling (middleware-level).

**Rationale**: Per-account lockout prevents credential stuffing against a single account. Per-IP throttling prevents distributed attacks across many accounts from a single source. Both are needed for defense-in-depth.

**Alternatives considered**:
- Application-level only: No IP-based protection. An attacker could try 4 passwords on every account without triggering lockout. Rejected.
- Redis-based rate limiting only: Doesn't track per-account failure history for escalating lockouts. Rejected.
- CAPTCHA after N failures: Added UX friction, requires third-party dependency. Deferred to future phase.

**Implementation notes**:
- Per-account: Track `failed_login_count` and `locked_until` on user record (or cache)
- Escalation: 5 failures → 1 min lock, continued failures → 5 min → 30 min
- Reset: Counter resets to 0 on successful login
- Per-IP: Laravel's built-in `ThrottleRequests` middleware — 10 attempts/minute on auth endpoints
- Lockout state stored in Redis (fast reads, auto-expiry)

---

### 3. Guest Identity → Account Conversion Pattern

**Decision**: Guest identity stored in a separate `guest_identities` table. On account creation (registration or guest conversion), query `guest_identities` by email and link all associated bookings to the new user record.

**Rationale**: Keeping guest identity separate from the users table avoids polluting the user model with incomplete records (no password, no auth capability). The linking operation is a one-time batch update at conversion time.

**Alternatives considered**:
- Single users table with `is_guest` flag: Creates "partial" user records that complicate auth logic (must exclude guests from login queries, password validation, etc.). Rejected.
- Store guest email directly on booking: No central guest identity — harder to deduplicate or merge. Rejected for data model clarity.

**Implementation notes**:
- `guest_identities` table: id, email, name, phone, created_at, updated_at, anonymized_at
- On registration/conversion: `LinkGuestBookingsAction` queries `Booking::where('guest_identity_id', ...)` and updates `user_id`
- The guest_identity record is soft-linked to the user (or marked as converted)
- Anonymization job: `AnonymizeStaleGuestIdentities` runs on schedule, anonymizes records 24 months after last booking date

---

### 4. Email Verification Flow (Non-Blocking)

**Decision**: Use Laravel's built-in email verification scaffolding (`MustVerifyEmail` contract + `VerificationNotification`) with customization: verification is non-blocking, and password reset is gated behind verification.

**Rationale**: Laravel's built-in system provides signed URL verification links, automatic retry support, and integrates with the notification queue. We customize the behavior to be non-blocking (user can use the platform while unverified) rather than the default blocking behavior.

**Alternatives considered**:
- Custom verification token system: Unnecessary when Laravel provides a robust signed-URL approach. Rejected for NIH avoidance.
- Blocking verification (must verify before any use): Too much friction for a travel marketplace where users may want to browse and book immediately. Rejected per spec clarification.

**Implementation notes**:
- `email_verified_at` column on users table (Laravel default)
- Verification email queued via Redis, retry-safe
- Signed URL with 60-minute expiry
- Unverified users can browse, book, and use all features except password reset
- Password reset endpoint checks `email_verified_at` — if null, sends verification email instead

---

### 5. Password Hashing & Security

**Decision**: Use Laravel's default bcrypt hashing via `Hash::make()`. Password strength validation via Form Request rules.

**Rationale**: Bcrypt is the Laravel default (with Argon2id as an alternative). It provides automatic salting and configurable cost factor. No reason to deviate from the framework default.

**Alternatives considered**:
- Argon2id: Slightly better resistance to GPU attacks, but bcrypt is more widely tested and is the Laravel default. No compelling reason to switch.
- scrypt: Not natively supported by Laravel. Would require custom implementation. Rejected.

**Implementation notes**:
- Password validation: min 8 chars, 1 uppercase, 1 lowercase, 1 number (per FR-003)
- Bcrypt cost factor: 12 (Laravel default of 10 is acceptable; 12 provides better future-proofing)
- Password is never logged or returned in API responses

---

### 6. Audit Logging for Auth Events

**Decision**: Use Laravel Events + Listeners pattern. Each auth action dispatches a domain event; a central `LogAuthEvent` listener writes to the `auth_audit_logs` table.

**Rationale**: Event-driven logging decouples the audit concern from the auth logic. Events are already a Laravel best practice and are constitutionally encouraged. A dedicated audit table (separate from the general application logs) provides queryable, structured logging.

**Alternatives considered**:
- Direct database inserts in service methods: Couples logging to business logic. Makes testing harder. Rejected.
- Third-party audit package (e.g., spatie/laravel-activitylog): Adds dependency. Constitution says minimize unnecessary dependencies. The auth audit needs are simple enough for a custom implementation. Rejected.
- General-purpose log files: Not queryable, not structured, not suitable for compliance review. Rejected.

**Implementation notes**:
- Events: `TravelerRegistered`, `TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut`, `PasswordReset`, `PasswordChanged`, `EmailVerified`, `GuestConvertedToAccount`
- Listener: `LogAuthEvent` — writes to `auth_audit_logs` table
- Each log entry: user_id (nullable), event_type, ip_address, user_agent, metadata (JSON), created_at
- Retention: indefinite for auth audit logs (no auto-purge in Phase 1)

---

### 7. Multi-Language Auth Pages (i18n)

**Decision**: Use `next-intl` for Next.js internationalization with locale-prefixed routes (`/en/auth/login`, `/es/auth/login`, `/it/auth/login`).

**Rationale**: `next-intl` is the most mature i18n library for Next.js App Router. It supports server components, middleware-based locale detection, and static generation. Locale-prefixed routes are constitutionally required for SEO.

**Alternatives considered**:
- `next-i18next`: Primarily designed for Pages Router, less App Router support. Rejected.
- Custom i18n solution: Unnecessary complexity. Rejected.
- `react-intl` (`formatjs`): Less Next.js-specific integration compared to `next-intl`. Rejected.

**Implementation notes**:
- Translation files: `i18n/en/auth.json`, `i18n/es/auth.json`, `i18n/it/auth.json`
- Middleware detects locale from URL prefix, falls back to browser preference, then English
- All auth error messages translated in all three languages
- Backend sends error codes; frontend maps to localized strings

---

### 8. Session Token Storage Strategy (Frontend)

**Decision**: Store the Sanctum API token in an httpOnly cookie set by the Next.js server (not the Laravel backend). Additionally hold the token in React state for client-side API calls.

**Rationale**: httpOnly cookies prevent XSS token theft. The Next.js server can read the cookie during SSR to make authenticated API calls on behalf of the user. React state provides immediate access for client-side navigation without cookie parsing.

**Alternatives considered**:
- localStorage: Vulnerable to XSS attacks. Rejected for security.
- Cookie from Laravel (SameSite cross-origin): Requires careful CORS + SameSite configuration across origins. Fragile. Rejected.
- In-memory only (React state): Lost on page refresh. Rejected for user experience.

**Implementation notes**:
- On login success: Next.js API route receives token from Laravel → sets httpOnly cookie → returns user data to client
- On page refresh: SSR reads httpOnly cookie → makes server-side API call for user data
- On logout: Cookie cleared + Sanctum token revoked via API
- Cookie attributes: httpOnly, secure, SameSite=Lax, path=/, max-age=7 days (matches session timeout)
