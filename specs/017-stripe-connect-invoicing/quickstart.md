# Quickstart: Verify Spec 017

Run from the repository root.

## 1. Start the disposable PostgreSQL test service

```powershell
docker compose -f docker-compose.test.yml up -d test-postgres
docker exec bookly-test-postgres pg_isready -U bookly_test -d bookly_test
```

Expected readiness output includes `accepting connections`.

## 2. Run the dedicated feature suite

```powershell
docker exec bookly-backend php artisan test tests/Feature/Payment/StripeConnectAndInvoicingTest.php
```

Original baseline result on 2026-09-21:

```text
PASS Tests\Feature\Payment\StripeConnectAndInvoicingTest
Tests: 6 passed (28 assertions)
```

Current hardened result on 2026-09-22:

```text
PASS Tests\Feature\Payment\StripeConnectAndInvoicingTest
Tests: 14 passed (61 assertions)
```

The current suite also covers invoice retry reuse, idempotency headers,
governance audit creation, and the negative authorization matrix. It uses a
recording Stripe HTTP boundary and the disposable PostgreSQL database; it does
not require live Stripe credentials or make real charges.

## 3. Inspect registered partner routes

```powershell
docker exec bookly-backend php artisan route:list --path=api/partner/stripe
```

Confirm that status, onboarding, and dashboard routes are present under the
partner route group. The source contract is documented in
`contracts/stripe-connect-partner-api.md`.

## 4. Verify hardening controls

```powershell
docker exec bookly-backend php artisan test `
  tests/Unit/Payment/ApplicationFeeCalculatorTest.php `
  tests/Feature/Partner/AnalyticsTest.php `
  tests/Feature/Payment/StripeConnectAndInvoicingTest.php
```

The 2026-09-22 targeted run passed **31 tests and 97 assertions**. It verifies:

- unauthenticated 401 and authenticated-traveler 404 responses for all three
  Stripe partner endpoints;
- integer basis-point commission arithmetic and rounding boundaries;
- governance audit records for connected-account creation and webhook sync;
- stable idempotency keys and reuse of the persisted Booking invoice on retry.

## 5. Optional cleanup

The test database is disposable. Stop it when it is no longer needed:

```powershell
docker compose -f docker-compose.test.yml stop test-postgres
```
