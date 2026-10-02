# Tasks: Bookly Web Experience Redesign

**Input**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`

**Tests**: Required by the feature specification and Constitution v2.0.0. Write focused failing tests before each implementation slice.

**Scope note**: Tasks are organized by user story. The owner-requested AI translation slice is US5 and may be implemented before the P1 visual redesign once foundational gates pass.

## Phase 1: Setup

**Goal**: Lock baseline and reproducible acceptance environment.

- [X] T001 Record existing route/API/browser baseline and current dirty-worktree changes in specs/018-bookly-ui-redesign/research.md
- [X] T002 [P] Document four-width, EN/ES/IT, keyboard and production-build performance acceptance matrix in specs/018-bookly-ui-redesign/quickstart.md
- [X] T003 [P] Add backend-only Gemini model/key placeholders (never a real key) in backend/.env.example and backend/config/services.php

## Phase 2: Foundational

**Goal**: Shared contracts and design system before dependent page work.

- [X] T004 [P] Add contract fixtures for public tour detail and partner content in backend/tests/Feature/Search/TourDetailTest.php and backend/tests/Feature/Partner/TourCreateTest.php
- [X] T005 [P] Define semantic Bookly navy/gold/surface/focus/motion tokens and Plus Jakarta Sans through next/font in frontend/src/app/globals.css, frontend/src/lib/design-tokens.ts and frontend/src/app/[locale]/layout.tsx
- [X] T006 [P] Add shared primitive accessibility tests in frontend/src/components/ui/__tests__/design-system.test.tsx
- [X] T007 Implement shared responsive public/auth/traveler/partner shells in frontend/src/components/layout/Header.tsx and frontend/src/components/layout/Footer.tsx
- [X] T049 [P] Add keyboard/focus/reduced-motion tests for shared primitives and mobile navigation in frontend/src/components/ui/__tests__/design-system.test.tsx and frontend/tests/e2e/auth-navigation.spec.ts

**Checkpoint**: Contracts and shared UI shell have focused passing tests.

## Phase 3: User Story 1 — Discover a relevant tour (P1)

**Independent test**: Search from home, filter/sort on desktop/mobile, navigate back and retain criteria; empty results and data-backed cards behave honestly.

- [X] T008 [P] [US1] Add discovery browser regressions in frontend/tests/e2e/homepage.spec.ts and frontend/tests/e2e/search.spec.ts
- [X] T009 [US1] Make localized search/card projection use only current derived content or EN fallback in backend/app/Models/Tour.php and backend/app/Domains/Search/Transformers/TourCardTransformer.php
- [X] T010 [US1] Redesign data-backed homepage discovery in frontend/src/app/[locale]/(public)/page.tsx
- [X] T011 [US1] Redesign filter/sort/history-preserving results in frontend/src/app/[locale]/(public)/search/page.tsx
- [X] T012 [US1] Verify empty/error/loading and mobile filters in frontend/tests/e2e/search.spec.ts
- [X] T050 [US1] Make data-backed TourCard and category/destination listing layouts consume shared tokens without changing their API/search contracts in frontend/src/components/search/TourCard.tsx, frontend/src/app/[locale]/(public)/categories/[slug]/page.tsx and frontend/src/app/[locale]/(public)/destinations/[slug]/page.tsx

## Phase 4: User Story 2 — Evaluate a tour and begin booking (P1)

**Independent test**: A published tour displays truthful ordered sections, gallery and guide languages; valid availability reaches checkout and missing content is omitted.

- [X] T013 [P] [US2] Add public detail contract/fallback/guide-language tests in backend/tests/Feature/Search/TourDetailTest.php
- [X] T014 [P] [US2] Add EN/ES/IT component tests for ready/fallback content and visible notice in frontend/src/components/tour/__tests__/TourDetail.test.tsx
- [X] T015 [US2] Select a revision-current locale or disclosed EN fallback, retaining existing JSON fields, in backend/app/Domains/Search/Actions/GetTourDetailAction.php
- [X] T016 [US2] Add safe `translation_status` and actual content locale to frontend/src/lib/api/types.ts and frontend/src/components/seo/StructuredData.tsx
- [X] T017 [US2] Render ordered optional sections, live-guide languages and booking action in frontend/src/components/tour/TourDetail.tsx
- [X] T018 [US2] Add gallery/focus, availability and fallback browser checks in frontend/tests/e2e/tour-detail.spec.ts
- [X] T051 [P] [US2] Add failing backend tests for difficulty, ordered/cover gallery media, public operator privacy/aggregates and published/bookable related-tour ranking in backend/tests/Feature/Search/TourDetailTest.php and backend/tests/Feature/Partner/TourCreateTest.php
- [X] T052 [US2] Persist difficulty and ordered cover/gallery media through validated partner writes; return cover-first public images with legacy cover fallback in backend/app/Domains/Partner/Services/TourService.php and backend/app/Domains/Search/Actions/GetTourDetailAction.php
- [X] T053 [US2] Implement public-safe operator summary and related-tour actions with eager loading; add optional fields to the existing detail contract and matching frontend types in backend/app/Domains/Search/Actions and frontend/src/lib/api/types.ts
- [X] T054 [P] [US2] Add focused gallery lightbox, optional-section navigation, sticky offset and mobile booking-action keyboard tests under frontend/src/components/tour/__tests__
- [X] T055 [US2] Decompose tour detail into reusable sections, responsive gallery/lightbox, visible-section navigation, operator/related content and mobile booking action in frontend/src/components/tour without fabricating unsupported facts
- [X] T056 [US2] Extend SEO/structured-data tests and rendering for ordered itinerary, complete image URLs, meeting place, genuine review aggregate and actual content locale in frontend/src/components/seo/__tests__/StructuredData.test.tsx, frontend/src/components/seo/StructuredData.tsx and frontend/src/app/[locale]/(public)/tours/[slug]/page.tsx

## Phase 5: User Story 3 — Complete a trustworthy booking (P1)

**Independent test**: Checkout handles valid selection, price drift, failure/retry and confirmed outcome at mobile/desktop without duplicate charges.

- [X] T019 [P] [US3] Add checkout/price-drift browser regression in frontend/tests/e2e/checkout.spec.ts and frontend/tests/e2e/payment.spec.ts
- [X] T020 [US3] Redesign summary and recovery while retaining backend totals and Stripe Elements in frontend/src/components/booking/BookingForm.tsx
- [X] T021 [US3] Verify confirmation and cancellation states in frontend/tests/e2e/booking.spec.ts

## Phase 6: User Story 4 — Manage identity and travel activity (P2)

**Independent test**: Existing auth/account/profile/wishlist/bookings/reviews guards and redirects work at narrow and wide widths.

- [X] T022 [P] [US4] Add auth/account guard browser regression in frontend/tests/e2e/auth-guards.spec.ts and frontend/tests/e2e/traveler-dashboard.spec.ts
- [X] T023 [US4] Apply shared shell/forms to traveler profile and bookings in frontend/src/app/[locale]/(traveler)/profile/page.tsx and frontend/src/app/[locale]/(traveler)/my-bookings/page.tsx
- [X] T024 [US4] Verify wishlist and eligible-review states in frontend/tests/e2e/wishlist.spec.ts and frontend/tests/e2e/review-submission.spec.ts
- [X] T057 [US4] Apply shared form/feedback primitives to login, registration and recovery routes while preserving validation, guards and redirects in frontend/src/app/[locale]/(auth)

## Phase 7: User Story 5 — Author accurate English source and derive ES/IT (P2; requested slice)

**Independent test**: Partner authors EN only; EN-complete tour publishes before translations; fake Gemini produces ready ES/IT; EN edit stales them; late/failed jobs cannot publish outdated content; guide codes never affect translation selection.

- [X] T025 [P] [US5] Add failing backend tests for EN-only publishing, status transitions, revisions, late results, retries and ownership in backend/tests/Feature/Partner/TourTranslationTest.php and backend/tests/Feature/Admin/TourModerationTest.php
- [X] T026 [P] [US5] Add browser tests for EN-only authoring, pending fallback and ready transition in frontend/tests/e2e/partner/tour-edit.spec.ts and frontend/tests/e2e/tour-detail.spec.ts
- [X] T027 [US5] Add `tour_translation_states` with unique (`tour_id`, `locale`) for es/it, SHA-256 source/translated hashes, pending/ready/stale/failed status, sanitized error code, timestamps and optional `tour_translations.important_information` in backend/database/migrations/2026_09_25_000002_create_tour_translation_states_table.php
- [X] T028 [US5] Add optional `important_information` localized field and state model/relations in backend/app/Models/TourTranslation.php and backend/app/Models/TourTranslationState.php
- [X] T029 [US5] Implement canonical EN payload hashing and fixed traveler-facing field allowlist in backend/app/Domains/Partner/Services/TourTranslationService.php
- [X] T030 [US5] Implement backend-only Gemini `generateContent` adapter with structured JSON, local shape validation, bounded transient retry and no secret/error-body logging in backend/app/Domains/Partner/Services/GeminiTourTranslator.php
- [X] T031 [US5] Add revision-safe after-commit ES/IT queue job; reject outdated result under lock in backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php
- [X] T032 [US5] Trigger stale/pending and queue only on EN content change in backend/app/Domains/Partner/Services/TourService.php
- [X] T033 [US5] Accept EN-only partner writes and reject manually supplied ES/IT while preserving read fields and independent guide codes in backend/app/Domains/Partner/Controllers/TourController.php
- [X] T034 [US5] Require EN title/description at admin approval as well as partner submit; do not gate ES/IT in backend/app/Domains/Admin/Actions/ApproveTourAction.php
- [X] T035 [US5] Add bounded legacy backfill/requeue command without deleting ES/IT rows in backend/app/Console/Commands/QueueTourTranslations.php
- [X] T036 [US5] Show partner translation statuses without provider details and EN-only editor in frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx and frontend/src/components/partner/tours/TourWizard.tsx
- [X] T037 [US5] Add localized status/fallback copy to frontend/messages/en.json, frontend/messages/es.json and frontend/messages/it.json
- [X] T038 [US5] Run focused backend/frontend/browser tests and capture outcomes in specs/018-bookly-ui-redesign/quickstart.md
- [X] T058 [P] [US5] Add failing browser coverage for ordered itinerary editing, image reordering/cover selection and saved preview in frontend/tests/e2e/partner/tour-edit.spec.ts
- [X] T059 [US5] Implement partner itinerary day/stop and media order/cover editing with draft round-trip and preview in frontend/src/components/partner/tours/TourWizard.tsx and matching validated backend partner content inputs

## Phase 8: User Story 6 — Operate a partner business (P2)

**Independent test**: Partner overview/tours/pricing/availability/bookings/reviews/analytics work on mobile; revenue scale and unavailable conversion are truthful.

- [X] T039 [P] [US6] Add narrow-screen partner navigation/analytics regressions in frontend/tests/e2e/partner/navigation.spec.ts and frontend/tests/e2e/partner/analytics.spec.ts
- [X] T040 [US6] Apply shared partner layout and accurate analytics formatting in frontend/src/app/[locale]/(partner)/partner/page.tsx and frontend/src/app/[locale]/(partner)/partner/analytics/page.tsx

## Phase 9: User Story 7 — Moderate submitted content (P2)

**Independent test**: Admin can inspect itinerary/gallery and approve/reject; unauthorized actor cannot.

- [X] T041 [P] [US7] Add moderation/authorization regressions in frontend/tests/e2e/filament-admin.spec.ts and backend/tests/Feature/Admin/ApproveTourTest.php
- [X] T042 [US7] Render EN source plus derived state safely within the existing Filament tour moderation resource in backend/app/Filament/Resources/TourResource.php
- [X] T060 [US7] Apply shared Bookly admin theme in backend/app/Providers/Filament/AdminPanelProvider.php and affected backend/app/Filament/Resources without changing authorization or moving admin into Next.js; verify moderation actions remain auditable

## Phase 10: User Story 8 — Reach supporting information and recover (P3)

**Independent test**: Blog/legal/voucher/error routes keep visibility/security and clear recovery at supported widths.

- [X] T043 [P] [US8] Add supporting-page browser regressions in frontend/tests/e2e/blog-detail.spec.ts and frontend/tests/e2e/voucher-verification.spec.ts
- [X] T044 [US8] Apply shared shell and truthful error/recovery content in frontend/src/app/[locale]/(public)/blog/page.tsx and frontend/src/app/[locale]/(public)/terms/page.tsx
- [X] T061 [US8] Complete privacy, root `/v/[reference]` voucher-verification, blog preview and locale not-found visual treatment in frontend/src/app/[locale]/(public)/privacy/page.tsx, frontend/src/app/v/[reference]/page.tsx, frontend/src/app/[locale]/(public)/blog/[slug]/preview/page.tsx and frontend/src/app/[locale]/not-found.tsx without changing visibility or access rules

## Phase 11: Polish and cross-cutting release gates

- [X] T045 [P] Run backend Pest, Pint and Larastan gates; record failures/evidence in specs/018-bookly-ui-redesign/quickstart.md
- [ ] T046 [P] Run frontend Jest, typecheck, lint, Playwright, Axe and build gates; record failures/evidence in specs/018-bookly-ui-redesign/quickstart.md
- [ ] T047 Run production-build Lighthouse audits at documented conditions and four-width manual keyboard/focus checks in specs/018-bookly-ui-redesign/quickstart.md
- [X] T048 Document additive deploy, translation queue monitoring, rotated-key setup and rollback rehearsal in specs/018-bookly-ui-redesign/quickstart.md

## Dependencies and Execution Order

Phase 1 → Phase 2 → independent story slices. US5 translation state must precede final US1 search projection and US2 localized detail acceptance; US2 booking CTA precedes US3 checkout acceptance. US4, US6, US7 and US8 can proceed after foundation in independently testable branches. Phase 11 follows delivered slices. Within each story: failing tests → data/model → service/job → API → UI → browser acceptance. T049–T061 were appended during the Phase 0 master-plan coverage audit so existing task IDs and completed references remain stable; their section placement, not numeric ID, determines execution order. T051 must precede T052–T053; T054 precedes T055; T058 precedes T059.

**Current implementation state**: All story and foundation tasks are checked, including the EN-source/Gemini translation slice and browser fallback/ready transition. T045 is verified: 725 backend tests / 2,689 assertions passed again after the independent partner fixture, full Pint passed, and full Larastan passed. Latest frontend Jest passed 43 suites / 233 tests, with typecheck, lint and the refreshed production build. The latest complete browser run passed 742 cases, failed three and deliberately skipped 40 duplicates, with no unrun/flaky cases. Two mobile filter readiness failures and one outdated drawer label assertion received test-only fixes; all 73 search/partner-Axe focused cases passed. Subsequent controlled Lighthouse audits found review-date contrast hidden by an early Axe scan and a lazy-loaded home LCP cover. Failing regressions preceded the narrow source fixes; the strengthened loaded-review Axe cases passed EN/ES/IT, and all 16 homepage browser cases passed. Two planned current-build idle Lighthouse rounds meet Performance >=90 on all four primary pages and Accessibility 100, retaining all eight results and earlier failures. A fresh full 787-case browser/Axe run is in progress. The previous 40 visual matrix cases and 88 screenshots passed; final-build matrix and remaining manual keyboard/focus/state acceptance still require verification. T046–T047 remain open; checked implementation tasks do not establish release readiness while a required gate is unverified.

**Parallel examples**: T025 backend tests and T026 browser tests use different files; T013 backend detail tests and T014 frontend component tests use different files; T045 backend gate and T046 frontend gate use separate toolchains.

**MVP strategy**: The full visual-redesign MVP is US1 discovery after foundation. Finish the remaining US5 browser and public read-path acceptance, then continue the visual P1 phases without claiming the entire redesign is done.

## Phase 0 scope traceability

The master plan's Phase 0 deliverables are `spec.md`, `plan.md`, `research.md`, `data-model.md`, both contracts, `quickstart.md`, `tasks.md`, base validation and design acceptance matrices. These artifacts are present. The following mappings make previously implicit master-plan obligations explicit; unchecked entries remain future implementation work, not Phase 0 completion claims.

| Master-plan work | Spec requirements | Implementation tasks |
|---|---|---|
| Shared brand, font, primitives and shells | FR-001–004, FR-024, FR-027 | T005–T007, T049 |
| Home, cards, search, categories and destinations | FR-005–007, FR-026 | T008–T012, T050 |
| Structured itinerary, translation, guide codes and publication | FR-015–017, FR-029–033 | T013–T016, T025–T038, T058–T059 |
| Media/difficulty, operator privacy, related tours, gallery and tour navigation | FR-008–012, FR-018–019, FR-028 | T017–T018, T051–T056 |
| Booking, payment and traveler/auth journeys | FR-013–014, FR-025 | T019–T024, T057 |
| Partner operations and metrics | FR-020–021 | T039–T040, T058–T059 |
| Admin moderation and design | FR-022, FR-025 | T041–T042, T060 |
| Blog, legal, voucher, preview and recovery | FR-023, FR-027 | T043–T044, T061 |
| Release-wide accessibility, localization, performance and rollback | FR-001–004, FR-024–027 | T045–T048 |
