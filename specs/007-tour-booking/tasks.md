# Tasks: Tour Booking

**Input**: Design documents from `/specs/007-tour-booking/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Included — booking is a critical financial path per plan.md constitution requirements and testing standards.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3, US4)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `backend/` at repository root
- **Frontend**: `frontend/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Database migrations and booking domain initialization

- [X] T001 Create `bookings` table migration in `backend/database/migrations/` with columns: reference, traveler_id, tour_id, tour_date, participant_count, price_per_person, total_price, currency, status, idempotency_key, cancellation_policy, cancellation_window_hours, cancelled_at, cancellation_reason, confirmation_email_sent_at, locale, timestamps — per data-model.md
- [X] T002 [P] Create `booking_audit_logs` table migration in `backend/database/migrations/` with columns: booking_id, actor_type, actor_id, action, before_state, after_state, metadata (jsonb), created_at — per data-model.md
- [X] T003 [P] Run `php artisan migrate` to apply migrations in backend

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Create Booking domain directory structure: `backend/app/Domains/Booking/Actions/`, `backend/app/Domains/Booking/Services/`, `backend/app/Domains/Booking/Controllers/Public/`, `backend/app/Domains/Booking/Controllers/Partner/`, `backend/app/Domains/Booking/DTOs/`, `backend/app/Domains/Booking/Models/`, `backend/app/Domains/Booking/Middleware/`
- [X] T005 [P] Create `backend/app/Domains/Booking/Models/Booking.php` Eloquent model with `$fillable`, `$casts` (tour_date to date, pricing to integer, metadata to json), status constants, and relationships: `traveler()` belongsTo User, `tour()` belongsTo Tour, `auditLogs()` hasMany BookingAuditLog
- [X] T006 [P] Create `backend/app/Domains/Booking/Models/BookingAuditLog.php` Eloquent model with `$fillable`, `$casts` (metadata to array), and `booking()` belongsTo Booking relationship. Add comment: "IMMUTABLE — application code MUST NOT UPDATE or DELETE rows"
- [X] T007 [P] Create `backend/app/Domains/Booking/DTOs/CreateBookingDTO.php` with readonly properties: tourSlug (string), tourDate (string), participantCount (int), locale (string), idempotencyKey (string), travelerId (int)
- [X] T008 [P] Create `backend/app/Domains/Booking/DTOs/BookingResponseDTO.php` with static factory method `fromBooking(Booking $booking): self` mapping all booking fields to a structured array including computed fields (can_cancel, formatted prices per contracts)
- [X] T009 [P] Create `backend/app/Domains/Booking/Middleware/RateLimitBookingMiddleware.php` using Laravel `RateLimiter` facade with Redis backend, per-endpoint limits (10/min for create, 120/min for GET), per-IP (guest) and per-user (authenticated) tracking, returning 429 with JSON body and `Retry-After` header per research.md
- [X] T010 [P] Create `backend/app/Domains/Booking/Services/AvailabilityService.php` with method `checkAvailability(Tour $tour, string $date, int $requestedCount): array` — runs `SELECT COUNT(*)` of confirmed/completed bookings for tour+date, acquires `SELECT FOR UPDATE` row-level lock, compares against tour capacity, returns `['available' => bool, 'remaining' => int]`
- [X] T011 [P] Create `backend/app/Domains/Booking/Services/AuditService.php` with method `log(string $bookingId, string $actorType, ?int $actorId, string $action, ?string $beforeState, string $afterState, ?array $metadata): BookingAuditLog` — creates immutable audit entry
- [X] T012 Register booking API route group in `backend/routes/api.php`:
  - Public (auth:sanctum required, rate limit middleware): prefix `api/public/bookings` and `api/public/my-bookings`
  - Partner (auth:sanctum + partner role, rate limit middleware): prefix `api/partner/bookings`
  - Admin (auth:sanctum + admin role): prefix `api/admin/audit/bookings`

**Checkpoint**: Foundation ready — database migrated, models/dto/services created, rate limiting active, routes registered. User story implementation can now begin.

---

## Phase 3: User Story 1 - Book a Tour with Instant Confirmation (Priority: P1) 🎯 MVP

**Goal**: Traveler selects date and participant count, submits booking, receives instant confirmation with booking reference

**Independent Test**: Submit a POST to `/api/public/bookings` with valid tour slug, date, participant count, locale, and Idempotency-Key header — verify 201 response with booking reference, confirmed status, and pricing breakdown. Submit same Idempotency-Key again — verify 200 with same booking (no duplicate).

### Implementation for User Story 1

- [X] T013 [P] [US1] Create `backend/app/Domains/Booking/Actions/CreateBookingAction.php` accepting CreateBookingDTO, orchestrating: (1) validate tour exists and is published, (2) check idempotency key uniqueness — return existing booking if duplicate, (3) validate tour_date is future, (4) validate participant_count within group_size range, (5) call AvailabilityService to atomically check + reserve spots, (6) capture current tour pricing, (7) create Booking record inside DB transaction, (8) create audit log entry (action: created), (9) dispatch SendBookingConfirmationEmail job, (10) commit transaction, (11) return BookingResponseDTO
- [X] T014 [US1] Create `backend/app/Domains/Booking/Controllers/Public/BookingController.php` with `store` method that validates request (tour_slug, tour_date, participant_count, locale), extracts Idempotency-Key from header, resolves authenticated traveler, invokes CreateBookingAction, and returns JSON response per booking-api.md — 201 for new, 200 for idempotent retry, 401/403/404/409/422/429 per contract
- [X] T015 [US1] Add route `POST /api/public/bookings` to `backend/routes/api.php` pointing to `BookingController@store` with auth:sanctum middleware and RateLimitBookingMiddleware (10 req/min)
- [X] T016 [P] [US1] Create `frontend/src/lib/api/bookings.ts` with `createBooking(params: CreateBookingRequest)` function that generates UUID v4 idempotency key (`crypto.randomUUID()`), sends POST to `/api/public/bookings` with `Idempotency-Key` and `Authorization` headers, handles 200/201/409/422/429 responses per contract
- [X] T017 [P] [US1] Create `frontend/src/components/booking/ParticipantSelector.tsx` showing +/- stepper constrained by tour group_size_min/max, displaying participant count and per-person price
- [X] T018 [P] [US1] Create `frontend/src/components/booking/DateConfirmation.tsx` displaying the selected date, "Change date" link back to tour detail availability calendar, and date format localized to current locale
- [X] T019 [P] [US1] Create `frontend/src/components/booking/PriceBreakdown.tsx` showing `price_per_person × participant_count = total` with formatted currency, locale-aware number formatting
- [X] T020 [US1] Create `frontend/src/components/booking/BookingForm.tsx` composing ParticipantSelector, DateConfirmation, PriceBreakdown, and "Confirm Booking" button — reads tour_slug and date from URL search params, fetches tour detail for pricing, validates inputs, calls createBooking on submit, shows loading state, and redirects to confirmation page on success
- [X] T021 [US1] Create `frontend/src/components/booking/BookingConfirmation.tsx` displaying confirmed booking details: booking reference (BKO-XXXXXX), tour name, date, participants, total price, cancellation policy, status badge — with localized text from i18n
- [X] T022 [US1] Create booking page at `frontend/src/app/[locale]/booking/page.tsx` as SSR page reading tour_slug and date from searchParams, rendering `<BookingForm>`, with `generateMetadata()` localized to current locale
- [X] T023 [US1] Create booking confirmation page at `frontend/src/app/[locale]/booking/confirmation/page.tsx` reading booking reference from searchParams, calling booking detail API, rendering `<BookingConfirmation>`
- [X] T024 [P] [US1] Create backend feature test `backend/tests/Feature/Booking/CreateBookingTest.php` covering: valid booking returns 201 with correct structure, idempotent retry returns 200 with same data, nonexistent tour returns 404, draft tour returns 404, past date returns 422, participant count below min returns 422, participant count exceeds availability returns 409, unauthenticated returns 401, missing idempotency key returns 422
- [X] T025 [P] [US1] Create frontend E2E test `frontend/tests/e2e/booking.spec.ts` covering: full booking flow from form to confirmation, idempotent retry from browser refresh, error states (sold out, invalid count), rate limit message display

**Checkpoint**: User Story 1 fully functional — instant booking confirmation with idempotency, atomically checked availability, and pricing snapshot. 🎯 MVP ready.

---

## Phase 4: User Story 2 - View and Manage My Bookings (Priority: P2)

**Goal**: Traveler views their booking list, drills into details, and cancels eligible bookings

**Independent Test**: Authenticate as traveler, call GET `/api/public/my-bookings` — verify correct bookings returned. Call GET `/api/public/my-bookings/{ref}` — verify full detail. Call POST `.../cancel` on eligible booking — verify status transitions to `cancelled`. Call cancel on ineligible booking — verify 409.

### Implementation for User Story 2

- [X] T026 [P] [US2] Create `backend/app/Domains/Booking/Actions/GetTravelerBookingsAction.php` accepting traveler ID, page, and optional status filter — queries bookings for traveler with tour relation, orders by tour_date DESC, paginates (10 per page), returns paginated collection of BookingResponseDTOs per traveler-booking-api.md
- [X] T027 [P] [US2] Create `backend/app/Domains/Booking/Actions/CancelBookingAction.php` accepting Booking reference and traveler ID — verifies ownership, validates status is `confirmed`, calculates if cancellation window is still open (`tour_date - now()` vs `cancellation_window_hours`), transitions to `cancelled` in transaction, creates audit entry (action: cancelled, metadata: reason if provided), triggers refund event (dispatches job for spec 008), returns updated BookingResponseDTO
- [X] T028 [US2] Create `backend/app/Domains/Booking/Controllers/Public/TravelerBookingController.php` with methods:
  - `index` — validates page/status params, invokes GetTravelerBookingsAction, returns paginated JSON
  - `show` — validates reference path param, loads booking, verifies ownership (403 if not owner), returns booking detail
  - `cancel` — validates reference, invokes CancelBookingAction, returns status & message per contract
- [X] T029 [US2] Add routes to `backend/routes/api.php`:
  - `GET /api/public/my-bookings` → `TravelerBookingController@index`
  - `GET /api/public/my-bookings/{reference}` → `TravelerBookingController@show`
  - `POST /api/public/my-bookings/{reference}/cancel` → `TravelerBookingController@cancel`
  - All with auth:sanctum and rate limit middleware (120 req/min for GET, 10/min for cancel)
- [X] T030 [P] [US2] Create `frontend/src/lib/api/my-bookings.ts` with `getMyBookings(page?, status?)`, `getBookingDetail(reference)`, `cancelBooking(reference)` functions calling the corresponding API endpoints via shared client
- [X] T031 [P] [US2] Create `frontend/src/components/my-bookings/BookingCard.tsx` displaying tour cover image, title, date, participant count, status badge (color-coded: green=confirmed, blue=completed, red=cancelled, yellow=no_show), and total price — linking to booking detail
- [X] T032 [US2] Create `frontend/src/components/my-bookings/BookingList.tsx` rendering a list of BookingCard components from API data, with status filter tabs (All / Confirmed / Completed / Cancelled), loading skeleton, and empty state ("No bookings yet — find a tour to start your adventure")
- [X] T033 [P] [US2] Create `frontend/src/components/my-bookings/CancelBookingButton.tsx` with confirmation dialog ("Are you sure? Refund will be processed within 5-10 business days."), disabled state when `can_cancel` is false with tooltip explaining why, loading state during API call
- [X] T034 [US2] Create traveler bookings list page at `frontend/src/app/[locale]/my-bookings/page.tsx` as SSR page loading bookings via API, rendering `<BookingList>`, with `generateMetadata()`
- [X] T035 [US2] Create single booking detail page at `frontend/src/app/[locale]/my-bookings/[reference]/page.tsx` as SSR page loading booking detail, rendering all sections: tour info, date, participants, pricing breakdown, cancellation policy, CancelBookingButton (if eligible), with `generateMetadata()` including booking reference
- [X] T036 [P] [US2] Create backend feature test `backend/tests/Feature/Booking/TravelerBookingsTest.php` covering: list returns own bookings ordered correctly, list excludes other travelers' bookings, detail returns full object with can_cancel computed, 403 for accessing another's booking, cancel succeeds for eligible booking, cancel returns 409 within window, cancel returns 422 for already-cancelled booking
- [X] T037 [P] [US2] Create frontend E2E test `frontend/tests/e2e/my-bookings.spec.ts` covering: bookings list renders cards, status filter works, clicking card navigates to detail, cancel button shows confirmation dialog, cancel succeeds for eligible booking, cancel rejected for ineligible booking

**Checkpoint**: User Story 2 fully functional — travelers can view, manage, and cancel their bookings. US1 + US2 operational.

---

## Phase 5: User Story 3 - Booking Lifecycle for Partners (Priority: P3)

**Goal**: Partner views bookings for their tours grouped by tour/date and transitions post-tour statuses (completed, no_show)

**Independent Test**: Authenticate as partner, call GET `/api/partner/bookings` — verify only own tours' bookings returned with aggregates. Call PATCH to transition confirmed → completed (past date) — verify 200. Call PATCH for future date — verify 409. Call PATCH for another partner's tour — verify 403.

### Implementation for User Story 3

- [X] T038 [P] [US3] Create `backend/app/Domains/Booking/Actions/GetPartnerBookingsAction.php` accepting partner ID, optional tour_slug/status/date_from/date_to/page filters — queries bookings where tour.owner = partner, eager loads tour and traveler (name only), computes aggregates (total, by_status, by_tour), paginates (25 per page), returns collection with aggregate metadata per partner-booking-api.md
- [X] T039 [P] [US3] Create `backend/app/Domains/Booking/Actions/TransitionBookingStatusAction.php` accepting Booking reference, partner ID, and target status — verifies partner owns the tour (403 if not), validates tour_date is past (409 if future), validates allowed transition (confirmed→completed or confirmed→no_show), executes in transaction: update status, create audit entry, return updated BookingResponseDTO
- [X] T040 [US3] Create `backend/app/Domains/Booking/Controllers/Partner/PartnerBookingController.php` with methods:
  - `index` — validates query params, invokes GetPartnerBookingsAction, returns JSON with data + aggregates + meta
  - `updateStatus` — validates reference and status body, invokes TransitionBookingStatusAction, returns updated booking
- [X] T041 [US3] Add routes to `backend/routes/api.php`:
  - `GET /api/partner/bookings` → `PartnerBookingController@index`
  - `PATCH /api/partner/bookings/{reference}/status` → `PartnerBookingController@updateStatus`
  - Both with auth:sanctum, partner role middleware, rate limit (120 req/min)
- [X] T042 [P] [US3] Create backend feature test `backend/tests/Feature/Booking/PartnerBookingsTest.php` covering: partner sees only own tours' bookings, aggregate counts correct, filter by tour_slug works, transition to completed succeeds on past date, transition to completed on future date returns 409, transition for another partner's tour returns 403, invalid status returns 422, transition from non-confirmed status returns 422, audit entry created on transition

**Checkpoint**: Partner booking management functional. US1 + US2 + US3 operational.

---

## Phase 6: User Story 4 - Booking Audit Trail & Status Tracking (Priority: P4)

**Goal**: Immutable audit log records every booking status change; admins can view filtered audit entries and full chronological audit trails per booking, with linked financial events

**Independent Test**: Create a booking, transition its status, call GET `/api/admin/audit/bookings/{ref}` — verify full chronological trail with correct actor/action/before/after. Call GET `/api/admin/audit/bookings` with filters — verify correct entries returned.

### Implementation for User Story 4

- [X] T043 [US4] Create `backend/app/Domains/Booking/Controllers/Admin/AuditController.php` (in new directory `backend/app/Domains/Booking/Controllers/Admin/`) with methods:
  - `index` — validates booking_reference/actor_type/action/date_from/date_to/page query params, queries audit logs with filters, paginates (50 per page), returns JSON per audit-api.md
  - `show` — validates booking reference, loads all audit entries for booking ordered by created_at ASC, loads linked financial events from payments table (spec 008) via booking_id, returns combined response with entries + linked_financial_events
- [X] T044 [US4] Add routes to `backend/routes/api.php`:
  - `GET /api/admin/audit/bookings` → `AuditController@index`
  - `GET /api/admin/audit/bookings/{reference}` → `AuditController@show`
  - Both with auth:sanctum + admin role middleware, rate limit (120 req/min)
- [X] T045 [P] [US4] Create backend feature test `backend/tests/Feature/Booking/AuditTrailTest.php` covering: booking creation produces audit entry, status transition produces entry with correct before/after, audit trail returns chronological entries, filter by actor_type returns only matching entries, filter by action returns only matching entries, 403 for non-admin access, 404 for nonexistent booking reference, linked financial events appear when present

**Checkpoint**: Audit trail fully functional. All user stories complete.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Email notifications, i18n strings, concurrency testing, accessibility, performance, and validation

- [X] T046 [P] Create `backend/app/Domains/Booking/Actions/SendBookingConfirmationEmail.php` as a queued job implementing ShouldQueue, dispatched after booking creation — checks `confirmation_email_sent_at` for idempotency, renders localized email template per locale, sends via Laravel Mail, marks `confirmation_email_sent_at` timestamp, handles failures gracefully (booking remains confirmed regardless)
- [X] T047 [P] Add booking i18n strings to `frontend/src/i18n/en.json`: booking form labels (participants, date, total), button text (Confirm Booking, Cancel Booking), status labels (confirmed, completed, cancelled, no_show), error messages, confirmation page headings, empty state text
- [X] T048 [P] Add booking i18n strings to `frontend/src/i18n/es.json` — Spanish translations of all keys from en.json
- [X] T049 [P] Add booking i18n strings to `frontend/src/i18n/it.json` — Italian translations of all keys from en.json
- [X] T050 Create `backend/tests/Feature/Booking/ConcurrencyTest.php` simulating simultaneous booking attempts for the same last available spot — verifies exactly one succeeds and the other receives 409, no overbooking occurs per SC-002
- [X] T051 [P] Create `backend/tests/Feature/Booking/RateLimitTest.php` verifying 429 response after the 11th booking attempt within 1 minute, Retry-After header present, JSON error body per contract, rate limit window resets correctly
- [X] T052 Run full flow validation per `quickstart.md`: migrate fresh, seed test data, verify all API endpoints return correct contracts, verify idempotency, verify rate limits, run all test suites
- [X] T053 Run Lighthouse audit on booking and my-bookings pages — verify Performance score ≥ 90 per SC-004/FR-033. Optimize: lazy load images, code splitting for booking flow, font loading strategy inline with spec 006 T082
- [X] T054 Run WCAG 2.1 AA accessibility audit on booking flow and my-bookings pages per FR-035: verify focus trap in booking form, keyboard navigation (tab through participant selector, confirm button), screen reader labels on stepper, color contrast on status badges, focus indicators — fix all AA-level violations per spec 006 T083

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **User Stories (Phases 3–6)**: All depend on Foundational phase completion
  - US1 (P1): Can start immediately after Phase 2 — No dependencies on other stories
  - US2 (P2): Can start after Phase 2 — Uses shared types/client but independently testable
  - US3 (P3): Can start after Phase 2 — Completely independent (partner-facing)
  - US4 (P4): Depends on US1 (needs bookings to exist) but independently testable with seeded data
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### Within Each User Story

- Actions (backend logic) before controllers
- Controllers before routes (or simultaneous)
- API client functions before frontend components
- Shared components before page composition
- Page implementation before tests run against it

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- **Once Foundational phase completes, US1, US2, US3 can all start in parallel** (different domains: public booking, traveler management, partner management)
- Within each story: all [P] tasks can run in parallel
- US4 can start in parallel with US2/US3 if test data is seeded for audit log verification
- All i18n population tasks (T047–T049) can run in parallel

---

## Parallel Example: User Story 1

```bash
# Launch all [P] tasks for US1 together:
Task: "Create bookings API client at frontend/src/lib/api/bookings.ts"
Task: "Create ParticipantSelector component at frontend/src/components/booking/ParticipantSelector.tsx"
Task: "Create DateConfirmation component at frontend/src/components/booking/DateConfirmation.tsx"
Task: "Create PriceBreakdown component at frontend/src/components/booking/PriceBreakdown.tsx"
Task: "Create CreateBookingTest at backend/tests/Feature/Booking/CreateBookingTest.php"
Task: "Create booking E2E test at frontend/tests/e2e/booking.spec.ts"

# Then sequential:
Task: "Create CreateBookingAction (depends on T013)"
Task: "Create BookingController (depends on T014)"
Task: "Create BookingForm component (depends on T017, T018, T019)"
Task: "Create /booking page (depends on T020, T021, T022, T023)"

# Tests in parallel:
Task: "Backend CreateBookingTest + Frontend booking E2E"
```

---

## Parallel Example: Multi-Story Parallelism (After Foundational)

```bash
# Developer A: US1 - Booking Creation
Task: "Phase 3 tasks T013–T023"

# Developer B: US2 - Traveler Booking Management
Task: "Phase 4 tasks T026–T035"

# Developer C: US3 - Partner Booking Management
Task: "Phase 5 tasks T038–T041"

# All merge independently after Foundational checkpoint
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T003)
2. Complete Phase 2: Foundational (T004–T012) — ⚠️ CRITICAL
3. Complete Phase 3: User Story 1 (T013–T023)
4. **STOP and VALIDATE**: Test booking creation independently via API and frontend
5. Run tests (T024–T025)
6. Deploy/demo — working booking MVP

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 (Booking Creation) → Test independently → **MVP deploy**
3. Add US2 (Traveler Bookings) → Test independently → Deploy (travelers can manage bookings)
4. Add US3 (Partner Bookings) → Test independently → Deploy (partners manage attendance)
5. Add US4 (Audit Trail) → Test independently → Deploy (full auditability)
6. Polish (emails, i18n, a11y, perf) → Final deploy
7. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers after Phase 2:

- Developer A: US1 → US4 (booking creation + audit trail)
- Developer B: US2 (traveler booking management)
- Developer C: US3 (partner booking management)

---

## Notes

- [P] tasks = different files, no dependencies — safe to run concurrently
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Booking references are server-generated (BKO-XXXXXX); no client input accepted for reference
- Idempotency keys are client-generated UUID v4 sent as HTTP header
- Financial audit entries MUST be created synchronously within the DB transaction
- Availability checks use `SELECT FOR UPDATE` row-level locking — ensure testing covers concurrency
- Email delivery is fire-and-forget; booking remains confirmed even if email fails
- All monetary values stored as integers in smallest currency unit (cents)
