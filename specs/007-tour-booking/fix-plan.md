# Fix all spec-007 tour-booking code-review findings
Implementation Rules (Mandatory)

Before changing any code:

Re-read the Constitution and follow every MUST requirement.
Do NOT change public APIs unless explicitly instructed.
Reuse existing domain actions, services, DTOs, transformers and helpers wherever possible.
Do NOT duplicate business logic.
Keep controllers thin.
Keep all changes backward compatible unless this prompt explicitly changes the contract.
Preserve existing architecture and naming conventions.
Do NOT introduce new dependencies unless absolutely required.
Run the relevant backend and frontend tests after each logical phase and fix any failures before continuing.
If a better implementation exists than the one suggested here while still satisfying the spec, choose the cleaner implementation and explain the decision in the final summary.
Do not stop after fixing the first failing test. Continue until every item in this prompt is completed.
At the end, produce a checklist showing every finding (F1–F15, L1–L8) and whether it is:
Fixed
Already fixed
Not applicable
with the exact files changed.

> Companion to [`code-review.md`](./code-review.md). Source of truth for the implementation pass that resolves every still-present finding.

## Context

`specs/007-tour-booking/code-review.md` documented 15 findings (F1–F15) plus 8 lower-severity items (L1–L8) against the spec-007 booking feature. Several were already fixed by later specs (F11 cross-traveler cancel 403, F12 create-title, the crypto-TypeError half of F2) and are confirmed-only. The still-present findings are real correctness, contract, and maintainability gaps: an overbooking race (F1), double-submit duplicate bookings (F2), a same-key-insert 500 (F3), stale price/capacity (F4), cancellation and no_show cutoffs anchored to `tour_date` midnight instead of a start time (F5/F6), confirmation-page error masking (F7), reference-collision 500 (F8), bookable non-operating dates (F9), empty partner-list tour title (F10), missing `cancelled_at` (F13), no pagination (F14), and a missing `<Suspense>` boundary (F15); plus contract/cleanup items L1–L7.

User decisions: **F5 → add a `bookings.start_time` column** (snapshot from the matching availability rule at booking time); **scope → all still-present findings** (correctness + contract + cleanups + i18n). Postgres-only; money in cents; tests run in Docker.

Outcome: a correct, contract-compliant booking flow — capacity is enforced under concurrency, idempotency retries never 500, cutoffs match the tour start time, the partner audit exposes real financial events, the frontend submits once and reports real errors, my-bookings paginates, and the booking route builds cleanly.

---

## Phase 1 — Backend correctness (data integrity)

### F1 + F4 — lock + refresh the tour row inside the transaction
`backend/app/Domains/Booking/Actions/CreateBookingAction.php`
- Keep the outside-txn `Tour::where('slug',$dto->tourSlug)->first()` only for the early 404 / draft checks.
- Inside `DB::transaction`, re-fetch **locked**: `$tour = Tour::where('id',$tour->id)->lockForUpdate()->firstOrFail();` before reading `lowestPriceAmount()`, `group_size_max`, currency, and before calling `AvailabilityService`. Serializes concurrent booking txns per tour → fixes overbooking (F1) and gives fresh price/capacity (F4). (Row lock chosen over `pg_advisory_xact_lock` — it doubles as F4's freshness mechanism; contention on one tour row is negligible. Revisit only if profiling shows a hot tour.)

### F3 + F8 — handle unique-constraint violations on insert
`backend/app/Domains/Booking/Actions/CreateBookingAction.php`
- Remove the pre-check + `lockForUpdate` idempotency re-check (the unique index is now the source of truth).
- Wrap `Booking::create([… 'reference' => Booking::generateReference() …])` in a retry loop (max 3). Catch `Illuminate\Database\QueryException`; branch on **`$e->errorInfo[0] === '23505'`** (do NOT substring-match constraint names — brittle across PG versions):
  - **idempotency-key collision** (the row with `$dto->idempotencyKey` now exists): re-read `Booking::where('idempotency_key',$key)->first()`, **`->load('tour.translations')`** (must match the 201 shape — tour.title non-empty), return it as the idempotent 200 (`is_retry=true`) **without creating a second PaymentIntent or audit**.
  - **reference collision**: regenerate the reference and retry the loop.
  - Other SQLSTATE: rethrow.
- Alternative considered: `INSERT … ON CONFLICT (idempotency_key) DO NOTHING` then fetch — acceptable but the try/catch keeps the regenerate-reference loop unified. Pick whichever reads cleanest in the file.

### F9 — validate `tour_date` against the operating schedule
- `backend/app/Models/Tour.php`: extract the rule/exception evaluator already inside `upcomingAvailableDates()` into a private `evaluateRulesForDate(Carbon $date): bool` (specific_date match; recurring rule whose `days_of_week` contains `$date->dayOfWeek` within `[start_date,end_date]`; minus `AvailabilityException` `exception_type='block'` on that date). Both `operatesOnDate()` and `upcomingAvailableDates()` call it so the two never drift. `operatesOnDate()` is **not** bounded by the 90-day/60-cap.
- Add public `operatesOnDate(Carbon $date): bool` thin wrapper.
- `CreateBookingAction`: after the future-date check, `if (! $tour->operatesOnDate($date)) throw new ValidationException::withMessages(['tour_date' => ['This tour does not operate on the selected date.']])` (ValidationException also satisfies L2's `{message,errors}` shape — see Phase 2).

---

## Phase 2 — Backend contract compliance

### L1 — `locale` required
`backend/app/Domains/Booking/Controllers/Public/BookingController.php`
- `'locale' => 'required|string|in:en,es,it'` (drop `sometimes` and `max:2`). DB column is `string('locale',2)` — no migration. Update existing `CreateBookingTest` cases to send `locale`; add a test omitting it → 422.

### L2 — 422 carries the `errors` structure
- `CreateBookingAction`: for field-level errors (past date, participant count over `group_size_max`, non-operating date) throw `ValidationException::withMessages([...])` instead of `UnprocessableEntityHttpException`. Laravel auto-renders `{message, errors:{…}}` with 422.

### L4 — remove dead try/catch HttpException re-mapping
- **First** grep `backend/routes/` to confirm booking/partner/traveler booking routes are registered under the `api` group (or `Route::middleware('api')`). If any sit under `routes/web.php`, **do not remove** their catch (would render HTML error pages) — relocate the route instead, then remove.
- Remove the try/catch re-mapping in `BookingController`, `PartnerBookingController`, `TravelerBookingController::cancel`. Laravel renders Symfony HttpExceptions as JSON `{message}` + status for `api`-grouped requests; `ValidationException` (L2) renders `{message,errors}`.

### F13 — emit `cancelled_at`
`backend/app/Domains/Booking/DTOs/BookingResponseDTO.php`
- Add `'cancelled_at' => $booking->cancelled_at?->toIso8601String()` to `fromBooking()`.

### L3 — standardize on `pricing.total` (contract amendment)
- Remove the top-level `total_price` from `BookingResponseDTO::fromBooking()`.
- Amend `specs/007-tour-booking/contracts/traveler-booking-api.md:43` to use `pricing.total` (reconcile with `booking-api.md:55` which already uses it).
- Update the frontend `BookingResponse` type + consumers. `frontend/src/components/booking/BookingConfirmation.tsx:36` uses `booking.total_amount ?? booking.total_price` → move to `booking.pricing.total`. Grep for all `total_price` consumers before editing.

### F10 — partner booking list tour title
`backend/app/Domains/Booking/Actions/GetPartnerBookingsAction.php`
- Eager-load `['tour.translations','traveler']`. Resolve the title per booking by `$booking->locale` (confirmed `bookings.locale` exists, `string('locale',2)->default('en')`): `$tour->translations->firstWhere('locale',$booking->locale)?->title ?? $tour->translations->firstWhere('locale','en')?->title ?? ''`.
- Drop the redundant 4-status-count-queries + groupBy if a single pass / SQL window can compute them; otherwise leave (cleanup is secondary).

### L5 — AuditController uses a real Payment relation, not a schema probe
`backend/app/Domains/Booking/Controllers/Admin/AuditController.php`
- Add `Booking::payments(): HasMany` to `backend/app/Domains/Booking/Models/Booking.php` (→ `App\Domains\Payment\Models\Payment`; existing `payment()` HasOne stays for the "latest/single" case). A booking has a charge AND a refund, so the audit's `linked_financial_events` (required by `audit-api.md:130-138`) must be a list — HasOne is wrong.
- `AuditController::show`: eager-load `payments`, render `linked_financial_events` via a small transformer (id, type, amount, currency, stripe_id, stripe_refund_id, created_at). **Remove** the `\Schema::hasTable('payments')` runtime probe and hand-rolled mapping — the table always exists post-spec-008.

### F5 — cancellation deadline from tour start time
- Migration: add nullable TIME `start_time` to `bookings` (after `tour_date`). Add to `$fillable` and `$casts` (`datetime:H:i:s`).
- `Booking`: add `startDateTime(): Carbon` — `tour_date` set to the stored `start_time`, or `tour_date` + config `bookings.default_start_time` (default `'09:00'`) when null. (Store the resolved default explicitly at booking time so the row is self-describing.)
- `CreateBookingAction`: at create, resolve `start_time` from the matching `AvailabilityRule` for the date (earliest `start_time` of rules `evaluateRulesForDate()` true; config default `'09:00'` when none) and store it — snapshot semantics like price.
- `Booking::canCancel()`: `$deadline = $this->startDateTime()->subHours($this->cancellation_window_hours); return now() <= $deadline;`.
- **Amend `traveler-booking-api.md:173`** (it literally says `tour_date - cancellation_window_hours`; the policy text + 409 message say "start time") to reference the tour start time, so the code matches the contract.

### F6 — no_show/completed only after the tour date
`backend/app/Domains/Booking/Actions/TransitionBookingStatusAction.php`
- Change `if ($booking->tour_date->isFuture())` to: throw 409 unless the tour date is strictly in the past — `if (! ($booking->tour_date < Carbon::today()))`. Date-only, per `partner-booking-api.md:81/149` ("after the tour date (UTC)"). **Do not** add `startDateTime()->isPast()` (would allow day-of after start — a contract divergence).

---

## Phase 3 — Frontend

### F2 — stable idempotency key + synchronous submit guard
`frontend/src/lib/api/bookings.ts`
- `createBooking(params, idempotencyKey?)`: generate `uuidv4()` only when not supplied (backward compatible).
`frontend/src/components/booking/BookingForm.tsx`
- `const idempotencyKey = useMemo(() => uuidv4(), [tourSlug, date, participants])` — stable across retries of the same selection, new on selection change. Pass to `createBooking`.
- `const submittingRef = useRef(false)`; **first** statement of `handleSubmit`: `if (submittingRef.current) return; submittingRef.current = true;` (synchronous, before any `await`). Reset in `finally`. Keep the reactive `submitting` state for button disable.

### F7 — BookingConfirmation reports real errors
`frontend/src/components/booking/BookingConfirmation.tsx`
- Replace `.catch(() => {})` with a handler that inspects the typed `ApiError.status`: 401 → `router.push(\`/${locale}/login\`)`; 404 → "Booking not found."; others → retryable error UI with a retry button. Keep `getBookingDetail`'s error surfaced.

### F14 — my-bookings pagination
- **First** read current `frontend/src/components/my-bookings/BookingList.tsx` and `frontend/src/hooks/useBookings.ts` to confirm what's already wired (the hook refactor may already return `meta`). Grep `total_price`/`getMyBookings` consumers.
- `useBookings(status, locale, page)`: pass `page` to `getTravelerBookings`, return `{data, meta, error, loading}`; reset `error` to null before each refetch.
- `BookingList`: track `page` in state; render "Load more" / page controls from `meta.last_page`; refetch on page/filter change.

### F15 — Suspense boundary
`frontend/src/app/[locale]/(public)/booking/page.tsx`
- Wrap `<BookingForm>` in `<Suspense fallback={…}>` (`BookingForm` calls `useSearchParams()`).

### L6 — shared currency formatter
- Add/reuse `formatCurrency(amountCents, currency, locale)` in `frontend/src/lib/` using `Intl.NumberFormat(locale,{style:'currency',currency})`. Wire into `BookingForm` (replaces the EUR/USD-symbol ternary) and `BookingConfirmation` (replaces hardcoded EUR/'en' `formatMoneyValue`).

### L7 — i18n the booking components
- Audit existing `booking.*` keys in `frontend/messages/{en,es,it}.json` vs every user-facing string in `BookingForm`, `BookingConfirmation`, `CancelBookingButton`, and the `booking/page.tsx` inline `locale === 'es' ? …` ternaries. Add missing keys to **all three** locales in one pass. Migrate components to `useTranslations('booking')` / server `getTranslations`.

### L8 — token helper
- `frontend/src/lib/api/bookings.ts` and `my-bookings.ts`: replace direct `localStorage.getItem('auth_token')` with the existing `getAuthToken()` (already used by partner/traveler APIs).

---

## Phase 4 — Tests + contract amendments

Backend tests live in `backend/tests/Feature/Booking/`; run via
`docker exec bookly-backend php vendor/bin/pest --configuration phpunit.pgsql.xml tests/Feature/Booking`.

Add a small availability-setup helper to `backend/tests/Pest.php` (creates an `AvailabilityRule` + optional `AvailabilityException`) — none exists today.

New / updated tests:
- `CreateBookingTest`: idempotency 201→200 — extend to assert the 200 body has **non-empty `tour.title`** and that **no second Payment row** was created (regression for the F3 catch path). locale-omitted → 422 (L1). non-operating-date → 422 (F9).
- `ConcurrencyTest`: keep the sequential overbooking smoke test (still passes with the tour-row lock). Document that true parallel overbooking is validated separately (no parallel harness in Pest).
- `TravelerBookingsTest`: past-window cancel → 409 (F5); cross-traveler cancel → 403 (F11 regression guard). cancel response `data.cancelled_at` present (F13).
- `PartnerBookingsTest`: `no_show` on day-of (today) → 409; `no_show` on a past date → 200 (F6). partner booking list `tour.title` non-empty (F10).
- Audit: `AuditController::show` returns `linked_financial_events` from real `Payment` rows (charge + refund), no `\Schema::hasTable` (L5).

Frontend:
- BookingForm double-submit: a jest test mocking `createBooking`, double-click "Confirm", assert exactly one call (F2 synchronous guard).
- BookingList: a test for "Load more" / page navigation using `meta.last_page` (F14).
- `npm run lint` + `tsc --noEmit` clean (next-intl key parity enforced by the i18n pass).

Contract amendments landed with their code:
- `traveler-booking-api.md:43` → `pricing.total` (L3).
- `traveler-booking-api.md:173` → "tour start time" wording (F5).

---

## Verification (end-to-end)

1. Backend: bring up postgres+redis+meilisearch containers, run the Docker Pest command above — all Booking tests green (existing + new).
2. `cd backend && php artisan migrate` (applies the `start_time` migration); `php artisan tinker` — create a booking for a date with an AvailabilityRule `start_time=14:00`, assert `booking->start_time === '14:00'` and `canCancel()` honors `tour_date 14:00 - window`.
3. Manual race check (optional, F1/F3): two concurrent `curl` POSTs to `/api/bookings` with the same Idempotency-Key and oversubscribed `participant_count` → one 201, one 200 with the same `reference`; capacity not exceeded.
4. Frontend: `npm run lint && npm run build` — the `[locale]/booking` route builds without the `useSearchParams` Suspense error (F15); `npm run test` (jest) — double-submit + pagination tests pass.
5. Smoke the cancel flow: book → cancel inside the window → 200 with `cancelled_at`; cancel outside the window → 409 with `errors.tour_date` (F5/L2).
6. Partner: PATCH `status=no_show` on a day-of booking → 409; on a past-date booking → 200 (F6). `GET /api/partner/bookings` → non-empty `tour.title` (F10). `GET` audit → `linked_financial_events` populated (L5).

## Critical files
- `backend/app/Domains/Booking/Actions/CreateBookingAction.php` (F1/F3/F4/F8/F9, F5 snapshot, L2)
- `backend/app/Domains/Booking/Models/Booking.php` (F5 startDateTime/canCancel, F8 ref regen, L5 payments HasMany)
- `backend/app/Domains/Booking/DTOs/BookingResponseDTO.php` (F13, L3)
- `backend/app/Domains/Booking/Actions/TransitionBookingStatusAction.php` (F6)
- `backend/app/Domains/Booking/Controllers/Public/BookingController.php` (L1, L4)
- `backend/app/Domains/Booking/Actions/GetPartnerBookingsAction.php` (F10)
- `backend/app/Domains/Booking/Controllers/Admin/AuditController.php` (L5)
- `backend/app/Models/Tour.php` (F9 operatesOnDate/evaluateRulesForDate)
- `frontend/src/lib/api/bookings.ts` (F2, L8) · `frontend/src/components/booking/BookingForm.tsx` (F2, L6, L7) · `frontend/src/components/booking/BookingConfirmation.tsx` (F7, L6, L7) · `frontend/src/hooks/useBookings.ts` + `components/my-bookings/BookingList.tsx` (F14) · `frontend/src/app/[locale]/(public)/booking/page.tsx` (F15)
- `specs/007-tour-booking/contracts/traveler-booking-api.md` (L3, F5 amendments)

## Out of scope (already fixed — confirm only)
- F11 (cross-traveler cancel 403), F12 (create title), the crypto-TypeError half of F2, the email-in-transaction concern, the TourCardTransformer N+1 — all fixed by later specs. Note: the review's "historical" framing for F15 is inaccurate; F15 is still present and in scope.

Before finishing:

run formatting
run static analysis
run backend tests
run frontend tests
run type checking
run lint
verify migrations
verify no contract regressions
verify no Constitution violations

Do not finish until everything passes.