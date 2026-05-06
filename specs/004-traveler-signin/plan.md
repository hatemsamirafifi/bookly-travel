# Implementation Plan: Traveler Sign-In and Sign-Out

**Branch**: `004-traveler-signin` | **Date**: 2026-04-25 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/004-traveler-signin/spec.md`

## Summary

Implement traveler sign-in with email and password, sign-out with single-token revocation, brute-force protection with escalating lockout tiers (1min → 5min → 30min), and multi-language support (EN, ES, IT). This feature builds directly on the foundational infrastructure from Phase 2 (002-foundational-implementation) and the registration flow from Phase 3 (003-traveler-registration). No new database migrations, events, or listeners are required — all supporting infrastructure already exists.

**Technical approach**: Follow the established domain-driven action pattern used in `RegisterTravelerAction`. Implement `AuthenticateTravelerAction` for credential verification, brute-force tracking, lockout enforcement, and token issuance. Implement `LogoutTravelerAction` for single-token revocation. Create thin `LoginController` and `LogoutController` with Form Request validation. Frontend follows the exact `RegisterForm` pattern: server component page, `LoginForm` client component with `react-hook-form` + `zodResolver`, `AuthGuard` guest-only protection, and `next-intl` localization.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 11), TypeScript 5.7 (Next.js 16.2.3, React 19.2.4)
**Primary Dependencies**: Laravel Sanctum (auth tokens), react-hook-form 7.54 + zod 4.3.6 (client validation), next-intl 4.9.1 (i18n), Tailwind CSS 4 (styling)
**Storage**: PostgreSQL (users, auth_audit_logs, personal_access_tokens), Redis (queue, cache)
**Testing**: Pest (Laravel) for backend feature tests
**Target Platform**: Linux server (backend), Web browser (frontend)
**Project Type**: Web application — backend API + frontend Next.js app
**Performance Goals**: Sign-in response < 3 seconds; page render < 15 seconds end-to-end
**Constraints**: Rate limit 10 req/min per IP on auth endpoints (`throttle:auth`); generic error messages to prevent email enumeration; no new DB columns or migrations
**Scale/Scope**: Single feature within Phase 1 MVP; supports EN/ES/IT locales; no social OAuth
**Terminology**: Use "sign in" / "sign out" for all user-facing copy (buttons, labels, page titles, translations). Use "login" / "logout" for technical identifiers (API endpoints, controller/action filenames, schema names, function names). Example: the page title reads "Sign In" but the endpoint is `/login` and the file is `LoginController.php`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Marketplace-First | ✅ Pass | Sign-in enables travelers to access their bookings/reviews, supporting marketplace transactions |
| II. Tours-Only Discipline | ✅ Pass | No scope expansion; auth is infrastructure for tours marketplace |
| III. Direct Booking Only | ✅ Pass | Not directly applicable; sign-in enables booking access |
| IV. Admin-Governed Publishing | ✅ Pass | Not directly applicable |
| V. Platform-Controlled Commerce | ✅ Pass | Audit logging ensures financial traceability |
| VI. Completed-Booking Review Integrity | ✅ Pass | Sign-in enables review submission for completed bookings |
| Security-First Mandate | ✅ Pass | Brute-force protection, rate limiting, generic error messages, audit logging all implemented |
| Mandatory Input Validation | ✅ Pass | Server-side Form Request + client-side Zod schema |
| Strict Authorization | ✅ Pass | Sanctum tokens; role checks handled by existing middleware |
| Secrets Handling | ✅ Pass | No secrets in code; all env-based |
| Thin Controllers | ✅ Pass | Controllers delegate to action classes |
| No Direct DB Access from Controllers | ✅ Pass | All DB access via actions |
| Business Logic in Services/Actions | ✅ Pass | `AuthenticateTravelerAction` and `LogoutTravelerAction` |
| Mandatory Audit Logs | ✅ Pass | `TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut` → `LogAuthEvent` → `auth_audit_logs` |
| Minimum Testing Coverage | ✅ Pass | Feature tests for sign-in, sign-out, brute-force, edge cases |

**Re-evaluation after Phase 1**: All gates pass. No violations or justifications needed.

## Project Structure

### Documentation (this feature)

```text
specs/004-traveler-signin/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   ├── login-api.md
│   └── logout-api.md
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/Auth/Actions/
│   │   ├── AuthenticateTravelerAction.php   # Credential verification + brute-force logic
│   │   └── LogoutTravelerAction.php         # Single-token revocation
│   ├── Http/
│   │   ├── Controllers/Public/Auth/
│   │   │   ├── LoginController.php          # Invokable login controller
│   │   │   └── LogoutController.php         # Invokable logout controller
│   │   ├── Requests/Auth/
│   │   │   └── LoginRequest.php             # Form Request validation
│   │   └── Resources/
│   │       └── UserResource.php             # Existing — reused
│   └── Models/
│       ├── User.php                         # Existing — reused
│       └── AuthAuditLog.php                 # Existing — reused
├── routes/
│   └── api/public.php                       # Add POST /login, POST /logout
└── tests/Feature/Auth/
    ├── LoginTest.php                        # Sign-in, brute-force, lockout, edge cases
    └── LogoutTest.php                       # Sign-out, token revocation, multi-session

frontend/
├── src/
│   ├── app/[locale]/auth/login/
│   │   └── page.tsx                         # Server component, metadata, AuthGuard
│   ├── components/auth/
│   │   ├── LoginForm.tsx                    # Client component, react-hook-form
│   │   ├── AuthGuard.tsx                    # Existing — reused
│   │   └── RegisterForm.tsx                 # Existing — reference pattern
│   ├── lib/
│   │   ├── api/auth.ts                      # Existing — authApi.login/logout reused
│   │   ├── hooks/useAuth.tsx                # Existing — login/logout methods reused
│   │   └── validators/auth.ts               # Existing — loginSchema reused
│   └── i18n/                                # Existing routing and request config
├── messages/
│   ├── en.json                              # Add login page translations
│   ├── es.json                              # Add login page translations
│   └── it.json                              # Add login page translations
└── next.config.ts
```

**Structure Decision**: The existing two-project layout (backend API + frontend Next.js) is used unchanged. The sign-in feature follows the exact patterns established by 003-traveler-registration: thin invokable controllers, domain actions for business logic, Form Request validation, Zod + react-hook-form on the frontend, next-intl localization, and Pest feature tests.

## Complexity Tracking

> No violations or complexity justifications required. All design decisions align with the constitution and existing project patterns.
