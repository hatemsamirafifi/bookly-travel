# Tasks: Stripe Connect and Invoicing Foundation

**Mode**: Retrospective traceability for implementation `76b1f1e`, merged by
`11c534e`. Checked tasks map to files or evidence already present on `main`.

## Phase 1 - Partner connected-account foundation

- [x] T001 Add Stripe connected-account fields through
  `backend/database/migrations/2026_09_19_100001_add_stripe_connect_columns_to_partners_table.php`.
- [x] T002 Add Partner fillable/cast state and `isPayoutReady()` in
  `backend/app/Domains/Partner/Models/Partner.php`.
- [x] T003 Implement Express account creation, account links, dashboard login
  links, and direct status synchronization in
  `backend/app/Domains/Payment/Services/StripeConnectService.php`.
- [x] T004 Add the status, onboarding, and dashboard operations in
  `backend/app/Domains/Partner/Controllers/PartnerStripeConnectController.php`.
- [x] T005 Register all three operations under the authenticated partner route
  group in `backend/routes/api/partner.php`.

## Phase 2 - Destination-charge payment flow

- [x] T006 Extend `PaymentGateway` and its implementations with optional
  destination, application-fee, and metadata parameters.
- [x] T007 Enable automatic payment methods and conditional destination-charge
  parameters in `backend/app/Domains/Payment/Services/StripeService.php`.
- [x] T008 Resolve the tour partner, calculate the configured fee, and persist
  trace metadata in
  `backend/app/Domains/Payment/Actions/CreatePaymentIntentAction.php`.
- [x] T009 Preserve existing idempotent reuse and orphan-intent compensation in
  the PaymentIntent action.
- [x] T010 Add `STRIPE_COMMISSION_PERCENT` configuration with a `15.00` default
  in `backend/config/services.php`.

## Phase 3 - Invoice and webhook foundation

- [x] T011 Add customer reuse, hosted send-invoice creation/finalization, and
  voiding in `backend/app/Domains/Payment/Services/StripeInvoiceService.php`.
- [x] T012 Synchronize partner flags for `account.updated` in
  `backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php`.
- [x] T013 Handle `invoice.paid`, `invoice.payment_failed`, and
  `invoice.finalized` through the existing signed and deduplicated webhook
  action.
- [x] T014 Preserve existing booking confirmation, Payment, ledger, and domain
  event behavior for a paid invoice.

## Phase 4 - Verification and retrospective artifacts

- [x] T015 Add the six-scenario feature suite in
  `backend/tests/Feature/Payment/StripeConnectAndInvoicingTest.php`.
- [x] T016 Start the disposable PostgreSQL test service and rerun the dedicated
  suite: 6 tests and 28 assertions passed on 2026-09-21.
- [x] T017 Add dedicated negative authorization cases for unauthenticated and
  non-partner requests to all three partner Stripe endpoints.
- [x] T018 Replace floating-point commission calculation with integer basis
  points or equivalent rational math and add rounding-boundary tests.
- [x] T019 Add governance audit records for connected-account creation and
  material charges/payouts/details/onboarding state changes.
- [x] T020 Make `StripeInvoiceService::createInvoiceForBooking()` idempotent
  using a stable booking-scoped key and persisted invoice linkage; add retry and
  duplicate-prevention tests.
- [x] T021 Create the retrospective `spec.md`, `plan.md`, `research.md`,
  `data-model.md`, `quickstart.md`, API contract, and this task trace.

## Phase 5 - Explicitly deferred to Spec 019

- [ ] T022 Design Bookly-owned earnings and settlement records after selecting
  the Stripe balance-transaction/payout reconciliation source of truth.
- [ ] T023 Add settlement history and administrative exception workflows
  without creating a duplicate transfer for destination-charge bookings.

> T022 and T023 are not incomplete Spec `017` implementation tasks. They are
> cross-spec dependency markers owned by Spec `019`.
