# Research: Stripe Connect and Invoicing Foundation

**Feature**: `017-stripe-connect-invoicing`

**Mode**: Retrospective — decisions verified against the merged implementation

## 1. Connected-account type

**Decision**: Use Stripe Express accounts.

**Evidence**: `StripeConnectService::createExpressAccount()` creates accounts
with `type = express`, requests card-payment and transfer capabilities, and
stores the Stripe account identifier and capability flags on Partner.

**Why it fits**: Stripe hosts onboarding and the Express Dashboard while Bookly
retains the marketplace relationship and server-side payment flow.

## 2. Charge routing

**Decision**: Use destination charges when the tour partner has both a stored
Stripe account identifier and charges enabled.

**Evidence**: `CreatePaymentIntentAction` passes the connected account and
application fee to `PaymentGateway`; `StripeService` maps them to
`transfer_data.destination` and `application_fee_amount`.

**Constraint**: This routing already moves the booking funds toward the
connected account. Spec `019` must reconcile this model and must not introduce a
second transfer for the same booking.

## 3. Application-fee calculation

**Decision**: Calculate the platform fee from the integer booking total using
`services.stripe.platform_commission_percent` with a decimal-string `15.00`
default converted to integer basis points.

**Evidence**: `ApplicationFeeCalculator` validates at most two decimal places,
converts to basis points, and performs half-up integer arithmetic. Tests cover
`3000` from `20000` at `15%`, a decimal percentage, the half boundary, zero,
invalid configuration, and negative input.

## 4. Automatic payment methods

**Decision**: Enable Stripe automatic payment methods on new PaymentIntents.

**Evidence**: `StripeService::createPaymentIntent()` sends
`automatic_payment_methods.enabled = true`. The existing booking payment form
continues confirming the returned client secret; payment-method availability
remains controlled by Stripe account and transaction eligibility.

## 5. Account state synchronization

**Decision**: Support both pull and push synchronization.

- Pull: the partner status endpoint retrieves the current account from Stripe.
- Push: `account.updated` updates the matching Partner through the signed
  webhook pipeline.

**Derived state**: `stripe_onboarding_completed` is true when Stripe reports
both `details_submitted` and `payouts_enabled`.

**Audit decision**: Material changes from partner requests or
`account.updated` append governance audit rows. Webhooks use a `system` actor
and retain the Stripe event identifier; partner requests use the Partner actor
and retain the requesting user identifier in metadata.

## 6. Invoice boundary

**Decision**: Deliver an internal hosted-invoice service and invoice webhook
handling, not a complete invoice product surface.

**Evidence**: `StripeInvoiceService` can find/create customers, add one booking
line item, create a `send_invoice` invoice, finalize it, and void it. No route or
controller invokes invoice creation in the merged code. Invoice webhooks can
confirm a referenced booking and record its successful payment and ledger
effect.

**Idempotency decision**: Persist invoice linkage on Booking, use stable keys
for customer, line-item, invoice, finalization, and void writes, and retrieve an
existing linked invoice on retry. Persisting the draft identifier before
finalization makes a failed finalization safely resumable.

## 7. Persistence boundary

**Decision**: Add only Stripe connected-account fields to `partners` and reuse
the existing Payment, ledger, booking, and webhook-event models.

**Rejected for this spec**: `partner_earnings`, payout batches, payout-history,
hold/release, and reconciliation tables. Those need a source-of-truth decision
against Stripe balance transactions and payouts in Spec `019`.

## 8. Verification result

The dedicated suite initially could not resolve `bookly-test-postgres`. After
starting the disposable service from `docker-compose.test.yml`, the suite
passed all 6 tests and 28 assertions. This distinguishes an environment failure
from a product-code failure.

The original rerun proves the six merged scenarios. The 2026-09-22 hardening
run additionally proves negative endpoint authorization, integer-only
commission math, governance-audit coverage, and idempotent invoice retry.
