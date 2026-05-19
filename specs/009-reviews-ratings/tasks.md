# Tasks: Reviews & Ratings

**Input**: Design documents from `/specs/009-reviews-ratings/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Backend Pest feature tests are included for each user story. Frontend Jest tests for review components.

**Organization**: Tasks grouped by user story for independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `backend/app/Domains/Reviews/`, `backend/database/migrations/`, `backend/routes/api/`, `backend/tests/Feature/Reviews/`
- **Frontend**: `frontend/src/components/reviews/`, `frontend/src/lib/reviews/`, `frontend/messages/`

---

## Phase 1: Setup (Migrations & Infrastructure)

**Purpose**: Database schema and shared configuration

- [X] T001 `backend/database/migrations/2026_05_13_100001_create_reviews_table.php`: Create migration for `reviews` table per data-model.md (id, booking_id UNIQUE FK, tour_id FK INDEX, traveler_id FK INDEX, rating TINYINT CHECK 1-5, comment TEXT nullable, status VARCHAR(20) DEFAULT 'visible', locale VARCHAR(5) DEFAULT 'en', edited_at TIMESTAMP nullable, created_at, updated_at).
- [X] T002 `backend/database/migrations/2026_05_13_100002_create_review_audit_trails_table.php`: Create migration for `review_audit_trails` table per data-model.md (id, review_id FK INDEX, actor_type, actor_id, action, old_rating nullable, new_rating nullable, old_comment nullable, new_comment nullable, reason nullable, created_at; NO updated_at).
- [X] T003 `backend/database/migrations/2026_05_13_100003_add_rating_columns_to_tours_table.php`: Create migration adding `average_rating` (DECIMAL(3,2) nullable) and `review_count` (INT DEFAULT 0) to the existing `tours` table.

---

## Phase 2: Foundational (Models, Events, Core Services)

**Purpose**: Core domain layer that ALL user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 [P] `backend/app/Domains/Reviews/Models/Review.php`: Create Eloquent model with `$fillable`, `$casts` (rating→int), relationships: `booking()` belongsTo, `tour()` belongsTo, `traveler()` belongsTo, `auditTrails()` hasMany. Add `booted()` with `static::deleting()` returning false (no deletion).
- [X] T005 [P] `backend/app/Domains/Reviews/Models/ReviewAuditTrail.php`: Create Eloquent model with `$fillable`, `const UPDATED_AT = null`. Relationship: `review()` belongsTo. Immutable — no update/delete.
- [X] T006 [P] `backend/app/Domains/Reviews/Events/ReviewSubmitted.php`: Create event with public `Review $review` property.
- [X] T007 [P] `backend/app/Domains/Reviews/Events/ReviewFlagged.php`: Create event with public `Review $review` and `array $matchedKeywords` properties.
- [X] T008 `backend/app/Domains/Reviews/Services/ProfanityFilterService.php`: Create service with a `scan(string $text): array` method. Load keyword list from `storage/app/profanity_keywords.json`. Match word boundaries, case-insensitive. Return array of matched keywords (empty array if clean). Support en/es/it keywords.
- [X] T009 `backend/app/Domains/Reviews/Services/ReviewValidationService.php`: Create service with `validate(Booking $booking, User $traveler): void`. Throws exceptions for: booking not completed, booking not owned by traveler, outside 30-day window, review already exists for booking, no payment record.
- [X] T010 `backend/app/Domains/Reviews/Listeners/UpdateTourAggregateRating.php`: Create listener for `ReviewSubmitted` and `ReviewFlagged`. Recalculate `average_rating` and `review_count` for the related tour (only count visible/flagged reviews). Update the `tours` table directly.
- [X] T011 `backend/app/Domains/Booking/Jobs/CompleteBookingJob.php`: Create scheduled job that queries bookings where `status = 'confirmed'` AND `tour_date < now()`, updating them to `status = 'completed'`. Register in `backend/routes/console.php` to run hourly.
- [X] T012 `backend/app/Http/Resources/ReviewResource.php` [P]: Create API resource transforming Review model to JSON per contracts/api.md (id, reviewer_name, rating, comment, status, edited flag, created_at, updated_at). `reviewer_name` returns first name only; "Anonymous Traveler" if traveler account deleted.

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 — Submit a Review After Tour Completion (Priority: P1) 🎯 MVP

**Goal**: Travelers can submit a star rating (1-5) and optional comment for a completed booking within 30 days of the tour date. One review per booking. Edits allowed within 48 hours. Profanity filter flags suspicious content.

**Independent Test**: Create a completed booking via seeding, POST to `/api/reviews` with rating + comment, verify 201 response, verify review appears in database, verify second submission for same booking returns 403.

### Tests for User Story 1 ⚠️

- [X] T013 [P] [US1] `backend/tests/Feature/Reviews/SubmitReviewTest.php`: Test (1) valid review submission returns 201 with correct data, (2) review with rating-only (no comment) succeeds, (3) 403 for incomplete booking, (4) 403 for booking not owned by traveler, (5) 403 for duplicate review submission, (6) 403 for booking outside 30-day window, (7) 403 for booking without payment record, (8) 422 for rating outside 1-5, (9) 422 for comment exceeding 2000 chars, (10) 429 when rate limit exceeded.
- [X] T014 [P] [US1] `backend/tests/Feature/Reviews/EditReviewTest.php`: Test (1) edit within 48 hours returns 200, (2) edited review shows `edited: true`, (3) edit creates audit trail entry, (4) 403 for edit after 48 hours, (5) 403 for edit by non-owner, (6) edit recalculates aggregate rating.
- [X] T015 [P] [US1] `backend/tests/Feature/Reviews/ProfanityFilterTest.php`: Test (1) review with profanity keyword is flagged, (2) clean review is not flagged, (3) flagged review still visible publicly, (4) case-insensitive matching, (5) word boundary matching (e.g., "class" not flagged as "ass").

### Implementation for User Story 1

- [X] T016 [US1] `backend/app/Domains/Reviews/Actions/SubmitReviewAction.php`: Implement action. Accept booking_reference, rating, comment, locale. Call ReviewValidationService to verify permissions. Call ProfanityFilterService::scan() on comment. Create Review record (status='flagged' if profanity matched, else 'visible'). Create ReviewAuditTrail entry (action='submit', actor_type='traveler'). Dispatch ReviewSubmitted event. If flagged, also dispatch ReviewFlagged event. Return Review.
- [X] T017 [US1] `backend/app/Domains/Reviews/Actions/EditReviewAction.php`: Implement action. Accept review ID, rating, comment. Verify 48-hour window (edited_at IS NULL OR edited_at > now() - 48h). Verify ownership. Record old rating/comment in ReviewAuditTrail. Update review. Set edited_at. Create audit trail entry (action='edit'). Dispatch ReviewSubmitted event (for aggregate recalc). Return updated Review.
- [X] T018 [US1] `backend/app/Domains/Reviews/Controllers/Public/ReviewController.php`: Create controller. `store()` → SubmitReviewAction, rate-limited via `RateLimiter::attempt('review-submit:{id}', 10, 60)`. `update()` → EditReviewAction. Return ReviewResource JSON. Handle exceptions with proper status codes.
- [X] T019 [US1] `backend/routes/api/public.php`: Add `POST /api/reviews` → ReviewController@store, `PUT /api/reviews/{review}` → ReviewController@update (both behind Sanctum auth middleware).
- [X] T020 [US1] `backend/app/Providers/EventServiceProvider.php`: Register `ReviewSubmitted` → `[UpdateTourAggregateRating::class]` and `ReviewFlagged` → `[UpdateTourAggregateRating::class]` in `$listen`.
- [X] T021 [P] [US1] `frontend/src/components/reviews/StarRating.tsx`: Create interactive star rating component (5 clickable stars, controlled value 1-5). Aria labels for accessibility. Click/tap to select.
- [X] T022 [P] [US1] `frontend/src/components/reviews/ReviewForm.tsx`: Create form component using React Hook Form + Zod. Rating selector (StarRating), comment textarea (max 2000 chars with character counter), locale hidden field, submit button. Submit handler calls review API. Error states: validation errors, rate limit (429). Success: show confirmation. Edit mode: pre-fill existing review data, show "Edited" indicator.
- [X] T023 [US1] `frontend/src/lib/reviews/review-api.ts`: Create API client module with `submitReview()`, `editReview()` functions calling the new endpoints.
- [X] T024 [P] [US1] `frontend/src/components/reviews/__tests__/StarRating.test.tsx`: Jest test for StarRating. Verify stars render, click sets correct value, aria labels present.
- [X] T025 [P] [US1] `frontend/src/components/reviews/__tests__/ReviewForm.test.tsx`: Jest test for ReviewForm. Verify form renders, validation error for comment >2000 chars, submit button disabled during processing, success callback, edit mode pre-fills data.

**Checkpoint**: User Story 1 complete — travelers can submit and edit reviews via API + frontend

---

## Phase 4: User Story 2 — View Reviews on Tour Detail Page (Priority: P1) 🎯 MVP

**Goal**: Tour detail page shows aggregate rating (average + count) and paginated review list with reviewer name, stars, comment, and date. Handles zero reviews gracefully. Paginated with 5 per page.

**Independent Test**: Seed reviews for a tour, visit tour detail page, verify aggregate rating displays correctly, verify review list renders, verify pagination works, verify empty state for tours with no reviews.

### Tests for User Story 2 ⚠️

- [X] T026 [P] [US2] `backend/tests/Feature/Reviews/ViewReviewsTest.php`: Test (1) GET /api/tours/{slug}/reviews returns paginated visible reviews, (2) hidden reviews not included in listing, (3) flagged reviews ARE included (they are publicly visible), (4) empty tour returns empty list with zero count, (5) aggregate rating calculation is correct, (6) reviewer name is first name only, (7) "Anonymous Traveler" when traveler deleted, (8) pagination respects per_page parameter.

### Implementation for User Story 2

- [X] T027 [US2] `backend/app/Domains/Reviews/Controllers/Public/ReviewController.php`: Add `index(Tour $tour)` method to existing controller. Query visible/flagged reviews for tour, ordered by `created_at DESC`, paginated (default 5/page). Return ReviewResource collection. Include `meta.average_rating` and `meta.review_count` from tour model.
- [X] T028 [US2] `backend/routes/api/public.php`: Add `GET /api/tours/{tour:slug}/reviews` → ReviewController@index.
- [X] T029 [P] [US2] `frontend/src/components/reviews/AggregateRating.tsx`: Create component displaying average rating (e.g., "4.2"), visual star bar, and review count (e.g., "10 reviews"). Handle null/no-reviews with "No reviews yet. Be the first!" message.
- [X] T030 [P] [US2] `frontend/src/components/reviews/ReviewCard.tsx`: Create single review card component. Displays: reviewer name, StarRating (read-only), comment text (if present), submission date formatted via Intl.DateTimeFormat. Shows "Edited" badge if edited. Skeleton loading state.
- [X] T031 [US2] `frontend/src/components/reviews/ReviewList.tsx`: Create component composing AggregateRating + list of ReviewCards. Fetches reviews from API with pagination. "Load More" button at end for next page. Loading, empty, and error states.
- [X] T032 [P] [US2] `frontend/src/lib/reviews/review-api.ts`: Add `fetchTourReviews(tourSlug, page)` function to existing API client module.
- [X] T033 [P] [US2] `frontend/src/components/reviews/__tests__/AggregateRating.test.tsx`: Jest test. Verify average displays, star bar renders, no-reviews message.
- [X] T034 [P] [US2] `frontend/src/components/reviews/__tests__/ReviewList.test.tsx`: Jest test. Mock API, verify render, pagination, empty state, loading state.

**Checkpoint**: User Story 2 complete — reviews visible on tour detail page. Combined with US1, the MVP review system is functional.

---

## Phase 5: User Story 3 — Partner Views Their Tour Reviews (Priority: P2)

**Goal**: Partners can see all reviews for their tours in a consolidated dashboard with per-tour aggregate ratings and individual review listings.

**Independent Test**: Login as partner, GET /api/partner/reviews, verify only reviews for partner's tours appear, verify aggregate values are correct.

### Tests for User Story 3 ⚠️

- [X] T035 [P] [US3] `backend/tests/Feature/Reviews/PartnerReviewsTest.php`: Test (1) partner sees only their tours' reviews, (2) aggregate values per tour correct, (3) filter by tour_id works, (4) 401 for unauthenticated, (5) 403 for non-partner role.

### Implementation for User Story 3

- [X] T036 [US3] `backend/app/Domains/Reviews/Controllers/Partner/PartnerReviewController.php`: Create controller. `index()`: query reviews where `tour_id IN (SELECT id FROM tours WHERE partner_id = auth()->id())`. Support `tour_id` filter. Return paginated ReviewResource collection with `meta.tour_summaries` (per-tour averages). Sorted by `created_at DESC`.
- [X] T037 [US3] `backend/routes/api/partner.php`: Add `GET /api/partner/reviews` → PartnerReviewController@index (partner auth middleware).

**Checkpoint**: User Story 3 complete — partners can monitor reviews for their tours

---

## Phase 6: User Story 4 — Admin Moderates Reviews (Priority: P3)

**Goal**: Admins view all platform reviews, filter by status/tour/date, hide inappropriate reviews, and reinstate hidden ones. Moderation actions logged in audit trail.

**Independent Test**: Login as admin, GET /api/admin/reviews, hide a review, verify it disappears from public listing, reinstate, verify it reappears. Check audit trail records the actions.

### Tests for User Story 4 ⚠️

- [X] T038 [P] [US4] `backend/tests/Feature/Reviews/AdminModerationTest.php`: Test (1) admin sees all reviews across all tours, (2) filtering by status (hidden/flagged/visible) works, (3) filtering by tour works, (4) hide action sets status=hidden and removes from public listing, (5) reinstate action sets status=visible and restores to public listing, (6) audit trail records both actions with actor, timestamp, and reason, (7) 403 for non-admin role, (8) hide/reinstate recalculates aggregate rating.

### Implementation for User Story 4

- [X] T039 [US4] `backend/app/Domains/Reviews/Actions/HideReviewAction.php`: Create action. Accept review ID, admin ID, reason string. Update review status to 'hidden'. Create ReviewAuditTrail (action='hide', actor_type='admin', reason). Dispatch event for aggregate recalculation. Return updated review.
- [X] T040 [US4] `backend/app/Domains/Reviews/Actions/ReinstateReviewAction.php`: Create action. Accept review ID, admin ID, reason. Update review status to 'visible'. Create ReviewAuditTrail (action='reinstate', actor_type='admin', reason). Dispatch event for aggregate recalculation. Return updated review.
- [X] T041 [US4] `backend/app/Domains/Reviews/Controllers/Admin/AdminReviewController.php`: Create controller. `index()`: list all reviews with filters (status, tour_id, date_from, date_to, flagged, page). Include audit trail summary. `hide()` → HideReviewAction. `reinstate()` → ReinstateReviewAction. Return ReviewResource JSON.
- [X] T042 [US4] `backend/routes/api/admin.php`: Add `GET /api/admin/reviews` → AdminReviewController@index, `POST /api/admin/reviews/{review}/hide` → AdminReviewController@hide, `POST /api/admin/reviews/{review}/reinstate` → AdminReviewController@reinstate (all behind admin auth middleware).

**Checkpoint**: Admin moderation complete — platform content quality is governable

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: i18n, integration, final validation

- [X] T043 [P] `frontend/messages/en.json`: Add English keys for review UI (star_rating_label, submit_review, edit_review, no_reviews_yet, edited, char_count, rate_limit_message, review_window_closed, edit_window_closed, flagged).
- [X] T044 [P] `frontend/messages/es.json`: Add Spanish translations for review keys.
- [X] T045 [P] `frontend/messages/it.json`: Add Italian translations for review keys.
- [X] T046 `frontend/src/components/tour/TourDetail.tsx`: Integrate `<ReviewList>` component into the tour detail page (spec 006 component) below the tour description section.
- [X] T047 `frontend/src/components/booking/BookingDetail.tsx`: Integrate `<ReviewForm>` component into the booking detail page for completed bookings within the 30-day window. Show existing review if already submitted.
- [X] T048 [P] Run `vendor/bin/pest --filter=Reviews` in backend container and verify all tests pass.
- [X] T049 [P] Run `npx jest --testPathPattern="reviews"` in frontend and verify all tests pass.
- [X] T050 Validate quickstart.md steps end-to-end: run migrations, seed test data, submit review, view on tour detail, hide as admin.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (migrations needed for models) — BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Phase 2 — No dependencies on other stories
- **US2 (Phase 4)**: Depends on Phase 2 — No dependencies on US1 (can read reviews seeded independently)
- **US3 (Phase 5)**: Depends on Phase 2 — No dependencies on US1/US2
- **US4 (Phase 6)**: Depends on Phase 2 — No dependencies on US1/US2/US3
- **Polish (Phase 7)**: Depends on all desired user stories

### Within Each User Story

- Write tests FIRST, verify they FAIL
- Actions before controllers
- Controllers before routes
- Frontend API client before components
- Shared components [P] can run in parallel

### Parallel Opportunities

- All Setup tasks [P] can run in parallel
- All Foundational model/event tasks (T004-T007) can run in parallel
- T010-T012 can run in parallel after T004-T009
- Once Phase 2 completes, US1, US2, US3, US4 can all start in parallel
- Within each story, all test tasks [P] can run in parallel
- Frontend components marked [P] can run in parallel with backend implementation
- i18n tasks (T043-T045) can run in parallel with any phase

---

## Parallel Example: Phase 2 (Foundational)

```bash
# Launch all model + event tasks together:
Task: "Create Review model in backend/app/Domains/Reviews/Models/Review.php"
Task: "Create ReviewAuditTrail model in backend/app/Domains/Reviews/Models/ReviewAuditTrail.php"
Task: "Create ReviewSubmitted event in backend/app/Domains/Reviews/Events/ReviewSubmitted.php"
Task: "Create ReviewFlagged event in backend/app/Domains/Reviews/Events/ReviewFlagged.php"
Task: "Create ReviewResource in backend/app/Http/Resources/ReviewResource.php"

# After models/events: services in parallel:
Task: "Create ProfanityFilterService in backend/app/Domains/Reviews/Services/ProfanityFilterService.php"
Task: "Create ReviewValidationService in backend/app/Domains/Reviews/Services/ReviewValidationService.php"
Task: "Create UpdateTourAggregateRating listener"
Task: "Create CompleteBookingJob"
```

## Parallel Example: US1 + US2 (Both P1 MVP)

```bash
# Once Foundational phase completes, launch both MVP stories in parallel:
# Developer A: US1 (Submit + Edit Review)
# Developer B: US2 (View Reviews on Tour Detail)
```

---

## Implementation Strategy

### MVP First (US1 + US2: Both P1)

1. Complete Phase 1: Setup (3 tasks)
2. Complete Phase 2: Foundational (9 tasks — CRITICAL)
3. Complete Phase 3: US1 — Submit/Edit Reviews (13 tasks)
4. Complete Phase 4: US2 — View Reviews (9 tasks)
5. **STOP and VALIDATE**: Full review loop works end-to-end
   - Traveler can submit review → review appears on tour detail page
6. Complete Phase 7: Polish (8 tasks)
7. **Deploy/Demo MVP**: Core review system functional

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 + US2 → Test independently → **Deploy MVP** (submission + display)
3. US3 → Partners see reviews → Deploy
4. US4 → Admin moderation → Deploy
5. Polish → i18n complete, integration validated → **Full release**

### Parallel Team Strategy

With multiple developers:
1. Team completes Setup + Foundational together
2. Once Foundational done:
   - Developer A: User Story 1 (Submit/Edit)
   - Developer B: User Story 2 (View/Display)
3. Stories complete independently, then:
   - Developer A: User Story 3 (Partner)
   - Developer B: User Story 4 (Admin)
4. Polish phase: pair on integration and i18n

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story
- Each user story independently completable and testable
- Backend tests: Pest with RefreshDatabase
- Frontend tests: Jest with @testing-library/react
- Write tests first, verify they fail, then implement
- Commit after each task or logical group
- Prohibited: review deletion at all levels (model, controller, DB)
- The `completed` booking status transition depends on T011 (CompleteBookingJob)
