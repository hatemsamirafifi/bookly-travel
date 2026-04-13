# Implementation Plan: Traveler Authentication

**Branch**: `001-traveler-auth` | **Date**: 2026-04-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-traveler-auth/spec.md`

## Summary

Implement the traveler account and authentication system for the Bookly tours marketplace. This covers email/password registration, sign-in/sign-out, token-based session management, guest checkout identity handling, automatic account creation from guest bookings, password reset via email, in-session password change, non-blocking email verification, brute-force protection, and security audit logging. The feature spans both the Laravel API backend and the Next.js frontend across all three supported languages (EN, ES, IT).

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel), TypeScript 5.x (Next.js 14)
**Primary Dependencies**: Laravel Sanctum (token auth), Laravel Mail (queued email), Next.js App Router, Tailwind CSS, React Hook Form + Zod (frontend validation)
**Storage**: PostgreSQL (primary), Redis (session cache, rate limiting, queue)
**Testing**: Pest (PHP backend), Vitest + React Testing Library (frontend)
**Target Platform**: Web (server-rendered public pages, client-rendered authenticated pages)
**Project Type**: Web application (API backend + frontend SPA/SSR)
**Performance Goals**: Registration < 60s UX time, sign-in < 15s UX time, password reset email delivered < 2 min
**Constraints**: No social login/OAuth, no account deletion, email-only notifications, 3 languages (EN/ES/IT)
**Scale/Scope**: Consumer-facing travel marketplace, standard web-scale expectations

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Principle | Gate | Status |
|---|-----------|------|--------|
| I | Marketplace-First | Auth system supports traveler, partner, and admin roles on shared user model | ✅ PASS |
| II | Tours-Only Discipline | Auth is role/identity infrastructure — no vertical-specific logic leak | ✅ PASS |
| III | Direct Booking Only | Guest checkout identity enables frictionless instant booking | ✅ PASS |
| IV | Admin-Governed Publishing | Not directly applicable to auth; admin role exists in user model | ✅ PASS |
| V | Platform-Controlled Commerce | Auth supports the identity layer for financial auditability | ✅ PASS |
| VI | Completed-Booking Review Integrity | Not directly applicable to auth | ✅ PASS |
| — | API-First | All auth endpoints are REST API; frontend consumes via API only | ✅ PASS |
| — | Approved Stack | Laravel Sanctum, PostgreSQL, Redis, Next.js 14 — all approved | ✅ PASS |
| — | Thin Controllers | Controllers delegate to AuthService/Actions — no business logic in controllers | ✅ PASS |
| — | No Direct DB in Controllers | All data access through service layer | ✅ PASS |
| — | Server-Side Validation | Form Requests for all write endpoints | ✅ PASS |
| — | Strict Authorization | Four-layer auth: authentication → role → permission → ownership | ✅ PASS |
| — | No Hardcoded Secrets | All secrets from environment variables | ✅ PASS |
| — | Idempotent Financial Flows | Not directly applicable to auth (no payments) | ✅ PASS |
| — | Queued Async Work | Verification emails and password reset emails queued via Redis | ✅ PASS |
| — | Retry-Safe Jobs | Email jobs are idempotent (check sent_at before sending) | ✅ PASS |
| — | Mandatory Audit Logs | Auth events logged per FR-025 (sign-ins, lockouts, resets, etc.) | ✅ PASS |
| — | Minimum Testing Coverage | Auth gate testing is constitutionally mandatory | ✅ PASS |

**Result**: All gates PASS. No violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/001-traveler-auth/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (API contracts)
│   ├── public-auth.md   # Public auth API endpoints
│   └── account.md       # Authenticated account endpoints
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/Auth/
│   │   ├── Services/
│   │   │   └── AuthService.php
│   │   ├── Actions/
│   │   │   ├── RegisterTravelerAction.php
│   │   │   ├── LoginAction.php
│   │   │   ├── LogoutAction.php
│   │   │   ├── ResetPasswordAction.php
│   │   │   ├── ChangePasswordAction.php
│   │   │   ├── ConvertGuestToAccountAction.php
│   │   │   ├── LinkGuestBookingsAction.php
│   │   │   ├── SendVerificationEmailAction.php
│   │   │   └── VerifyEmailAction.php
│   │   ├── Events/
│   │   │   ├── TravelerRegistered.php
│   │   │   ├── TravelerLoggedIn.php
│   │   │   ├── LoginFailed.php
│   │   │   ├── AccountLockedOut.php
│   │   │   ├── PasswordReset.php
│   │   │   ├── PasswordChanged.php
│   │   │   ├── EmailVerified.php
│   │   │   └── GuestConvertedToAccount.php
│   │   └── Listeners/
│   │       └── LogAuthEvent.php
│   ├── Http/
│   │   ├── Controllers/Public/Auth/
│   │   │   ├── RegisterController.php
│   │   │   ├── LoginController.php
│   │   │   ├── LogoutController.php
│   │   │   ├── ForgotPasswordController.php
│   │   │   ├── ResetPasswordController.php
│   │   │   ├── VerifyEmailController.php
│   │   │   └── ResendVerificationController.php
│   │   └── Controllers/Public/Account/
│   │       ├── ProfileController.php
│   │       └── ChangePasswordController.php
│   │   ├── Requests/Auth/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   ├── ForgotPasswordRequest.php
│   │   │   ├── ResetPasswordRequest.php
│   │   │   ├── ChangePasswordRequest.php
│   │   │   └── ConvertGuestRequest.php
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       └── AuthTokenResource.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── GuestIdentity.php
│   │   └── AuthAuditLog.php
│   ├── Jobs/
│   │   ├── SendVerificationEmail.php
│   │   ├── SendPasswordResetEmail.php
│   │   └── AnonymizeStaleGuestIdentities.php
│   ├── Mail/
│   │   ├── VerificationMail.php
│   │   └── PasswordResetMail.php
│   └── Policies/
│       └── UserPolicy.php
├── database/migrations/
│   ├── xxxx_create_users_table.php
│   ├── xxxx_create_guest_identities_table.php
│   ├── xxxx_create_personal_access_tokens_table.php
│   └── xxxx_create_auth_audit_logs_table.php
├── routes/api/
│   └── public.php        # Auth routes registered here
└── tests/
    ├── Feature/Auth/
    │   ├── RegistrationTest.php
    │   ├── LoginTest.php
    │   ├── LogoutTest.php
    │   ├── PasswordResetTest.php
    │   ├── ChangePasswordTest.php
    │   ├── EmailVerificationTest.php
    │   ├── GuestConversionTest.php
    │   ├── BruteForceProtectionTest.php
    │   └── SessionManagementTest.php
    └── Unit/Auth/
        ├── AuthServiceTest.php
        └── LinkGuestBookingsActionTest.php

frontend/
├── src/
│   ├── app/[locale]/auth/
│   │   ├── login/page.tsx
│   │   ├── register/page.tsx
│   │   ├── forgot-password/page.tsx
│   │   ├── reset-password/page.tsx
│   │   └── verify-email/page.tsx
│   ├── app/[locale]/account/
│   │   └── profile/page.tsx
│   ├── components/auth/
│   │   ├── LoginForm.tsx
│   │   ├── RegisterForm.tsx
│   │   ├── ForgotPasswordForm.tsx
│   │   ├── ResetPasswordForm.tsx
│   │   ├── ChangePasswordForm.tsx
│   │   ├── GuestConversionPrompt.tsx
│   │   └── AuthGuard.tsx
│   ├── lib/
│   │   ├── api/auth.ts
│   │   ├── hooks/useAuth.ts
│   │   └── validators/auth.ts
│   └── i18n/
│       ├── en/auth.json
│       ├── es/auth.json
│       └── it/auth.json
└── tests/
    └── components/auth/
        ├── LoginForm.test.tsx
        └── RegisterForm.test.tsx
```

**Structure Decision**: Web application structure (Option 2) — `backend/` (Laravel API) + `frontend/` (Next.js). Auth domain organized under `app/Domains/Auth/` with dedicated services, actions, and events per constitution requirements.

## Complexity Tracking

> No violations found. No entries required.
