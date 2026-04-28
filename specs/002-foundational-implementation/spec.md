# Feature Specification: Foundational Implementation

**Feature Branch**: `002-foundational-implementation`
**Created**: 2026-04-18
**Status**: Complete ✅
**Input**: User description: "Database schema, shared models, event infrastructure, and API scaffolding that ALL user stories depend on (Phase 2 from 001-traveler-auth)"
**Parent Feature**: `001-traveler-auth` (Phase 2)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Database Schema Foundation (Priority: P1)

The development team needs a fully migrated database with all tables required by the authentication system — users, personal access tokens, password reset tokens, guest identities, and auth audit logs — so that all subsequent user stories can read from and write to these tables without migration conflicts.

**Why this priority**: Every single user story in the authentication system depends on these tables existing. No application code can function without the schema.

**Independent Test**: Run all migrations successfully against a clean database. Verify all five tables exist with the correct columns, constraints, indexes, and foreign key relationships.

**Acceptance Scenarios**:

1. **Given** a clean database, **When** all migrations are executed, **Then** the `users`, `personal_access_tokens`, `password_reset_tokens`, `guest_identities`, and `auth_audit_logs` tables are created successfully.
2. **Given** the `users` table exists, **When** inspected, **Then** it contains all required columns: `id`, `name`, `email` (unique), `password`, `role` (enum), `locale` (enum), `email_verified_at`, `failed_login_count`, `locked_until`, `last_login_at`, `remember_token`, and timestamps.
3. **Given** foreign key relationships are defined, **When** a `guest_identities` record references a user via `converted_user_id`, **Then** deleting that user sets the FK to null (nullOnDelete behavior).

---

### User Story 2 - Domain Models and Relationships (Priority: P1)

The development team needs fully defined Eloquent models for User, GuestIdentity, and AuthAuditLog with correct relationships, casts, scopes, and security attributes — so that all business logic in subsequent phases can operate on these models reliably.

**Why this priority**: Models are the application's interface to the database. Incorrect model definitions would cascade errors into every action, controller, and service built on top of them.

**Independent Test**: Instantiate each model via its factory, verify relationships load correctly, confirm sensitive fields are hidden from serialization, and validate scopes return expected query results.

**Acceptance Scenarios**:

1. **Given** the User model, **When** serialized to JSON, **Then** `password` and `remember_token` are excluded from the output.
2. **Given** a GuestIdentity record, **When** the `anonymize()` method is called, **Then** the email, name, and phone are replaced with anonymized values and `anonymized_at` is set.
3. **Given** an AuthAuditLog record, **When** an update or delete is attempted, **Then** the model enforces append-only behavior.

---

### User Story 3 - Event Infrastructure (Priority: P1)

The system needs a complete set of domain events and a centralized audit logging listener — so that all authentication activities across all phases are automatically captured in the audit log whenever the corresponding event is dispatched.

**Why this priority**: Audit logging is a constitutional mandate. The infrastructure must be in place before any action dispatches events, otherwise audit trail gaps will occur.

**Independent Test**: Dispatch each domain event and verify a corresponding record appears in the `auth_audit_logs` table with the correct event type, user ID, IP address, and user agent.

**Acceptance Scenarios**:

1. **Given** the event infrastructure is registered, **When** any of the 8 authentication events is dispatched, **Then** the `LogAuthEvent` listener creates an audit log entry.
2. **Given** an event containing an email address, **When** logged, **Then** the email is stored as a hashed value (not plaintext) in the metadata field.
3. **Given** a `TravelerRegistered` event is dispatched, **When** the listener processes it, **Then** the audit log entry has `event_type` set to `registration` (matching the data model specification).

---

### User Story 4 - API Scaffolding and Configuration (Priority: P1)

The system needs a configured API route group with rate limiting, Sanctum token authentication with 7-day expiry, and the UserResource transformer — so that all subsequent controllers can be registered in a consistent, secured route group.

**Why this priority**: Without the route group, rate limiter, and token configuration, no controller endpoint can be safely exposed.

**Independent Test**: Verify the auth route group exists with throttle middleware, Sanctum expiration is set to 7 days, and the UserResource transforms a User model into the expected JSON structure.

**Acceptance Scenarios**:

1. **Given** the API route configuration, **When** more than 10 requests per minute hit auth endpoints from the same IP, **Then** subsequent requests receive a 429 response.
2. **Given** a User model, **When** transformed via `UserResource`, **Then** the output contains exactly: `id`, `name`, `email`, `role`, `locale`, `email_verified` (boolean), `created_at`, `last_login_at`.
3. **Given** the Sanctum configuration, **When** a token is issued, **Then** it expires 7 days after issuance.

---

### User Story 5 - Frontend Auth Infrastructure (Priority: P1)

The frontend needs Zod validation schemas, a typed API client, an authentication context provider, an auth guard component, and complete translation files — so that all subsequent frontend pages and forms can leverage shared auth state, validation, and localization.

**Why this priority**: Every auth-related page in Phase 3+ depends on these shared frontend modules. Building them ad-hoc in each page would create inconsistencies and duplication.

**Independent Test**: Import each module in isolation, verify TypeScript compiles without errors, Zod schemas validate correctly, the auth context provides user state, and translation keys are present in all three locales.

**Acceptance Scenarios**:

1. **Given** the Zod schemas, **When** a password with no uppercase letter is validated, **Then** validation fails with a specific error.
2. **Given** the auth API client, **When** a login request is made, **Then** the response is mapped from snake_case API fields to camelCase TypeScript properties.
3. **Given** the AuthGuard component wraps a protected page, **When** no user is signed in, **Then** the user is redirected to the locale-aware login page with a return URL.
4. **Given** all three translation files, **When** the `auth.errors` keys are compared, **Then** all three locales have identical key structures.

---

### User Story 6 - Test Factories (Priority: P1)

The development team needs User and GuestIdentity factories with configurable states — so that all feature tests in subsequent phases can seed realistic test data.

**Why this priority**: Factories are prerequisites for every test file. Without them, no feature test can create test users or guest identities.

**Independent Test**: Use each factory and its states to create model instances and verify they persist correctly to the database.

**Acceptance Scenarios**:

1. **Given** the UserFactory, **When** `User::factory()->create()` is called, **Then** a valid user with default attributes (traveler role, English locale, verified email) is persisted.
2. **Given** the UserFactory, **When** the `lockedOut()` state is applied, **Then** the user has `failed_login_count` of 5 and a future `locked_until` timestamp.
3. **Given** the GuestIdentityFactory, **When** the `anonymized()` state is applied, **Then**  PII fields are replaced with anonymized values and `anonymized_at` is set.

---

### Edge Cases

- What happens if migrations are run out of order? → Migration filenames use timestamp prefixes that enforce correct ordering. Foreign key dependencies are respected.
- What happens if the `LogAuthEvent` listener encounters an event type not in the mapping? → It falls back to `Str::snake(class_basename)` for forward compatibility with future events.
- What happens if `request()` returns null in the listener (e.g., during console/testing)? → IP and user agent fields are nullable and gracefully handle null values.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide database migrations for all five authentication tables (users, personal_access_tokens, password_reset_tokens, guest_identities, auth_audit_logs) with all columns, constraints, indexes, and foreign keys defined in the data model specification.
- **FR-002**: The system MUST provide Eloquent models (User, GuestIdentity, AuthAuditLog) with correct fillable attributes, casts, relationships, and security attributes (hidden fields, append-only enforcement).
- **FR-003**: The User model MUST implement the `MustVerifyEmail` contract and use the `HasApiTokens` trait for Sanctum token support.
- **FR-004**: The GuestIdentity model MUST provide `active` and `anonymizable` query scopes for filtering records by state.
- **FR-005**: The GuestIdentity model MUST provide an `anonymize()` method that replaces PII with anonymized values and sets the `anonymized_at` timestamp.
- **FR-006**: The system MUST provide all 8 domain events: TravelerRegistered, TravelerLoggedIn, LoginFailed, AccountLockedOut, PasswordReset, PasswordChanged, EmailVerified, GuestConvertedToAccount.
- **FR-007**: The system MUST provide a centralized `LogAuthEvent` listener that writes audit log entries for all domain events with user ID, event type (matching data model naming), IP address, user agent, and hashed metadata.
- **FR-008**: The system MUST register all event-to-listener mappings in the EventServiceProvider.
- **FR-009**: The system MUST configure Sanctum token expiration to 7 days and define an auth-specific rate limiter at 10 requests per minute per IP.
- **FR-010**: The system MUST provide an API route group at `/api/public/auth` with rate limiting middleware applied.
- **FR-011**: The system MUST provide a UserResource API transformer that outputs: id, name, email, role, locale, email_verified (boolean), created_at, last_login_at.
- **FR-012**: The system MUST provide frontend Zod validation schemas for all auth forms (register, login, forgot-password, reset-password, change-password) with shared password strength rules.
- **FR-013**: The system MUST provide a typed frontend API client module with methods for all auth endpoints and camelCase field mapping.
- **FR-014**: The system MUST provide a React auth context provider (AuthProvider + useAuth hook) with token management, user state, and sign-in/sign-out methods.
- **FR-015**: The system MUST provide an AuthGuard component that redirects unauthenticated users to the locale-aware login page with a return URL parameter.
- **FR-016**: The system MUST provide auth translation keys in all three locale files (English, Spanish, Italian) with matching key structures.
- **FR-017**: The system MUST provide UserFactory and GuestIdentityFactory with configurable states for test seeding.

### Key Entities

- **User**: Central identity model. Attributes: name, email (unique), hashed password, role (traveler/partner/admin), locale (en/es/it), email verification status, login failure tracking, session timestamps.
- **GuestIdentity**: Temporary checkout identity. Attributes: email, name, phone, conversion status, anonymization status.
- **AuthAuditLog**: Append-only security log. Attributes: user reference, event type, client IP, user agent, metadata (JSONB).
- **PersonalAccessToken**: Sanctum session token. Attributes: tokenable reference, hashed token, abilities, expiry.
- **PasswordResetToken**: Time-limited reset credential. Attributes: email, hashed token, creation timestamp.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All 5 database migrations execute successfully against a clean database without errors.
- **SC-002**: All 3 models (User, GuestIdentity, AuthAuditLog) can be instantiated via their factories and persisted to the database.
- **SC-003**: All 8 domain events dispatch correctly and produce audit log entries via the LogAuthEvent listener.
- **SC-004**: The auth rate limiter correctly blocks the 11th request within a 1-minute window from the same IP.
- **SC-005**: The UserResource produces the correct JSON structure with exactly the 8 specified fields.
- **SC-006**: All frontend TypeScript files compile without errors.
- **SC-007**: All 3 translation files contain identical key structures under the `auth` namespace.
- **SC-008**: The AuthGuard correctly redirects unauthenticated users to the login page with a return URL.

## Assumptions

- PHP 8.2+ and Laravel 11 are installed and configured (Phase 1 setup).
- PostgreSQL 15 and Redis 7 are available via Docker Compose (Phase 1 setup).
- Next.js 16 with TypeScript, App Router, and next-intl is configured (Phase 1 setup).
- The `bookings` table does NOT exist yet (spec 007) — the GuestIdentity model's booking-related logic must handle this gracefully.
- This phase produces no user-facing UI — it is purely infrastructure for subsequent phases.
- All items in this spec have been implemented and reviewed. Status is Complete.
