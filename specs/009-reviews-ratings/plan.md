# Implementation Plan: Reviews & Ratings

**Branch**: `009-reviews-ratings` | **Date**: 2026-05-13 | **Spec**: [spec.md](../specs/009-reviews-ratings/spec.md)
**Input**: Feature specification from `/specs/009-reviews-ratings/spec.md`

## Summary

Add a Reviews & Ratings system to the Bookly marketplace. Travelers can submit one star rating (1-5) and optional comment per completed booking within 30 days of their tour date. Reviews are displayed on the public tour detail page with aggregate ratings. Partners view their tour reviews via a dashboard. Admins moderate reviews with hide/reinstate actions tracked in an audit trail. An automated profanity filter flags suspicious content for admin attention while publishing immediately (post-moderation).

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 11.x) / TypeScript 5 (Next.js 16)
**Primary Dependencies**: Laravel Sanctum, React Hook Form, Zod, next-intl
**Storage**: PostgreSQL 15, Redis 7
**Testing**: Pest (backend), Jest (frontend)
**Target Platform**: Web application (Responsive layout)
**Project Type**: Multi-vendor marketplace
**Performance Goals**: Sub-500ms API response for review queries; tour detail page loads reviews within 3s
**Constraints**: Reviews must be append-only (no deletion); editing limited to 48-hour window; rate limited at 10/hr/traveler
**Scale/Scope**: International userbase (en, es, it); reviews per tour potentially in hundreds

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [X] I. Marketplace-First: Reviews are tied to partner-owned tours via platform-controlled booking verification. Partners do not bypass the platform.
- [X] II. Tours-Only Discipline: Reviews apply exclusively to tours. No hotel/flight/transfer review support.
- [X] III. Direct Booking Only: Reviews are tied to completed bookings made through the direct booking flow. No new booking model introduced.
- [X] IV. Admin-Governed Publishing: Admin has moderation control over review visibility (hide/reinstate). FR-006 covers this.
- [X] V. Platform-Controlled Commerce: FR-008 requires verified payment records for review eligibility, enforcing platform-controlled commerce.
- [X] VI. Completed-Booking Review Integrity: This feature IS the implementation of this principle. FR-002(a-b) enforces completed booking check; FR-001 enforces one review per booking.
- [X] API-First: All surfaces consume Laravel API exclusively. Frontend components call REST endpoints.
- [X] Thin Controllers: Review creation, editing, moderation delegated to Action/Service classes.
- [X] No Direct DB Access from Controllers: Data access through Action classes and Eloquent models with proper relationships.
- [X] Audit Logging: FR-013 mandates moderation audit trail. Booking audit trail already exists from spec 007.
- [X] Security-First: Rate limiting (FR-012), ownership checks (FR-002), payment verification (FR-008).

**Gate Result**: All gates pass. No violations to justify.

## Project Structure

### Documentation (this feature)

```text
specs/009-reviews-ratings/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (API contracts)
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)
```text
backend/
├── app/
│   ├── Domains/
│   │   └── Reviews/
│   │       ├── Actions/
│   │       │   ├── SubmitReviewAction.php
│   │       │   ├── EditReviewAction.php
│   │       │   ├── HideReviewAction.php
│   │       │   └── ReinstateReviewAction.php
│   │       ├── Events/
│   │       │   ├── ReviewSubmitted.php
│   │       │   └── ReviewFlagged.php
│   │       ├── Listeners/
│   │       │   └── UpdateTourAggregateRating.php
│   │       ├── Models/
│   │       │   ├── Review.php
│   │       │   └── ReviewAuditTrail.php
│   │       ├── Services/
│   │       │   ├── ReviewValidationService.php
│   │       │   └── ProfanityFilterService.php
│   │       └── Controllers/
│   │           ├── Public/
│   │           │   └── ReviewController.php
│   │           ├── Partner/
│   │           │   └── PartnerReviewController.php
│   │           └── Admin/
│   │               └── AdminReviewController.php
│   └── Http/
│       └── Resources/
│           └── ReviewResource.php
├── database/
│   └── migrations/
│       ├── 2026_05_13_100001_create_reviews_table.php
│       └── 2026_05_13_100002_create_review_audit_trails_table.php
├── routes/
│   ├── api/
│   │   ├── public.php  (add review routes)
│   │   ├── partner.php (add partner review routes)
│   │   └── admin.php   (add admin review routes)
└── tests/
    └── Feature/
        └── Reviews/
            ├── SubmitReviewTest.php
            ├── EditReviewTest.php
            ├── ViewReviewsTest.php
            ├── PartnerReviewsTest.php
            ├── AdminModerationTest.php
            └── ProfanityFilterTest.php

frontend/
├── src/
│   ├── components/
│   │   └── reviews/
│   │       ├── ReviewForm.tsx
│   │       ├── ReviewList.tsx
│   │       ├── ReviewCard.tsx
│   │       ├── StarRating.tsx
│   │       └── AggregateRating.tsx
│   └── lib/
│       └── reviews/
│           └── review-api.ts
└── messages/
    ├── en.json  (add review keys)
    ├── es.json  (add review keys)
    └── it.json  (add review keys)
```

**Structure Decision**: Standard Bookly web application structure. Backend follows Domains/Reviews pattern with Action classes, thin controllers, and dedicated services. Frontend components integrate into existing tour detail and booking detail pages.

## Complexity Tracking

> No constitution violations to justify.
