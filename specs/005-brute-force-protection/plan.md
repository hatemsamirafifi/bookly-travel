# Implementation Plan: Brute-Force Protection for Traveler Sign-In

**Branch**: `005-brute-force-protection` | **Date**: 2026-04-29 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-brute-force-protection/spec.md`

## Summary

Add database-backed account lockout after 5 consecutive failed sign-in attempts, with escalating lockout tiers (1 min, 5 min, 30 min), counter reset only on successful sign-in, audit logging of all attempts, and an email notification to locked-out travelers. The core lockout logic in `AuthenticateTravelerAction` is already implemented. Remaining work: add `rejected_due_to_lockout` event metadata, wire up email notification on lockout, and verify translation coverage.

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 11), TypeScript 5.x (Next.js 16)
**Primary Dependencies**: Laravel Sanctum (auth), React Hook Form + Zod (frontend forms), next-intl (i18n)
**Storage**: PostgreSQL — `users` table (`failed_login_count`, `locked_until` columns), `auth_audit_logs` table (append-only event log)
**Testing**: Pest PHP (backend feature tests), Vitest/Jest (frontend)
**Target Platform**: Linux server (Nginx + PHP-FPM), modern browsers
**Project Type**: Web application (frontend + backend API)
**Performance Goals**: Login response <3s p95 under lockout checks; `SELECT ... FOR UPDATE` serializes concurrent attempts safely
**Constraints**: Lockout enforced from DB state only (no cache dependency); email notification must be queued and idempotent
**Scale/Scope**: Phase 1 traveler sign-in only; partner/admin auth not in scope

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| **I. Marketplace-First** | PASS | Brute-force protection is infrastructure, not vertical-specific. Applies to traveler accounts within the marketplace. |
| **II. Tours-Only Discipline** | PASS | N/A — security infrastructure, not a new business domain. |
| **III. Direct Booking Only** | PASS | N/A — not a booking feature. |
| **IV. Admin-Governed Publishing** | PASS | N/A — not a publishing feature. |
| **V. Platform-Controlled Commerce** | PASS | N/A — not a commerce feature. |
| **VI. Completed-Booking Review Integrity** | PASS | N/A — not a review feature. |
| **Security-First Mandate** | PASS | This feature IS a security requirement. Lockout protects against credential stuffing. |
| **Mandatory Input Validation** | PASS | `LoginRequest` validates credentials; `AuthenticateTravelerAction` handles lockout logic. |
| **Strict Authorization** | PASS | Sign-in is unauthenticated by definition; post-auth access uses Sanctum. |
| **Thin Controllers** | PASS | `LoginController` maps action result to HTTP response only — no business logic. |
| **No Direct DB Access from Controllers** | PASS | All DB access is in `AuthenticateTravelerAction` and `LogAuthEvent` listener. |
| **Business Logic in Services/Actions** | PASS | Lockout counting, tier escalation, and counter reset are in `AuthenticateTravelerAction`. |
| **Long-Running Jobs MUST Be Queued** | PASS | Email notification will be dispatched via queued job. |
| **Retry-Safety** | PARTIAL (see Complexity Tracking) | Email job is queued. In rare retry scenarios a duplicate lockout notification could be sent. Mitigation: `SendAccountLockedOutEmail` listener tracks last-notified `locked_until` timestamp per user; if the stored timestamp matches the user's current `locked_until`, the email is skipped (already sent for this lockout event). |
| **Mandatory Audit Logs** | PASS | All login attempts and lockout events are logged to `auth_audit_logs`. |
| **Minimum Testing Coverage** | PASS | 15+ Pest tests covering lockout, escalation, reset, race conditions, cache survivability, rate limiting, and performance. |

**Gate Result**: ALL PASS — no violations. Proceed to Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/005-brute-force-protection/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── login-api.md     # (symlink/ref to 004 contract)
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/Auth/
│   │   ├── Actions/
│   │   │   └── AuthenticateTravelerAction.php  # Core lockout logic
│   │   ├── Events/
│   │   │   ├── LoginFailed.php                 # Needs rejectedDueToLockout property
│   │   │   └── AccountLockedOut.php            # Complete
│   │   ├── Listeners/
│   │   │   ├── LogAuthEvent.php                # Needs rejected_due_to_lockout in metadata
│   │   │   └── SendAccountLockedOutEmail.php   # Complete — queues AccountLockedOutMail
│   ├── Mail/
│   │   └── AccountLockedOutMail.php            # Complete — localized lockout notification mailable
│   ├── Http/
│   │   ├── Controllers/Public/Auth/
│   │   │   └── LoginController.php             # Complete (423 mapping)
│   │   └── Requests/Auth/
│   │       └── LoginRequest.php                # Complete
│   ├── Models/
│   │   ├── User.php                            # Complete (casts locked_until)
│   │   └── AuthAuditLog.php                    # Complete
│   └── Providers/
│       └── EventServiceProvider.php            # Needs entry for email listener
├── database/migrations/
│   ├── 0001_01_01_000000_create_users_table.php     # Complete
│   └── 0001_01_01_000003_create_auth_audit_logs_table.php  # Complete
└── tests/Feature/Auth/
    └── LoginTest.php                           # 15 tests exist — may need email notification test

frontend/
├── src/
│   ├── components/auth/
│   │   └── LoginForm.tsx                       # Complete (handles account_locked code)
│   ├── lib/
│   │   ├── api/auth.ts                         # Complete (AuthApiError.code mapping)
│   │   ├── hooks/useAuth.tsx                   # Complete
│   │   └── validators/auth.ts                  # Complete
│   └── messages/
│       ├── en.json                             # Complete (auth.errors.accountLocked)
│       ├── es.json                             # Complete (auth.errors.accountLocked)
│       └── it.json                             # Complete (auth.errors.accountLocked)
```

**Structure Decision**: Standard web application layout. No new directories needed. The remaining work is modifying 2-3 existing backend files and creating 2 new backend files (email mailable + listener).

## Complexity Tracking

| ID | Issue | Mitigation | Status |
|----|-------|------------|--------|
| CT-001 | Retry-Safety: `SendAccountLockedOutEmail` may send duplicate email on job retry (constitution §274-276) | Listener checks stored `last_lockout_email_sent_at` on user; skips send if it matches current `locked_until` timestamp | Implemented |
