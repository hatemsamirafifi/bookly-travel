# Implementation Plan: Traveler Registration

**Branch**: `003-traveler-registration` | **Date**: 2026-04-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-traveler-registration/spec.md`
**Parent**: `/specs/001-traveler-auth/` (inherits Phase 2 infrastructure)

## Summary

Implement the traveler registration flow: a public POST `/api/public/auth/register` endpoint that creates a traveler account (name, email, password — no confirmation field), issues a Sanctum token, queues a verification email, links any guest bookings by email, and dispatches the TravelerRegistered event for audit logging. On the frontend, a locale-aware registration page with a client-validated form, API integration, and return-to-URL redirect behavior.

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 11) + TypeScript 5 (Next.js 16)
**Primary Dependencies**: Laravel Sanctum 4, next-intl 4, Zod 4, react-hook-form 7
**Storage**: PostgreSQL 15 (users, guest_identities, auth_audit_logs tables — already migrated)
**Testing**: Pest 3 (backend), manual verification (frontend, Phase 10)
**Target Platform**: Web (responsive, SSR for public pages)
**Project Type**: Web application (API backend + SPA frontend)
**Performance Goals**: Registration completes in under 3 seconds, verification email queued in under 1 second
**Constraints**: Rate limited at 10 req/min per IP, no password confirmation field
**Scale/Scope**: Standard web traffic, 3 locales (EN/ES/IT)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Marketplace-First | ✅ Pass | Registration creates traveler accounts — marketplace participant |
| II. Tours-Only Discipline | ✅ Pass | Auth is a core platform service, not a vertical expansion |
| III. Direct Booking Only | ✅ N/A | Not a booking feature |
| IV. Admin-Governed Publishing | ✅ N/A | Not a publishing feature |
| V. Platform-Controlled Commerce | ✅ N/A | No payments |
| VI. Completed-Booking Review Integrity | ✅ N/A | No reviews |
| API-First | ✅ Pass | Registration is a REST API endpoint consumed by Next.js frontend |
| Approved Stack | ✅ Pass | Laravel + Sanctum + Next.js + PostgreSQL + Redis |
| Thin Controllers | ✅ Pass | Controller delegates to RegisterTravelerAction |
| No Direct DB in Controllers | ✅ Pass | User creation in Action class, not controller |
| Business Logic in Services/Actions | ✅ Pass | RegisterTravelerAction, LinkGuestBookingsAction, SendVerificationEmailAction |
| Mandatory Input Validation | ✅ Pass | Server-side via RegisterRequest Form Request |
| Strict Authorization | ✅ N/A | Public endpoint — no auth required |
| Long-Running Jobs Queued | ✅ Pass | Verification email dispatched to Redis queue |
| Retry-Safety | ✅ Pass | SendVerificationEmail job is idempotent (re-sending is safe) |
| Mandatory Audit Logs | ✅ Pass | TravelerRegistered event → LogAuthEvent listener → auth_audit_logs |
| Minimum Testing Coverage | ✅ Pass | Feature tests for registration flow (RegistrationTest.php) |
| Security-First | ✅ Pass | Server-side validation, rate limiting, hashed passwords, explicit email-taken error (enumeration accepted per SC-006) |

**GATE RESULT**: ✅ ALL PASS — No violations.

## Project Structure

### Documentation (this feature)

```text
specs/003-traveler-registration/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (inherits from 001-traveler-auth)
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (inherits from 001-traveler-auth)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/Auth/
│   │   ├── Actions/
│   │   │   ├── RegisterTravelerAction.php      # NEW
│   │   │   ├── LinkGuestBookingsAction.php      # NEW
│   │   │   └── SendVerificationEmailAction.php  # NEW
│   │   └── Events/                              # EXISTS (Phase 2)
│   ├── Http/
│   │   ├── Controllers/Public/Auth/
│   │   │   └── RegisterController.php           # NEW
│   │   ├── Requests/Auth/
│   │   │   └── RegisterRequest.php              # NEW
│   │   └── Resources/
│   │       └── UserResource.php                 # EXISTS (Phase 2)
│   ├── Jobs/
│   │   └── SendVerificationEmail.php            # NEW
│   ├── Mail/
│   │   └── VerificationMail.php                 # NEW
│   └── Models/                                  # EXISTS (Phase 2)
├── database/factories/                          # EXISTS (Phase 2)
├── routes/api/
│   └── public.php                               # MODIFY (add register route)
└── tests/Feature/Auth/
    └── RegistrationTest.php                     # NEW

frontend/
├── src/
│   ├── app/[locale]/auth/register/
│   │   └── page.tsx                             # NEW
│   ├── components/auth/
│   │   └── RegisterForm.tsx                     # NEW
│   ├── lib/
│   │   ├── api/auth.ts                          # EXISTS (Phase 2)
│   │   ├── hooks/useAuth.tsx                    # EXISTS (Phase 2)
│   │   └── validators/auth.ts                   # EXISTS (Phase 2)
│   └── i18n/                                    # EXISTS (Phase 2)
└── messages/
    ├── en.json                                  # MODIFY (add register form keys)
    ├── es.json                                  # MODIFY
    └── it.json                                  # MODIFY
```

**Structure Decision**: Web application (Option 2). Backend at `backend/`, frontend at `frontend/`. Registration adds 7 new backend files (action, controller, request, job, mailable, action, test) and 2 new frontend files (page, component), modifying 4 existing files (routes, 3 translation files).

## Complexity Tracking

> No constitution violations — table not needed.
