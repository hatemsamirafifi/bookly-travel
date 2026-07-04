# Partner factory / `partner_id` test failure cascade — fix report

**Date:** 2026-07-02
**Scope:** Pre-existing test failures where `tours.partner_id` was assigned a `users.id` (or omitted) instead of a `partners.id`.
**Constraint:** Test-only changes. No production code, no migrations, no PHPUnit `<server>` fix touched.

---

## 1. Root cause

`tours.partner_id` is a NOT NULL `foreignId` constrained to `partners.id`. Migration
`2026_06_06_231248_fix_tours_partner_id_to_partners_table.php` re-pointed it from
`users.id` to `partners.id` to match the production domain model (a Tour belongs to a
`Partner`, which in turn `belongsTo` a `User` via `partners.user_id`).

`UserFactory::partner()` only sets `role = 'partner'` — it creates **no** `Partner` row.
No `PartnerFactory` existed. So tests that did:

```php
'partner_id' => User::factory()->partner()->create()->id
// or
$partner = User::factory()->partner()->create();
... 'partner_id' => $partner->id
```

were supplying a **`users.id`** that violates the FK to `partners` (78 FK violations),
and the Search tests (`TourDetailTest`, `HomepageTest`) omitted `partner_id` entirely
(NOT NULL violations).

The only correct pattern lived inline in the passing `tests/Feature/Partner/*` and
`Admin/*` tests, which manually call `Partner::create(['user_id' => $partnerUser->id, ...])`
before creating a Tour.

## 2. The fix (smallest correct, shared-layer)

Added one shared helper to `tests/Pest.php`:

```php
function makePartner(string $onboardingStatus = 'approved'): Partner
{
    $partnerUser = User::factory()->partner()->create();
    return Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => $onboardingStatus,
        'is_active' => true,
    ]);
}
```

It returns a **`Partner` model**, so:
- `makePartner()->id` is a valid `partners.id` for `tours.partner_id`.
- `makePartner()->user` gives the backing `User` for authentication.

`makeSearchableTour()` was refactored to reuse it (DRY).

### Call-site updates

The tests passed a User's `->id` — no factory hook can make a User id satisfy a FK to
`partners.id` — so call-site edits were unavoidable. Each is a one-liner:

- Replace `User::factory()->partner()->create()` with `makePartner()`.
- Replace inline `'partner_id' => User::factory()->partner()->create()->id` with
  `'partner_id' => makePartner()->id`.
- For the 3 tests that authenticate as the partner (`PartnerBookingsTest`,
  `PartnerReviewsTest`, `PartnerFinancialSummaryTest`), use `$partner->user` for
  `createToken` / `actingAs`.

### Why this is the smallest correct fix

Modifying `UserFactory::partner()` with an `afterCreating` hook to auto-create a
`Partner` was considered and **rejected**: the passing `Admin/*` and `Partner/*` tests
explicitly call `Partner::create(['user_id' => ...])` after `User::factory()->partner()->create()`,
so an auto-creating hook would double-create a Partner and violate `partners.user_id`
UNIQUE — a regression in currently-passing tests.

The shared helper avoids that: it touches no factory state used by passing tests, no
production code, no migrations, and no PHPUnit config. A grep confirmed no `$partner`
variable in the failing tests is accessed via User-only attributes (only `->id`, `->user`,
`->createToken`, `actingAs`), so converting it from a User to a Partner model is safe.

## 3. Files changed (19 — all test files)

- `tests/Pest.php` — added `makePartner()`; refactored `makeSearchableTour()` to reuse it.
- **Booking:** `AuditTrailTest`, `ConcurrencyTest`, `CreateBookingTest`, `PartnerBookingsTest`, `TravelerBookingsTest`.
- **Payment:** `LedgerImmutabilityTest`, `PartnerFinancialSummaryTest`, `PaymentCaptureTest`, `PendingExpiryTest`, `RefundTest`, `StripeDowntimeTest`.
- **Reviews:** `EditReviewTest`, `PartnerReviewsTest`, `SubmitReviewTest`, `ViewReviewsTest`.
- **Search:** `TourDetailTest`, `HomepageTest`.
- **Traveler:** `WishlistTest`.

Diffstat: 19 files changed, 131 insertions(+), 52 deletions(-).

**Not touched:** production code, migrations, `phpunit.pgsql.xml` (the `<server>` config-leak fix), `UserFactory`, `PartnerFactory` (none existed).

## 4. Before / after

Run: `docker exec bookly-backend php vendor/bin/pest --config=phpunit.pgsql.xml`

| Metric | Before | After |
|---|---|---|
| Passed | 283 | **314** |
| Failed | 106 | **75** |
| Skipped | 2 | 2 |
| `tours_partner_id_foreign` FK violations | 78 | **0** |
| `partner_id` NOT NULL violations | ~8 | **0** |
| Tests fixed (fail → pass) | — | **31** |
| Regressions (pass → fail) | — | **0** |

**Regression verification:** a set-diff of failing tests (file + test description)
before vs after returned **0** tests failing after that were not failing before, and
**31** tests failing before that now pass. Total active test count is unchanged (389),
so the +31 passed / −31 failed delta is fully accounted for by fail→pass flips with no
masked pass→fail movement. The 5 `TourSearchIndexTest` + 2 `AvailabilityResolutionRegressionTest`
(the `<server>` fix's targets) still pass. The passing `tests/Feature/Partner/*` and
`Admin/*` tests were not edited and still pass.

## 5. Remaining 75 failures — grouped by category (none partner-related)

| Category | Tests | Root cause |
|---|---|---|
| **Payment model requires `stripe_payment_intent_id`** | ~37 | `Payment::booted()` creating-hook throws `InvalidArgumentException` if `stripe_payment_intent_id` is empty; Review/Payment tests create `Payment` without it. Pre-existing, **masked by the FK before** — now that tours create successfully, tests reach `Payment::create` and surface it. Includes Reviews (ViewReviews 7, SubmitReview 7, EditReview 6, PartnerReviews 4) and Payment (PaymentCapture 4, Refund 3, PendingExpiry 2, StripeDowntime 2, PartnerFinancialSummary 2). Some Payment tests also hit `ArgumentCountError` (`PendingExpiry` job signature) and 409/503/idempotency assertions. |
| **Booking downstream** | 11 | Now reach booking business logic → assertion failures + `BadMethodCallException` (Concurrency). TravelerBookings 6, CreateBooking 2, Concurrency 2, PartnerBookings 1. Pre-existing. |
| **Search schema / missing `RefreshDatabase`** | 10 | Search tests don't `use RefreshDatabase` → cross-test state pollution → hardcoded slug collisions (`adventure`, `test-adventure`) **plus** `category_id` NOT NULL (tests pass `null` for draft/rejected/archived tours — a separate schema-incompatibility, not partner). TourDetail 7, Category 2, Homepage 1. |
| **Sitemap routing** | 4 | `/api/public/sitemap.xml` returns 404 — route not registered (`SitemapController` mid-work on branch). |
| **Auth email / rate-limit / token** | 11 | Hardcoded `traveler@example.com` `UniqueConstraint` collisions + `throttle:auth` saturation (10/min/IP shared across docker-exec runs) + token/expiry. Login 5, GuestConversion 2, Logout 1, PasswordReset 1, Registration 1, SessionManagement 1. |
| **Rate-limit** | 2 | Throttle saturation (same shared-IP issue). Search RateLimit 1, Booking RateLimit 1. |
| **Total** | **75** | |

## 6. Verification performed

- Full suite run before and after (same command).
- `php -l` syntax check clean on all 19 edited files.
- Grep confirmed 0 remaining `User::factory()->partner()->create()` in edited failing
  test files, and 0 remaining `tours_partner_id_foreign` / `partner_id` NOT NULL
  violations in the after log.
- Grep confirmed no User-only attribute access on `$partner` variables (only `->id`,
  `->user`, `->createToken`, `actingAs`) prior to editing — so the User→Partner model
  swap is safe.
- Set-diff confirmed 0 regressions.

## 7. Conclusion

The `partner_id` cascade is fully resolved. The fix is test-only, shared at the helper
layer, and matches the production domain model (`Tour` → `Partner.id`, not `User.id`).
The 75 remaining failures are independent pre-existing issues — Payment
`stripe_payment_intent_id` validation, missing `RefreshDatabase` / `category_id` in
Search tests, sitemap routing, and Auth email/rate-limit collisions — each suitable as a
separate scoped fix.