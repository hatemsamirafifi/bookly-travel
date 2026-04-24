# Research: Traveler Registration

**Feature**: 003-traveler-registration
**Date**: 2026-04-18
**Parent Research**: [001-traveler-auth/research.md](../001-traveler-auth/research.md)

## Research Scope

This feature inherits the full technology stack and architectural decisions from the parent feature `001-traveler-auth`. No new technology choices or NEEDS CLARIFICATION items exist. This document records decisions specific to the registration implementation.

## Decision 1: Registration Action Pattern

**Decision**: Single-purpose Action class (`RegisterTravelerAction`) orchestrating sub-actions.

**Rationale**: The constitution mandates thin controllers and business logic in action classes. The registration flow has multiple side effects (create user, link bookings, queue email, dispatch event) that must be atomic. An action class encapsulates this orchestration cleanly.

**Alternatives considered**:
- Service class with `register()` method — would grow into a god class as more auth flows are added.
- Controller-level orchestration — violates constitution's thin controller mandate.

## Decision 2: Guest Booking Linkage Strategy

**Decision**: Separate `LinkGuestBookingsAction` called from within `RegisterTravelerAction`.

**Rationale**: 
- Separation of concerns — linkage logic is reusable (also needed in Phase 6: Guest Conversion).
- The bookings table may not exist yet (spec 007). The action must check for table existence before querying and no-op gracefully if absent.
- Guest identities are queried by email; associated bookings' `user_id` is updated to the new user's ID.

**Alternatives considered**:
- Event-driven linkage (listen for TravelerRegistered, link async) — introduces ordering risks; the user would not see linked bookings immediately after registration.
- Inline in RegisterTravelerAction — reduces reusability for Phase 6.

## Decision 3: Verification Email Dispatch

**Decision**: Queue a `SendVerificationEmail` job (Redis) from `RegisterTravelerAction`. The job creates a signed URL using Laravel's built-in `MustVerifyEmail` support and sends a `VerificationMail` mailable with multi-language content.

**Rationale**: 
- Non-blocking (FR-006) — traveler is not blocked waiting for email.
- Retry-safe — resending a verification email is idempotent.
- Constitution requires long-running tasks to be queued.

**Alternatives considered**:
- Synchronous send — violates non-blocking requirement and slows registration response.
- Laravel's built-in `Registered` event + `SendEmailVerificationNotification` — less control over email template and language; we need custom multi-language support.

## Decision 4: No Password Confirmation Field

**Decision**: Registration form collects 3 fields only (name, email, password). No `password_confirmation` field.

**Rationale**: Clarification session 2026-04-18 confirmed this decision. Password strength rules (min 8, uppercase, lowercase, digit) are sufficient quality assurance. Fewer fields reduce friction and improve conversion.

**Alternatives considered**:
- 4-field form with confirmation — adds UX friction with minimal security benefit when strength rules are strong.
- Optional confirmation accepted server-side — adds API complexity with no clear UX benefit.

## Decision 5: Registration Form Component Architecture

**Decision**: A `RegisterForm` client component with:
- Zod validation using the existing `registerSchema`.
- Controlled form state with React `useState`.
- API submission via `authApi.register()`.
- Error display per field (server validation errors mapped to form fields).
- Loading state during submission.

**Rationale**: The Zod schemas and API client already exist from Phase 2. The component uses them directly. Client-side validation provides immediate feedback (FR-010) before the server request.

**Alternatives considered**:
- React Hook Form — adds a dependency not in the approved stack.
- Server Actions (Next.js) — the API-first constitution mandates that the frontend consumes the Laravel API, not Next.js server actions.

## Decision 6: Registration Page Route and Return-to-URL

**Decision**: Registration page at `frontend/src/app/[locale]/auth/register/page.tsx`. Return-to-URL is read from `?returnUrl=` query parameter. On successful registration, the user is redirected to `returnUrl` or the locale homepage (`/[locale]/`).

**Rationale**: Consistent with the AuthGuard component which already sets `returnUrl` when redirecting to login. Registration page follows the same pattern for parity.

## Technology Summary (No Changes from Parent)

| Decision | Choice | Source |
|----------|--------|--------|
| Backend framework | Laravel 11 | Constitution |
| Authentication | Laravel Sanctum 4 | Constitution |
| Frontend | Next.js 14 + TypeScript | Constitution |
| Database | PostgreSQL 15 | Constitution |
| Queue | Redis 7 | Constitution |
| Email (dev) | Mailpit (SMTP on port 1025) | Phase 1 Docker setup |
| Validation | Laravel Form Requests (server) + Zod (client) | Constitution + Phase 2 |
| Localization | next-intl 4 (3 locales: EN/ES/IT) | Phase 1 + Phase 2 |
