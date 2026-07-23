# Phase 007 — Tour Booking: Detailed Code Review Report

**Scope:** diff `2daf870...380bee1` (the tour-booking feature — backend `Domains/Booking` domain: actions, controllers, models, services, DTOs, middleware, migrations; `Search/Transformers/TourCardTransformer`; public/admin/partner route files; frontend booking pages, components, API client, hooks, e2e specs; contracts).

**Method:** 4 parallel finder agents (correctness / contract-compliance / frontend / cleanup+conventions) → every candidate verified directly against the **spec-007 diff version** of each file (`git show 380bee1:<path>`) → sweep → ≤15 findings.

> **Verification note:** later specs (010 frontend, 011, 013, 014) substantially rewrote the booking frontend and fixed several issues. To avoid both hiding real spec-007 bugs and inventing bugs about later-added code, every candidate was verified against the file content **as it existed at commit 380bee1**, not current `HEAD`. Each finding below is annotated with its current status (STILL PRESENT vs FIXED in a later spec).

**Result:** 15 confirmed findings + lower-severity/cut items + refuted claims.

---

## 🔴 Critical findings

### F1 — Overbooking race: capacity check locks booking rows, never the tour row
**File:** `backend/app/Domains/Booking/Services/AvailabilityService.php:20`

**Evidence:** `checkAndReserve()` does `SELECT … FOR UPDATE` on existing `bookings` rows for the date, sums `participant_count`, and compares to `$tour->group_size_max`. On PostgreSQL READ COMMITTED, `FOR UPDATE` on zero rows locks nothing.

**Failure scenario:** Tour `group_size_max=10`, no bookings for 2026-07-20. Two travelers POST 6 participants each concurrently → both `SELECT … lockForUpdate` find zero rows (locks nothing), both compute `remaining=10 ≥ 6`, both `INSERT` → **12 seats booked**, overbooking beyond capacity. The `tours` row is never locked.

**Fix:** Lock the tours row inside the transaction (`Tour::where('id',$tour->id)->lockForUpdate()->first()`) before the capacity sum, or use an advisory lock keyed on `tour_id + date`. *Status: STILL PRESENT.*

---

### F2 — Double-submit creates duplicate bookings (per-call Idempotency-Key + reactive-only submit guard)
**Files:** `frontend/src/lib/api/bookings.ts:45` · `frontend/src/components/booking/BookingForm.tsx:53,133`

**Evidence — fresh key per call:**
```ts
// bookings.ts (spec-007) — createBooking()
const idempotencyKey = crypto.randomUUID();
```
**Evidence — reactive-only guard:**
```tsx
// BookingForm.tsx
setSubmitting(true);              // line 53 — async state update
…
<button disabled={submitting || !tourSlug || !date}>  // line 133
```

**Failure scenario:** Two rapid clicks on "Confirm Booking": both handlers run from the same render where `submitting=false` (stale closure; the button isn't disabled until the re-render commits), both call `createBooking` with **two distinct** `crypto.randomUUID()` keys → the backend treats them as two separate requests → two `Booking` rows, two capacity reservations (compounding F1), two payment intents. A network-error retry also gets a new key, so the contract's 200-idempotent-retry path can never trigger. (The `crypto.randomUUID()` TypeError-on-HTTP half was fixed later with a fallback, but the per-call-UUID and reactive-guard issues remain.)

**Fix:** Generate the Idempotency-Key **once per user attempt** (persist in component state/ref) so retries reuse it; guard the submit **synchronously** (a `useRef` flag) so a second click in the same tick is a no-op. *Status: STILL PRESENT.*

---

### F3 — Same-key idempotency insert races to a 500
**File:** `backend/app/Domains/Booking/Actions/CreateBookingAction.php:59`

**Evidence:** Pre-check outside the txn (line ~29) + `lockForUpdate()` re-check inside the txn. On READ COMMITTED, `FOR UPDATE` on a non-existent row locks nothing.

**Failure scenario:** Two concurrent requests carry the **same** `Idempotency-Key`. Both pass the pre-check and the `lockForUpdate` re-check (no row exists yet → nothing locked). Both call `Booking::create`; the second violates the `idempotency_key` unique constraint → `QueryException`. The controller only catches `NotFound/Conflict/Unprocessable` → traveler gets **500** instead of the idempotent 200.

**Fix:** Wrap `Booking::create` to catch the unique-constraint `QueryException` and re-read the existing row (or use `INSERT … ON CONFLICT (idempotency_key) DO NOTHING` then fetch). *Status: STILL PRESENT.*

---

### F4 — Stale tour price / capacity (tour loaded outside the transaction)
**File:** `backend/app/Domains/Booking/Actions/CreateBookingAction.php:36`

**Evidence:**
```php
$tour = Tour::where('slug', $dto->tourSlug)->first();   // line 36 — OUTSIDE the txn
…
$pricePerPerson = $tour->lowestPriceAmount();           // line 76 — inside the txn, stale model
```

**Failure scenario:** Partner raises the price 10000→15000 while a booking request is in flight. The action holds the pre-edit `Tour` model; `lowestPriceAmount()` and `AvailabilityService`'s `group_size_max` use the stale values → traveler charged €100 not €150, and the `price_changed` detection (which compares against the same stale value) never fires. Same staleness applies to `group_size_max`.

**Fix:** Reload `$tour` with `lockForUpdate()` **inside** the transaction before reading price/capacity. *Status: STILL PRESENT.*

---

### F5 — `canCancel()` deadline uses `tour_date` midnight, not the tour start time
**File:** `backend/app/Domains/Booking/Models/Booking.php:96`

**Evidence:**
```php
$deadline = (clone $this->tour_date)->subHours($this->cancellation_window_hours);
```
`tour_date` is cast to `date` (midnight), but the policy text says "X hours before the tour **start time**".

**Failure scenario:** Tour on 2026-07-20 starts 09:00, `cancellation_window_hours=24`, policy = "…up to 24 hours before the tour start time." Policy deadline = 2026-07-19 09:00. Code: `tour_date`(midnight) − 24h = 2026-07-19 00:00. Traveler cancels at 2026-07-19 08:00 (25h before start, within policy) → `canCancel()` returns false → **409**, valid cancellation denied. The error is one-directional (always stricter, up to ~24h).

**Fix:** Store/use the tour start time for the cutoff, not `tour_date` midnight. *Status: STILL PRESENT.*

---

### F6 — Partner can mark a booking `no_show`/`completed` before the tour starts
**File:** `backend/app/Domains/Booking/Actions/TransitionBookingStatusAction.php:40`

**Evidence:**
```php
if ($booking->tour_date->isFuture()) { throw new ConflictHttpException(...); }
```
`tour_date` cast to `date` = midnight; on the day-of the tour, midnight is in the past → `isFuture()` is false.

**Failure scenario:** Tour date = today 2026-07-17, start 14:00. At 08:00 the partner PATCHes `status=no_show`. `tour_date`(2026-07-17 00:00)->isFuture() = false → guard passes → booking marked `no_show` and audited, traveler penalized **6h before the tour even begins**. No `start_time` column exists to gate on the actual start.

**Fix:** Gate on the tour's actual start time (store one, or derive from the availability rule), not `tour_date` midnight. *Status: STILL PRESENT.*

---

### F7 — BookingConfirmation masks auth/network errors as "Booking not found"
**File:** `frontend/src/components/booking/BookingConfirmation.tsx:19,31`

**Evidence:**
```tsx
getBookingDetail(reference).then((res) => setBooking(res.data)).catch(() => {})
…
if (!booking) { return <p>Booking not found.</p>; }
```

**Failure scenario:** Traveler's auth token expires between booking and the confirmation page load → `getBookingDetail` 401 → caught and discarded → UI shows "Booking not found." instead of redirecting to login; the user believes the booking vanished rather than needing to re-authenticate. Same for 403/timeout/network.

**Fix:** Inspect the error status; on 401 redirect to login, on 404 show "not found", on others show a retryable error. *Status: STILL PRESENT.*

---

## 🟠 High findings

### F8 — `generateReference()` TOCTOU → 500 on collision
**File:** `backend/app/Domains/Booking/Models/Booking.php:81`

**Evidence:**
```php
} while (self::where('reference', $reference)->exists());
```
The existence check and the subsequent `Booking::create` are not atomic.

**Failure scenario:** Two concurrent booking creations draw the same 6-char reference from the 32-char alphabet (32^6 ≈ 1e9; birthday collision plausible at scale). Both `where('reference', …)->exists()` return false, both insert with the same reference; the second hits the `reference` unique constraint → uncaught `QueryException` → **500** to a legitimate attempt.

**Fix:** Catch the unique-constraint `QueryException` and retry generation, or use `INSERT … ON CONFLICT (reference) DO NOTHING` + retry. *Status: STILL PRESENT.*

---

### F9 — `tour_date` not validated against the tour's operating schedule
**File:** `backend/app/Domains/Booking/Actions/CreateBookingAction.php:41`

**Evidence:** The only date check is `$dto->tourDate <= now()->toDateString()` (future). `AvailabilityService` only sums existing bookings vs `group_size_max` — no check that the tour operates on that date (`availabilityRules`/`availabilityExceptions`).

**Failure scenario:** Tour operates Mon/Wed/Fri only. Traveler POSTs `tour_date=2026-07-21` (Tuesday). The `> today` guard passes; `AvailabilityService` only checks capacity → booking created for a non-operating date. The tour-detail `available_dates` gate is bypassed by calling the API directly. *(booking-api.md:35 only requires "future date", so this is a functional gap rather than a strict contract breach.)*

**Fix:** Validate `tour_date ∈ Tour::upcomingAvailableDates()` (or against the availability rules) before reserving. *Status: STILL PRESENT.*

---

### F10 — Partner booking list hardcodes `tour.title` to `''`
**File:** `backend/app/Domains/Booking/Actions/GetPartnerBookingsAction.php:54`

**Evidence:** The action loads `['tour','traveler']` (no translations) and builds `'tour' => ['slug' => …, 'title' => '']`. Contract `partner-booking-api.md:41` requires `"title": "Tuscany Wine Tasting Experience"`.

**Failure scenario:** `GET /api/partner/bookings` returns every row with `tour.title=''`. Partner dashboard / consumers can never display the tour name.

**Fix:** Load `tour.translations` and resolve the title by locale (mirror `BookingResponseDTO`). *Status: STILL PRESENT.*

---

### F11 — Cross-traveler cancel returns 404, not the 403 the contract requires *(FIXED in a later spec — historical)*
**File:** `backend/app/Domains/Booking/Actions/CancelBookingAction.php:28` (spec-007 version)

**Evidence (spec-007):**
```php
if ($booking->traveler_id !== $travelerId) {
    throw new NotFoundHttpException('Booking not found.');   // 404
}
```
Contract `traveler-booking-api.md:110-116` requires 403 "You do not have access to this booking." (`TravelerBookingController::show` already threw `AccessDeniedHttpException`; cancel did not).

**Failure scenario:** Traveler A cancels B's booking → 404 "Booking not found." instead of 403, leaking no-such-vs-forbidden and violating the contract.

**Fix:** (Applied later — current code throws `AccessDeniedHttpException` → 403.) *Status: FIXED in a later spec.*

---

### F12 — Create-201 response ships `tour.title=''` *(FIXED in a later spec — historical)*
**File:** `backend/app/Domains/Booking/Actions/CreateBookingAction.php:107` (spec-007 version)

**Evidence (spec-007):** after the transaction, `$booking->load('tour')` — not `tour.translations`. `BookingResponseDTO` only fills `title` when `tour.translations` is loaded (it isn't) → the 201 body has `tour.title=''` though `booking-api.md:48-52` requires the title.

**Fix:** (Applied later — current code loads `tour.translations` at line 120.) *Status: FIXED in a later spec.*

---

### F13 — Cancel response omits `cancelled_at`
**File:** `backend/app/Domains/Booking/DTOs/BookingResponseDTO.php:13`

**Evidence:** `BookingResponseDTO::fromBooking()` never emits `cancelled_at` even though the model sets it. Contract `traveler-booking-api.md:149` requires `"cancelled_at": "2026-05-10T08:00:00Z"` in the cancel 200 body.

**Failure scenario:** `POST /my-bookings/{ref}/cancel` returns `{data:{reference, tour, pricing, status, …}, message}` — the `cancelled_at` timestamp is missing.

**Fix:** Emit `cancelled_at => $booking->cancelled_at?->toIso8601String()` in the DTO. *Status: STILL PRESENT.*

---

### F14 — `BookingList` hardcodes page 1 (no pagination) and never clears stale error
**File:** `frontend/src/components/my-bookings/BookingList.tsx:27,29`

**Evidence:**
```tsx
getMyBookings(1, activeFilter || undefined)   // line 27 — page 1 hardcoded, meta.last_page ignored
…
.catch(() => setError('Failed to load bookings.'))   // line 29 — never reset to null on filter change
```

**Failure scenario:** A traveler with >10 bookings (per_page 10) can never reach page 2 — no pager UI, `getMyBookings(1, …)` is hardcoded. Separately, switching the status filter sets `error` only on failure and never resets it → a prior "Failed to load bookings." stays visible during/after the new fetch.

**Fix:** Drive `page` from state + render pagination from `meta.last_page`; clear `error` to null before each refetch. *Status: REFACTORED to a `useBookings` hook in a later spec — re-verify whether pagination UI was actually added (current `BookingList` still shows no pager).*

---

### F15 — `booking/page.tsx` renders `<BookingForm>` (uses `useSearchParams`) with no `<Suspense>` *(route refactored in a later spec — historical)*
**File:** `frontend/src/app/[locale]/booking/page.tsx:40`

**Evidence (spec-007):** `<BookingForm locale={locale} />` rendered directly, no `<Suspense>` boundary. `BookingForm` calls `useSearchParams()`.

**Failure scenario:** `next build` of `/{locale}/booking` → "useSearchParams() should be wrapped in a suspense boundary" error, or the route deopts to client-side rendering, losing the `generateMetadata` static output.

**Fix:** Wrap `<BookingForm>` in `<Suspense>`. *Status: the booking route has since been restructured in current code — verify whether the boundary was added.*

---

## 🟢 Lower-severity / cut below the cap (real, still present)

- **L1 — `locale` validated `sometimes` vs contract-required** — `BookingController.php:21` uses `'sometimes|string|in:en,es,it|max:2'`; `booking-api.md:37` marks `locale` **required**. Omitting it silently defaults to `en` instead of 422.
- **L2 — 422 from action-thrown validation omits the `errors` structure** — `BookingController.php:62` catches `UnprocessableEntityHttpException` → `{message}` only. `booking-api.md:108-113` requires `{message, errors:{tour_date, participant_count}}`. (Controller-level `$request->validate` failures do include `errors`; action-level ones don't.)
- **L3 — `BookingResponseDTO` emits `pricing.total` AND top-level `total_price`** — two identical amount/currency/formatted objects; derivable, duplicated state that will drift.
- **L4 — try/catch re-mapping HttpExceptions is dead boilerplate** — `BookingController:58-64`, `PartnerBookingController:52-60`, `TravelerBookingController::cancel:84-92` each catch Symfony HttpExceptions and re-render `{message}`. Laravel's exception handler already renders them as JSON with the correct status. ~40 lines that will drift (the 403 branch exists only in some copies).
- **L5 — `AuditController::show` probes `\Schema::hasTable('payments')` at request time** — runtime schema introspection + a hand-rolled payment→array mapping on the audit-read path. Altitude: special-casing spec 008 onto the shared audit endpoint; implement `linked_financial_events` in the Payments domain instead.
- **L6 — `BookingForm.formatPrice` hardcodes EUR/USD symbols** — `currency === 'EUR' ? '€' : currency === 'USD' ? '$' : ''`. A GBP tour renders "178.00" with no symbol, diverging from the backend-formatted string used elsewhere.
- **L7 — Booking components hardcode English despite next-intl keys** — `BookingForm`, `BookingConfirmation`, `CancelBookingButton`, `booking/page.tsx` (inline `locale === 'es' ? …` ternaries) hardcode user-facing strings while `booking.*` keys exist in `messages/{en,es,it}.json`. `frontend/AGENTS.md:21`: "Must adhere to defined `next-intl` localization message schemas (e.g. `messages/en.json`) when producing user-facing interfaces."
- **L8 — `bookings.ts`/`my-bookings.ts` use `localStorage.getItem('auth_token')` directly** — duplicating the existing `getAuthToken()` helper used by partner/traveler APIs; any future token-storage change silently breaks booking auth.

---

## ❌ Refuted / out-of-scope candidates (verified false for the spec-007 diff)

1. **"Stripe refund issued inside `DB::transaction` → ledger drift / double-refund"**: REFUTED for spec-007. The spec-007 `CancelBookingAction` has no `ProcessRefundAction` (payment/refund is spec 008); it only updates status + audit. The refund-in-transaction concern is about later code, out of scope.
2. **"TourCardTransformer N+1 (uses `->translations()->where()` builder, ignoring eager-load)"**: CONFIRMED real for the spec-007 diff (the transformer did invoke the relation as a builder), but **already fixed** in a later spec (current code checks `relationLoaded('translations')` and uses the collection). Historical.
3. **"`crypto.randomUUID()` throws TypeError on HTTP, aborting booking"**: CONFIRMED for spec-007 (no fallback), but **already fixed** (later `uuidv4()` falls back to `getRandomValues`/`Math.random`). The per-call-UUID half of this finding (F2) is still present.
4. **"Audit `date_from`/`date_to` validated as `date` rejects ISO-8601 datetimes"**: REFUTED. Laravel's `date` rule accepts ISO-8601 datetime values (e.g. `2026-05-09T14:30:00Z`), and Postgres casts them in the `where('created_at','>=',…)` comparison — no 422.
5. **"Confirmation-email job dispatched inside `DB::transaction`"**: real for spec-007 (`SendBookingConfirmationEmail::dispatch()` ran inside the txn → could execute pre-commit / escape rollback), but **removed** when the payment flow landed in spec 008 (email now dispatches on payment confirmation). Historical.
6. **"`SendBookingConfirmationEmail::dispatch()` undefined (not a job)"**: REFUTED. The spec-007 class `implements ShouldQueue` with the `Dispatchable` trait — `::dispatch()` is valid.

---

## Prioritised fix list

1. **F1** — overbooking race (data integrity, revenue, capacity) — *STILL PRESENT*
2. **F2** — double-submit + per-call idempotency key (duplicate bookings) — *STILL PRESENT*
3. **F3** — same-key idempotency insert → 500 — *STILL PRESENT*
4. **F4** — stale price/capacity (tour loaded outside txn) — *STILL PRESENT*
5. **F5** — `canCancel()` too strict (denies valid cancellations) — *STILL PRESENT*
6. **F6** — `no_show` allowed before tour start — *STILL PRESENT*
7. **F7** — BookingConfirmation masks auth errors — *STILL PRESENT*
8. **F8** — reference collision → 500 — *STILL PRESENT*
9. **F9** — book non-operating dates — *STILL PRESENT*
10. **F10** — partner list `tour.title` empty — *STILL PRESENT*
11. **F13** — cancel omits `cancelled_at` — *STILL PRESENT*
12. **F11 / F12 / F15** — already resolved by later specs (cancel 403, create title, Suspense) — *historical*
13. **L1–L8** — contract compliance, cleanup, i18n convention — *mostly STILL PRESENT*

**Highest-impact still-present fixes:** F1 (lock the tour row), F2 (stable idempotency key + synchronous submit guard), F3/F8 (catch unique-constraint `QueryException`), F4 (reload tour inside the txn), F5/F6 (gate on tour start time, not `tour_date` midnight).