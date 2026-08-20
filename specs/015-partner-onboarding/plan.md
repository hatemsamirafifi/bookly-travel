# Implementation Plan: Partner Onboarding

**Branch**: `015-partner-onboarding` | **Date**: 2026-08-18 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/015-partner-onboarding/spec.md`

## Summary

Implement the partner onboarding lifecycle: self-service registration, admin invitation flow, lifecycle status management (pending → approved/rejected/suspended), rejection re-application, and the frontend partner onboarding status view. The backend already has partial infrastructure from Spec 013 (Partner model, PartnerProfile, PartnerSettings, lifecycle Actions, GovernanceAuditLog, PartnerPolicy, PartnerRegistrationController). This spec fills the gaps: invitation system (PartnerInvitation model + flow), admin invitation UI in Filament, partner-facing onboarding status page in Next.js, resubmission endpoint, status-gated middleware enhancement, and transactional emails for all lifecycle milestones.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 11), TypeScript 5+ (Next.js 16)
**Primary Dependencies**: Laravel 11, Sanctum, Filament 3, Next.js 16, Tailwind CSS, next-intl
**Storage**: PostgreSQL (primary), Redis (cache/queues), Cloudflare R2 (file storage)
**Testing**: PHPUnit (backend), Jest (frontend unit), Playwright (E2E)
**Target Platform**: Linux server (Nginx + PHP-FPM), SSR/SSG Next.js frontend
**Project Type**: Web application (API-first Laravel backend + Next.js frontend + Filament admin)
**Performance Goals**: Registration < 3 min completion (SC-001), audit log + email within 5s (SC-003), tour delisting zero-latency (SC-004)
**Constraints**: Constitution-mandated API-First (partner + public surfaces), Filament exception for admin only, immutable audit logs
**Scale/Scope**: ~500 initial partners, admin team of 5-10, 3 locales (EN/ES/IT)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Marketplace-First | ✅ PASS | Partner onboarding is core marketplace supply acquisition; partners control listings via dashboard |
| II. Tours-Only Discipline | ✅ PASS | All features serve the tours vertical exclusively |
| III. Direct Booking Only | ✅ PASS | No changes to booking flow; this spec governs partner lifecycle |
| IV. Admin-Governed Publishing | ✅ PASS | Spec enforces admin approval before partners can create/publish tours (FR-004, FR-005) |
| V. Platform-Controlled Commerce | ✅ PASS | No financial flows in this spec; payout fields exist on PartnerProfile but are deferred |
| API-First | ✅ PASS | Partner registration + onboarding status via API; admin uses Filament (Internal Admin Exception) |
| Internal Admin Exception | ✅ PASS | Admin partner moderation uses Filament only; partner-facing onboarding uses Next.js API-first |
| Mandatory Input Validation | ✅ PASS | FR-002 mandates server-side validation; PartnerRegistrationRequest already enforces this; rejection/suspension reasons rendered with escaped Blade output (spec Edge Case: Rejection Reason Sanitization) |
| Mandatory Audit Logs | ✅ PASS | FR-006 requires immutable audit entries for all lifecycle transitions; reasons stored verbatim for audit integrity |
| Strict Authorization | ✅ PASS | FR-010 enforces ownership boundaries; PartnerRoleMiddleware gates access on every request via current `is_active`/`onboarding_status` checks — already-issued Sanctum tokens are rejected on the next request after suspension (spec Edge Case: Deleted/Inactive Users); admin actions use PartnerPolicy |
| Thin Controllers / Business Logic in Actions | ✅ PASS | Existing pattern: SuspendPartnerAction, ApprovePartnerAction etc. New actions follow same pattern |
| Queueing & Async Work | ✅ PASS | FR-011 emails dispatched via queued Mailables (existing pattern) |
| Testing & Quality Standards | ✅ PASS | Integration + unit tests for lifecycle flows, auth gates, and registration |

## Project Structure

### Documentation (this feature)

```text
specs/015-partner-onboarding/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (via /speckit.tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/
│   │   ├── Partner/
│   │   │   ├── Actions/
│   │   │   │   ├── ResubmitPartnerApplicationAction.php
│   │   │   │   └── CompletePartnerInvitationAction.php
│   │   │   ├── Controllers/
│   │   │   │   ├── Public/
│   │   │   │   │   ├── PartnerRegistrationController.php    (exists)
│   │   │   │   │   └── PartnerInvitationController.php      (new)
│   │   │   │   └── PartnerOnboardingStatusController.php    (new)
│   │   │   ├── Middleware/
│   │   │   │   └── PartnerRoleMiddleware.php                (exists, enhance)
│   │   │   ├── Models/
│   │   │   │   ├── Partner.php                              (exists, enhance)
│   │   │   │   ├── PartnerProfile.php                       (exists)
│   │   │   │   ├── PartnerSettings.php                      (exists)
│   │   │   │   ├── PartnerInvitation.php                    (new)
│   │   │   │   └── Notification.php                         (exists)
│   │   │   ├── Policies/
│   │   │   │   └── TourPolicy.php                           (exists)
│   │   │   ├── Requests/
│   │   │   │   ├── PartnerRegistrationRequest.php            (exists)
│   │   │   │   ├── PartnerResubmitRequest.php               (new)
│   │   │   │   └── CompleteInvitationRequest.php             (new)
│   │   │   └── Services/
│   │   │       └── PartnerOnboardingService.php              (new)
│   │   └── Admin/
│   │       ├── Actions/
│   │       │   ├── ApprovePartnerAction.php                  (exists)
│   │       │   ├── RejectPartnerAction.php                   (exists)
│   │       │   ├── SuspendPartnerAction.php                  (exists)
│   │       │   ├── ReinstatePartnerAction.php                (exists)
│   │       │   └── InvitePartnerAction.php                   (new)
│   │       ├── Filament/
│   │       │   └── PartnerResource.php                       (new/enhance)
│   │       ├── Policies/
│   │       │   └── PartnerPolicy.php                         (exists)
│   │       └── Services/
│   │           └── GovernanceAuditService.php                 (exists)
│   ├── Enums/
│   │   └── PartnerStatus.php                                (exists)
│   └── Mail/
│       ├── PartnerApprovedMail.php                           (exists)
│       ├── PartnerRejectedMail.php                           (exists)
│       ├── PartnerSuspendedMail.php                          (new)
│       ├── PartnerReinstatedMail.php                         (new)
│       ├── PartnerApplicationReceivedMail.php                (new)
│       └── PartnerInvitationMail.php                         (new)
├── database/
│   └── migrations/
│       └── 2026_08_18_100000_create_partner_invitations_table.php (new)
│       └── 2026_08_18_100001_add_rejection_reason_to_partner_profiles_table.php (new)
│       └── 2026_08_18_100002_add_invited_by_admin_to_partners_table.php (new)
├── routes/
│   └── api/
│       ├── public.php                                        (modify: add invitation routes)
│       ├── partner.php                                       (modify: add onboarding status + resubmit)
│       └── admin.php                                         (modify: add invitation + partner management routes)
└── tests/
    ├── Feature/
    │   └── Partner/
    │       ├── PartnerRegistrationTest.php                    (new)
    │       ├── PartnerLifecycleTest.php                       (new)
    │       ├── PartnerInvitationTest.php                      (new)
    │       ├── PartnerResubmissionTest.php                    (new)
    │       └── PartnerOnboardingStatusTest.php                (new)
    └── Unit/
        └── Partner/
            ├── PartnerCanTransitionToTest.php                 (new)
            └── PartnerInvitationModelTest.php                 (new)

frontend/
├── src/
│   ├── app/[locale]/(auth)/partner-register/
│   │   └── page.tsx                                          (new)
│   ├── app/[locale]/(auth)/partner-invite/[token]/
│   │   └── page.tsx                                          (new)
│   ├── app/[locale]/(partner)/partner/onboarding/
│   │   └── page.tsx                                          (new)
│   ├── components/partner/
│   │   ├── OnboardingStatusBanner.tsx                        (new)
│   │   ├── PartnerRegistrationForm.tsx                        (new)
│   │   ├── InvitationAcceptanceForm.tsx                       (new)
│   │   └── ResubmissionForm.tsx                               (new)
│   ├── lib/api/partner.ts                                    (modify: add onboarding + invitation API calls)
│   ├── types/partner.ts                                      (modify: add onboarding types)
│   └── messages/
│       ├── en.json                                           (modify: add onboarding translations)
│       ├── es.json                                           (modify: add onboarding translations)
│       └── it.json                                           (modify: add onboarding translations)
└── tests/
    └── partner/
        ├── onboarding-status.test.tsx                        (new)
        ├── registration.test.tsx                             (new)
        └── invitation.test.tsx                                (new)
```

**Structure Decision**: Web application (Option 2). The project follows the established monorepo pattern with `backend/` (Laravel API + Filament admin) and `frontend/` (Next.js 16). The Partner domain already exists under `backend/app/Domains/Partner/`. New files extend the existing structure following established conventions (Actions, Controllers, Requests, Models, Policies, Services). Frontend adds pages under the `(auth)` layout group for unauthenticated registration/invitation flows and under `(partner)/partner/` for the authenticated onboarding status view.

## Complexity Tracking

> No constitution violations. The existing architecture supports all spec requirements without deviation.