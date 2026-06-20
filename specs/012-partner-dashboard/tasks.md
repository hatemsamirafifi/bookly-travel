# Tasks: Partner Dashboard — Tour Creation, Booking Management & Partner Profile

**Input**: Design documents from `/specs/012-partner-dashboard/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Backend — Partner Domain Setup

**Purpose**: Core backend infrastructure for partner operations.

- [x] T001 [P] Create `PartnerRoleMiddleware` in `backend/app/Domains/Partner/Middleware/PartnerRoleMiddleware.php` — sets `partner_id` on request, returns 404 for cross-partner access.
- [x] T002 [P] Register partner route group in `backend/routes/api/partner.php` with `middleware(['auth:sanctum', 'partner'])` prefix.
- [x] T003 [P] Create `Partner` model in `backend/app/Domains/Partner/Models/Partner.php`.
- [x] T004 [P] Create `PartnerProfile` model with encrypted payout accessors in `backend/app/Domains/Partner/Models/PartnerProfile.php`.
- [x] T005 [P] Create `PartnerSettings` model in `backend/app/Domains/Partner/Models/PartnerSettings.php`.
- [x] T006 [P] Create `Notification` model in `backend/app/Domains/Partner/Models/Notification.php`.
- [x] T007 [P] Create `TourDraft` model in `backend/app/Domains/Partner/Models/TourDraft.php`.
- [x] T008 [P] Create `TourMedia` model in `backend/app/Domains/Partner/Models/TourMedia.php`.
- [x] T009 [P] Create `PricingTier` model in `backend/app/Domains/Partner/Models/PricingTier.php`.
- [x] T010 [P] Create `AvailabilityRule` model in `backend/app/Domains/Partner/Models/AvailabilityRule.php`.
- [x] T011 [P] Create `AvailabilityException` model in `backend/app/Domains/Partner/Models/AvailabilityException.php`.
- [x] T012 [P] Create `ReviewResponse` model in `backend/app/Domains/Partner/Models/ReviewResponse.php`.
- [x] T013 [P] Create `TourPolicy` policy in `backend/app/Domains/Partner/Policies/TourPolicy.php`.

---

## Phase 2: Backend — Database Migrations

**Purpose**: Create all partner-related tables and columns.

- [x] T014 [P] Migration: `2026_06_02_100001_create_partners_table.php`
- [x] T015 [P] Migration: `2026_06_02_100002_create_notifications_table.php`
- [x] T016 [P] Migration: `2026_06_02_100003_create_tour_drafts_table.php`
- [x] T017 [P] Migration: `2026_06_02_100004_create_tour_media_table.php`
- [x] T018 [P] Migration: `2026_06_02_100005_create_pricing_tiers_table.php`
- [x] T019 [P] Migration: `2026_06_02_100006_create_availability_rules_table.php`
- [x] T020 [P] Migration: `2026_06_02_100007_create_availability_exceptions_table.php`
- [x] T021 [P] Migration: `2026_06_02_100008_add_partner_columns_to_tours_table.php`
- [x] T022 [P] Migration: `2026_06_02_100009_create_review_responses_table.php`
- [x] T023 [P] Migration: `2026_06_02_100010_create_partner_profiles_table.php`
- [x] T024 [P] Migration: `2026_06_02_100011_create_partner_settings_table.php`

---

## Phase 3: Backend — Services

**Purpose**: Business logic layer — all controllers delegate to services.

- [x] T025 [P] Create `TourService` in `backend/app/Domains/Partner/Services/TourService.php` — listForPartner, getForPartner, createTour, updateTour, archiveTour, saveDraft, getLatestDraft.
- [x] T026 [P] Create `BookingService` in `backend/app/Domains/Partner/Services/BookingService.php` — listForPartner, getForPartner, markAsCompleted, requestCancellation.
- [x] T027 [P] Create `ReviewService` in `backend/app/Domains/Partner/Services/ReviewService.php` — listForPartner, getReviewForPartner, createResponse, updateResponse.
- [x] T028 [P] Create `AnalyticsService` in `backend/app/Domains/Partner/Services/AnalyticsService.php` — getSummary, getBookingsOverTime, getAnalytics.
- [x] T029 [P] Create `ProfileService` in `backend/app/Domains/Partner/Services/ProfileService.php` — getProfile, getSettings, updateProfile, updateSettings.
- [x] T030 [P] Create `NotificationService` in `backend/app/Domains/Partner/Services/NotificationService.php` — listForPartner, getUnreadCount, markAsRead, markAllAsRead, create.

---

## Phase 4: Backend — Controllers

**Purpose**: Thin controllers that extract partner_id and delegate to services.

- [x] T031 [P] Create `TourController` in `backend/app/Domains/Partner/Controllers/TourController.php` — index, show, store, update, destroy, saveDraft, latestDraft.
- [x] T032 [P] Create `BookingController` in `backend/app/Domains/Partner/Controllers/BookingController.php` — index, show, updateStatus, requestCancellation.
- [x] T033 [P] Create `ReviewController` in `backend/app/Domains/Partner/Controllers/ReviewController.php` — index, storeResponse, updateResponse.
- [x] T034 [P] Create `AnalyticsController` in `backend/app/Domains/Partner/Controllers/AnalyticsController.php` — index (with tour_id ownership validation).
- [x] T035 [P] Create `ProfileController` in `backend/app/Domains/Partner/Controllers/ProfileController.php` — show, update, settings, updateSettings.
- [x] T036 [P] Create `NotificationController` in `backend/app/Domains/Partner/Controllers/NotificationController.php` — index, markAsRead, markAllAsRead.
- [x] T037 [P] Create `UploadController` in `backend/app/Domains/Partner/Controllers/UploadController.php` — signedUrl for R2 direct upload.

---

## Phase 5: Backend — Form Requests, Rules & Events

**Purpose**: Validation, custom rules, and real-time broadcast events.

- [x] T038 [P] Create `StoreTourRequest` in `backend/app/Domains/Partner/Requests/StoreTourRequest.php`.
- [x] T039 [P] Create `UpdateTourRequest` in `backend/app/Domains/Partner/Requests/UpdateTourRequest.php`.
- [x] T040 [P] Create `UpdateBookingStatusRequest` in `backend/app/Domains/Partner/Requests/UpdateBookingStatusRequest.php`.
- [x] T041 [P] Create `StoreReviewResponseRequest` in `backend/app/Domains/Partner/Requests/StoreReviewResponseRequest.php`.
- [x] T042 [P] Create `UpdateProfileRequest` in `backend/app/Domains/Partner/Requests/UpdateProfileRequest.php`.
- [x] T043 [P] Create `ValidIban` rule with MOD 97 checksum in `backend/app/Domains/Partner/Rules/ValidIban.php`.
- [x] T044 [P] Create 7 broadcast events in `backend/app/Domains/Partner/Events/` — NewBooking, TourApproved, TourRejected, BookingCancelled, ReviewReceived, PaymentStatusChanged, DailySummaryReady (all ShouldBroadcastNow on PrivateChannel `private-partner.{partner_id}`).
- [x] T045 [P] Create `SendDailySummaryJob` in `backend/app/Domains/Partner/Jobs/SendDailySummaryJob.php` — ShouldQueue on 'low' queue.

---

## Phase 6: Backend — Seeder & Documentation

**Purpose**: Development seed data and API documentation.

- [x] T046 [P] Create `PartnerSeeder` in `backend/database/seeders/PartnerSeeder.php` — partner user, Partner record, PartnerProfile, PartnerSettings, 3 tours, pricing tiers, availability, tour media, tour draft, 4 notifications.
- [x] T047 [P] Create `backend/docs/partner-api.md` — complete API documentation with endpoints, request/response formats, WebSocket events, error responses.

---

## Phase 7: Backend — Security Fixes

**Purpose**: Fix cross-partner access vulnerabilities.

- [x] T048 Fix partner route middleware — change `'role:partner'` to `'partner'` in `backend/routes/api/partner.php` to use PartnerRoleMiddleware (sets partner_id, returns 404) instead of RoleMiddleware (returns 403).
- [x] T049 Rewrite `BookingController` to use `BookingService` with proper partner scoping — previously had zero partner ownership checks.
- [x] T050 Rewrite `ReviewController` to use `ReviewService` — previously had no ownership verification.
- [x] T051 Rewrite `AnalyticsController` to use `AnalyticsService` with partner scoping and tour_id ownership validation — previously returned data for all partners.
- [x] T052 Fix `TransitionBookingStatusAction` — change `AccessDeniedHttpException('You do not own this tour.')` to `NotFoundHttpException('Booking not found.')` to prevent information leakage.
- [x] T053 Add encrypted payout accessors to `PartnerProfile` model — `payoutAccountNumber()`, `payoutIban()`, `payoutSwiftBic()` with `getRawPayout*()` methods for processing.

---

## Phase 8: Frontend — Shared Infrastructure

**Purpose**: Core setup and layout for the partner portal.

- [x] T054 [P] Create Partner route group layout in `frontend/src/app/[locale]/(partner)/partner/layout.tsx` incorporating `PartnerHeader` and `PartnerSidebar`.
- [x] T055 [P] Verify `PartnerAuthGuard` properly wraps the layout to block unauthorized access per FR-012.
- [x] T056 [P] Configure partner API client in `frontend/src/lib/api/partner.ts` — authenticated requests with Bearer token.
- [x] T057 [P] Add ~150 partner i18n keys to `frontend/messages/en.json`, `es.json`, `it.json` covering nav, dashboard, tours, bookings, reviews, analytics, profile, notifications.
- [x] T058 [P] Create `usePartnerRealtime` hook in `frontend/src/lib/hooks/usePartnerRealtime.ts` — WebSocket + 60s polling fallback.
- [x] T059 [P] Create partner TypeScript types in `frontend/src/types/partner.ts` and `frontend/src/types/tour.ts`.

---

## Phase 9: Frontend — Layout Components

**Purpose**: Shared partner layout components.

- [x] T060 [P] Create `PartnerSidebar` in `frontend/src/components/partner/layout/PartnerSidebar.tsx`.
- [x] T061 [P] Create `PartnerHeader` in `frontend/src/components/partner/layout/PartnerHeader.tsx`.
- [x] T062 [P] Create `MobileDrawer` in `frontend/src/components/partner/layout/MobileDrawer.tsx` — hamburger menu for mobile.
- [x] T063 [P] Create `NotificationBell` in `frontend/src/components/partner/layout/NotificationBell.tsx` — real-time badge with unread count.

---

## Phase 10: Frontend — User Story 1: Dashboard Overview

**Goal**: Partner visits `/{locale}/partner` and sees summary metrics, bookings chart, and notifications.

- [x] T064 [P] [US1] Create `AnalyticsSummary` component in `frontend/src/components/partner/analytics/AnalyticsSummary.tsx` — total bookings, revenue, rating, etc.
- [x] T065 [P] [US1] Create `BookingsChart` component in `frontend/src/components/partner/analytics/BookingsChart.tsx`.
- [x] T066 [US1] Wire dashboard page in `frontend/src/app/[locale]/(partner)/partner/page.tsx` with data fetching from `/api/partner/analytics`.

---

## Phase 11: Frontend — User Story 2: Tour Creation & Management

**Goal**: Multi-step wizard to create tours, save drafts, and submit for review.

- [x] T067 [P] [US2] Create `TourList` component in `frontend/src/components/partner/tours/TourList.tsx` with status tabs.
- [x] T068 [P] [US2] Create `TourCard` component in `frontend/src/components/partner/tours/TourCard.tsx`.
- [x] T069 [P] [US2] Create `TourWizard` component in `frontend/src/components/partner/tours/TourWizard.tsx` — multi-step wizard shell.
- [x] T070 [P] [US2] Create `ImageUploader` component in `frontend/src/components/partner/tours/ImageUploader.tsx` — drag-and-drop, presigned URL, 5MB max.
- [x] T071 [P] [US2] Create `PricingTierForm` component in `frontend/src/components/partner/tours/PricingTierForm.tsx` — dynamic add/remove tiers.
- [x] T072 [P] [US2] Create `AvailabilityCalendar` component in `frontend/src/components/partner/tours/AvailabilityCalendar.tsx` — recurring rules + exceptions.
- [x] T073 [P] [US2] Create `useTourWizardStore` Zustand store with persist in `frontend/src/lib/stores/tourWizard.ts` — 5 wizard steps, form data, pricing tier/availability actions.
- [x] T074 [P] [US2] Create wizard-specific types in `frontend/src/types/tour.ts` — WizardStep, TourFormData, PricingTierFormInput, AvailabilityRuleFormInput, etc.
- [x] T075 [US2] Wire `/partner/tours` page to display paginated list of tours.
- [x] T076 [US2] Wire `/partner/tours/create/page.tsx` with auto-save draft functionality.
- [x] T077 [US2] Create tour edit page in `/partner/tours/[id]/edit/page.tsx` pre-filling the wizard.
- [x] T078 [US2] Implement "Submit for Review" API call and redirection.

---

## Phase 12: Frontend — User Story 3: Booking Management

**Goal**: View and manage bookings, update statuses, request cancellations.

- [x] T079 [P] [US3] Create `BookingFilters` component in `frontend/src/components/partner/bookings/BookingFilters.tsx` — search, status, date range, tour filter.
- [x] T080 [P] [US3] Create `BookingList` component in `frontend/src/components/partner/bookings/BookingList.tsx`.
- [x] T081 [P] [US3] Create `BookingDetail` component in `frontend/src/components/partner/bookings/BookingDetail.tsx` — status badges, Mark Completed / Request Cancellation actions.
- [x] T082 [US3] Wire `/partner/bookings/page.tsx` combining filters, list, and detail view.

---

## Phase 13: Frontend — User Story 4: Review Management

**Goal**: Read and respond to traveler reviews.

- [x] T083 [P] [US4] Create `ReviewList` component in `frontend/src/components/partner/reviews/ReviewList.tsx`.
- [x] T084 [P] [US4] Create `ReviewResponseForm` component in `frontend/src/components/partner/reviews/ReviewResponseForm.tsx` — 1000 char counter, create/edit modes.
- [x] T085 [US4] Wire `/partner/reviews/page.tsx` with paginated review list.

---

## Phase 14: Frontend — User Story 5: Profile & Settings

**Goal**: Manage company profile, payout info, and notification preferences.

- [x] T086 [P] [US5] Create `ProfileForm` component in `frontend/src/components/partner/profile/ProfileForm.tsx` — company info and logo upload.
- [x] T087 [P] [US5] Create `NotificationSettings` component in `frontend/src/components/partner/profile/NotificationSettings.tsx` — toggles for notifications.
- [x] T088 [P] [US5] Create `PayoutForm` component in `frontend/src/components/partner/profile/PayoutForm.tsx` — IBAN and bank detail validation.
- [x] T089 [US5] Wire `/partner/profile/page.tsx` combining these forms with data fetching.

---

## Phase 15: Frontend — User Story 6: Partner Analytics

**Goal**: Detailed analytics view with date range and tour filtering.

- [x] T090 [US6] Wire analytics page in `frontend/src/app/[locale]/(partner)/partner/analytics/page.tsx` reusing AnalyticsSummary and BookingsChart.
- [x] T091 [US6] Add date range picker and tour selector to refetch analytics data.

---

## Phase 16: Backend Contract Tests

**Purpose**: Pest PHP feature tests verifying all partner API endpoints.

- [x] T092 [P] `TourCreateTest.php` — POST /api/partner/tours with valid/invalid data.
- [x] T093 [P] `TourListTest.php` — GET /api/partner/tours with status filter, pagination.
- [x] T094 [P] `TourDraftTest.php` — POST saveDraft, GET latestDraft.
- [x] T095 [P] `TourArchiveTest.php` — DELETE /api/partner/tours/{id}.
- [x] T096 [P] `BookingListTest.php` — GET /api/partner/bookings with filters.
- [x] T097 [P] `BookingStatusTest.php` — PATCH status, POST cancellation-request.
- [x] T098 [P] `NotificationTest.php` — GET, POST read, POST read-all.
- [x] T099 [P] `AnalyticsTest.php` — GET /api/partner/analytics with filters, tour_id ownership.
- [x] T100 [P] `ReviewListTest.php` — GET /api/partner/reviews with filters.
- [x] T101 [P] `ReviewResponseTest.php` — POST/PUT /api/partner/reviews/{id}/responses.
- [x] T102 [P] `ProfileTest.php` — GET/PUT profile, GET/PUT settings.
- [x] T103 [P] `PayoutValidationTest.php` — IBAN validation with MOD 97 checksum.

---

## Phase 17: Frontend E2E Tests

**Purpose**: Playwright end-to-end tests for partner dashboard flows.

- [x] T104 [P] `tour-create.spec.ts` — multi-step wizard, draft save, submit for review.
- [x] T105 [P] `tour-edit.spec.ts` — load existing tour, edit, save draft.
- [x] T106 [P] `bookings.spec.ts` — list, filter, detail, status update, cancellation request.
- [x] T107 [P] `navigation.spec.ts` — sidebar navigation, mobile drawer.
- [x] T108 [P] `reviews.spec.ts` — list, respond, edit response.
- [x] T109 [P] `analytics.spec.ts` — summary cards, chart, date filter.
- [x] T110 [P] `profile.spec.ts` — company info, payout, notification settings.

---

## Phase 18: Validation & Polish

**Purpose**: Cross-cutting verifications and final quality checks.

- [x] T111 [P] Fix frontend API client mismatches in `frontend/src/lib/api/partner.ts` — review endpoints (`/respond` → `/responses`), cancellation endpoint (`/request-cancellation` → `/cancellation-request`), settings endpoint path, upload endpoint (POST not GET). **Verified**: all endpoints match backend `routes/api/partner.php`.
- [x] T112 [P] Run backend contract test suite (`php artisan test --testsuite=Partner`). **Passed**: All Partner feature tests passed successfully.
- [x] T113 [P] Run frontend E2E test suite (`npx playwright test`). **Passed**: Playwright E2E tests verified.
- [x] T114 [P] Verify WCAG 2.1 AA accessibility (keyboard nav, ARIA, contrast) across partner components. **Verified**: aria-label on sidebar nav, header buttons, pagination; aria-expanded on expandable sections; role="dialog" on MobileDrawer and NotificationBell; role="alert" on error states; role="menubar/menuitem" on sidebar; form labels on all filter inputs.
- [x] T115 [P] Test mobile responsiveness (390px, 780px) ensuring sidebar collapses to MobileDrawer. **Verified**: sidebar `hidden md:flex`, MobileDrawer `fixed inset-0 z-50 md:hidden`, responsive padding `p-4 md:p-6`, responsive grids throughout partner components.
- [x] T116 Add loading states/skeletons for all major data fetches (tours, bookings, analytics). **Verified**: `PartnerTourListSkeleton`, `PartnerBookingListSkeleton`, `PartnerAnalyticsSkeleton`, `LoadingSkeleton` variants for reviews.
- [x] T117 Run Lighthouse audit — verify Performance ≥ 90. **Passed**: Performance > 90 across tested routes.
- [x] T118 Run build verification — `npm run build` succeeds with zero errors. **Passed**: all 31 routes compiled successfully.
- [x] T119 Verify i18n completeness — all 3 locales (EN, ES, IT) have matching keys. **Passed**: EN=733, ES=733, IT=733 leaf keys — all match.
- [x] T120 Validate quickstart guide — partner user can log in and navigate all pages. **Verified**: Complete flow confirmed with seeded partner user.

---

## Completion Summary

| Phase | Tasks | Status |
|-------|--------|--------|
| 1–7: Backend | T001–T053 | ✅ Complete (53/53) |
| 8–15: Frontend | T054–T091 | ✅ Complete (38/38) |
| 16–17: Tests | T092–T110 | ✅ Complete (19/19) |
| 18: Validation | T111–T120 | ✅ Complete (10/10) |
| **Total** | **T001–T120** | **120/120 complete** |