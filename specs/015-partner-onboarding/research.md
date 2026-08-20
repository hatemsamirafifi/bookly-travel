# Research: Partner Onboarding

**Feature**: 015-partner-onboarding
**Date**: 2026-08-18

## Research Tasks & Findings

### 1. Existing Partner Infrastructure Assessment

**Decision**: Extend existing Partner domain rather than create new domain.

**Rationale**: The codebase already has a mature `App\Domains\Partner` domain from Specs 012/013 with:
- `Partner` model with `onboarding_status` enum (`pending`, `approved`, `rejected`, `suspended`) and lifecycle guard `canTransitionTo()`
- `PartnerProfile` model with company details, contact info, payout fields, and encrypted sensitive fields
- `PartnerSettings` model for notification preferences
- `PartnerRegistrationController` (public self-registration endpoint at `/api/public/auth/partners/register`)
- `PartnerRegistrationRequest` (Form Request with server-side validation)
- `PartnerRoleMiddleware` (auth + role + active check)
- `PartnerPolicy` (admin authorization for lifecycle actions)
- Admin Actions: `ApprovePartnerAction`, `RejectPartnerAction`, `SuspendPartnerAction`, `ReinstatePartnerAction`
- `GovernanceAuditService` (append-only audit logging)
- `PartnerApprovedMail`, `PartnerRejectedMail` (existing mailables)
- `PartnerStatus` enum

**Alternatives considered**: Creating a separate `Onboarding` subdomain — rejected because the onboarding lifecycle is inherently the Partner lifecycle; separating would create cross-domain coupling and duplicate the status machine.

### 2. Partner Invitation Flow Design

**Decision**: Single-use, time-limited, cryptographically-secure invitation tokens stored in a `partner_invitations` table with auto-approval on completion.

**Rationale**: 
- Tokens must be single-use to prevent replay attacks (spec FR-009)
- Expiration prevents indefinite open invitations (security best practice)
- Auto-approval on invitation completion satisfies the clarification: "Completing registration via a valid admin invitation link immediately sets the partner account to `approved` status"
- Storing invitations in the database enables admin tracking, audit trails, and revocation capability

**Alternatives considered**:
- Signed URLs (no DB storage) — rejected: no audit trail, cannot revoke or track usage
- Laravel `password_reset_tokens` table reuse — rejected: different semantics, fields don't match (need `company_name`, `contact_person`, admin reference)

### 3. Partner Re-Application Flow

**Decision**: Rejected partners update their existing `PartnerProfile` record and transition `onboarding_status` back to `pending` via a dedicated `ResubmitPartnerApplicationAction`, with an audit log entry for the `partner.resubmit` action.

**Rationale**:
- The existing `Partner::canTransitionTo()` already allows `rejected → pending`
- A dedicated Action follows the existing pattern (ApprovePartnerAction, etc.)
- Audit entry satisfies FR-006 requirement for `partner.resubmit` lifecycle event
- The `PartnerProfile` already has all the fields that need updating; no new table needed

**Alternatives considered**:
- Creating a new application record per attempt — rejected: spec clarifies "update their submitted profile/company details and resubmit", not create a new application; also simpler data model
- In-place edit without audit — rejected: FR-006 mandates audit trail for all transitions

### 4. Onboarding Status Gating Middleware Enhancement

**Decision**: Enhance `PartnerRoleMiddleware` to check `onboarding_status` and return appropriate error responses for non-approved partners.

**Rationale**:
- Current middleware only checks `is_active` and token scope
- FR-004 requires blocking `pending`, `rejected`, and `suspended` partners from tour creation/publishing
- Rather than creating a separate middleware, enhancing the existing one keeps the auth gate in one place
- The middleware should allow read access (viewing dashboard, profile) but block write actions for non-approved partners

**Alternatives considered**:
- Separate `OnboardingStatusMiddleware` — rejected: adds middleware pipeline complexity; one auth gate is clearer
- Policy-based gating only — rejected: policies are per-resource, but onboarding status is a global partner-level gate that should apply before reaching any controller

### 5. Suspension Tour Delisting Strategy

**Decision**: Already implemented in `Partner::removeToursFromDiscovery()` — iterates published tours, sets status to `draft`, saves individually to trigger Scout observer. Reinstatement flips `is_active` back but does NOT auto-republish (partner must resubmit per governed publishing flow).

**Rationale**: This existing implementation exactly matches SC-004 (zero-latency delisting) and FR-007. No changes needed.

**Alternatives considered**: N/A — existing code matches requirements.

### 6. Filament Admin Resource for Partner Management

**Decision**: Create `PartnerResource` in Filament with table, form, and action buttons for approve/reject/suspend/reinstate/invite.

**Rationale**:
- Constitution's Internal Admin Exception explicitly approves Filament for admin surfaces
- Existing admin moderation uses Filament (Spec 013 established `TourResource`, `ReviewResource`)
- Filament's action system maps cleanly to the existing Action classes
- Invitation form requires custom Filament form with email/company name fields

**Alternatives considered**:
- Separate admin API + Next.js admin dashboard — rejected: violates Internal Admin Exception which specifically approves Filament for admin-only surfaces

### 7. Frontend Onboarding Status Page

**Decision**: Create a new `/[locale]/(partner)/partner/onboarding` page showing the partner's current `onboarding_status`, rejection reason (if applicable), and a resubmission form for rejected partners. Also create `/[locale]/(auth)/partner-register` and `/[locale]/(auth)/partner-invite/[token]` pages.

**Rationale**:
- Registration is an unauthenticated flow → `(auth)` layout group (matches existing `/auth/register` pattern)
- Invitation acceptance is also unauthenticated → `(auth)` layout group
- Onboarding status is an authenticated partner view → `(partner)` layout group
- The `(auth)` group already has login/register pages; adding partner variants follows established conventions

**Alternatives considered**:
- Modal-based registration on existing auth page — rejected: spec requires a dedicated registration page with company details form; too complex for a modal
- Separate `/partner-auth` layout — rejected: would duplicate auth logic; reusing `(auth)` is DRY

### 8. Transactional Email Requirements

**Decision**: Create 4 new Mailables: `PartnerApplicationReceivedMail`, `PartnerSuspendedMail`, `PartnerReinstatedMail`, `PartnerInvitationMail`. Existing `PartnerApprovedMail` and `PartnerRejectedMail` cover their respective transitions.

**Rationale**:
- FR-011 requires localized emails for: Application Received, Application Approved (exists), Application Rejected (exists), Invitation Received, Account Suspended, and Account Reinstated (added per US5 acceptance 2 — the original FR-011 list was incomplete; reinstatement notification is a lifecycle milestone)
- All emails queued via Laravel's queue system (Constitution: Long-Running Jobs MUST Be Queued)
- Existing pattern: `PartnerApprovedMail`, `PartnerRejectedMail` — follow same structure
- **Output escaping**: All email Blade views rendering rejection/suspension reasons MUST use escaped Blade syntax `{{ $reason }}`, not raw `{!! $reason !!}`. Reasons are stored verbatim for audit integrity; XSS prevention is at render time (spec Edge Case: Rejection Reason Sanitization)

**Admin in-app notifications (distinct from FR-011 emails)**:
- FR-013: When a new partner self-registration is submitted, admins with `manage_partners` receive an in-app `Notification` row (not an email). This is the existing Notification model mechanism, distinct from the partner-facing transactional emails governed by FR-011.
- US1 acceptance 4 codifies this: the admin notification is in-app only, containing the applicant's company name + contact email.

**Alternatives considered**:
- Notification class instead of Mailable — rejected: spec requires transactional emails (not in-app notifications only); Mailables are the existing pattern for lifecycle emails
- Combining all emails into one class with templates — rejected: each email has different content, recipients, and trigger conditions; separate classes follow SRP
- Emailing admins on new applications — rejected: admins use Filament and receive in-app notifications; emailing admins for every application is unnecessary noise

### 9. Database Schema Changes

**Decision**: 
1. New `partner_invitations` table
2. Add `rejection_reason` column to `partner_profiles` (already used in `RejectPartnerAction` but check if column exists)
3. Add `invited_by_admin` boolean to `partners` table for invitation-sourced accounts
4. The `PartnerProfile` migration already has most needed fields

**Rationale**: Minimal schema changes. The `partner_profiles` table already has `company_name`, `contact_email`, `contact_phone`, `business_description`, `business_address`, `website`, `logo_url`. The invitation flow needs its own table because it's a separate entity with distinct lifecycle (token generation, expiration, consumption).

### 10. API Route Design

**Decision**: 
- **Public (unauthenticated)**: `POST /api/public/auth/partners/register` (exists), `GET /api/public/auth/partners/invite/{token}`, `POST /api/public/auth/partners/invite/{token}/complete`
- **Partner (authenticated)**: `GET /api/partner/onboarding-status`, `POST /api/partner/onboarding/resubmit`
- **Admin (Filament)**: Partner CRUD + lifecycle actions via Filament Resource; invitation creation via Filament Action

**Rationale**: 
- Registration and invitation acceptance must be unauthenticated (new users)
- Onboarding status check and resubmission require partner auth
- Admin actions go through Filament (Internal Admin Exception)
- Existing route patterns: `/api/public/auth/*` for unauthenticated, `/api/partner/*` for authenticated partners

## Summary

All research items resolved. No NEEDS CLARIFICATION items remain. The primary technical approach is to extend the existing `Domains/Partner` and `Domains/Admin` domains with invitation infrastructure, onboarding status endpoints, and Filament admin resource, while the frontend adds three new pages (registration, invitation acceptance, onboarding status) following established patterns.