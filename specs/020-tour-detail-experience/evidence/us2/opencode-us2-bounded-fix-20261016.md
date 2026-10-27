# US2 bounded E2E fix — booking.spec.ts only

Scope: `frontend/tests/e2e/booking.spec.ts`. No backend, product, fixture, or
other spec changes. Backend reference: `Booking::canCancel` requires
`STATUS_CONFIRMED`; `CancelBookingAction` rejects non-confirmed with 422, so a
`pending_payment` booking cannot be cancelled.

## Changed assertions and why

1. `cancelOwnedBooking` — added `throw new Error(description)` after the
   existing `owned-cleanup-failed` annotation.
   Why: annotation alone leaves a failed cleanup green; the throw makes an
   uncancelled owned reference a visible test failure. The annotation still
   carries the exact owned reference for root handling. Token stays in a local
   const and is never logged; only the exact owned reference is touched.

2. `completes checkout, payment, confirmation, and an idempotent replay`
   - Replaced post-payment URL-derived `ownedReference` with a synchronous
     `page.waitForResponse(POST /api/public/bookings && ok)` + `await
     createResponse.json()` captured immediately after the Confirm click and
     before `Complete test payment`.
   - Changed `expect(originalReference).toBeTruthy()` to
     `expect(originalReference).toBe(ownedReference)`.
   Why: removes the fire-and-forget `response.json()` race and guarantees the
   owned reference exists before the payment action, so a payment failure still
   leaves the exact owned pending reference for bounded expiration reporting.

3. `expired session recovers through login with the full selection and no
   duplicate submit`
   - Removed the `page.on('response')` fire-and-forget `response.json()` tap.
   - Captures `ownedReference` synchronously via `page.waitForResponse` on the
     second (real) POST after the explicit resubmit, before the payment step.
   - Added `Complete test payment` click plus confirmation-URL, `Booking
     Confirmed!` heading, and `ref === ownedReference` assertions.
   Why: the journey previously ended at `pending_payment`, whose cancellation
   cannot succeed under the confirmed-only gate. Completing the existing
   deterministic payment on the same reference moves the owned booking to
   `confirmed` so the exact-owner `finally` cleanup can succeed. Preserved:
   `sessionExpired` returnUrl, no-auto-submit (`postCount === 1` after login
   return), and explicit-resubmit proof (`postCount === 2`).

4. `shows error for sold out tour` replaced by two tests:
   - `surfaces the sold-out message when the server reports a conflict`:
     derives an offered future date via `futureBookableDate(request)`, injects
     a controlled 409 at the `**/api/public/bookings` CREATE boundary, clicks
     Confirm, asserts `/sold out/i`.
   - `blocks an unoffered date locally without posting`: routes CREATE with a
     counter, opens the hardcoded unoffered `SOLD_OUT_DATE`, asserts Confirm
     is disabled, `Invalid booking details` visible, `postCount === 0`.
   Why: the correct product guard disables submit for unoffered dates before
   any POST, so the old hardcoded-date-click-Confirm flow could not reach the
   server 409. The split keeps a meaningful server-409 assertion on a real
   offered date and covers local blocking as separate explicit behavior. No
   product guard was weakened.

5. `futureBookableDate` comment — removed the stale "every confirmed booking
   permanently consumes fixture capacity" claim; states owned bookings are
   cancelled in `finally`, releasing held capacity.
   Why: the old comment contradicted owned cleanup; capacity is released on
   cancel. No seeded account/tour edits.

## Test-guard self-check

- Route mocks only at the network boundary (`**/api/public/bookings`,
  traveler booking detail); assertions target visible behavior and the
  idempotency/no-duplicate-submit contract.
- The two sold-out tests are distinct scenarios (server conflict vs local
  guard), not parametrize variants.
- No financial rows deleted; no other booking mutated; pending leftovers are
  reported by exact reference for bounded expiration.
