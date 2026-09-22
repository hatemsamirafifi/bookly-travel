# Implementation Plan: Stripe Connect and Invoicing Foundation

**Branch**: `feature/stripe-payments-connect-invoicing` | **Merged**: 2026-09-21 | **Spec**: [spec.md](./spec.md)

**Mode**: Retrospective documentation of implementation commit `76b1f1e` and merge commit `11c534e`

## Summary

Document the merged Stripe foundation: Express connected-account lifecycle,
authenticated partner endpoints, destination charges with an application fee,
automatic payment methods, hosted-invoice services, and account/invoice webhook
handling. Preserve the existing booking, Payment, ledger, refund, and webhook
idempotency behavior. Defer Bookly-owned settlement operations to Spec `019`.

## Technical Context

**Backend**: PHP 8.3+, Laravel 11, Sanctum, `stripe/stripe-php`

**Database**: PostgreSQL; five additive columns on `partners` and four Stripe
invoice-link columns on `bookings`; existing `payments`,
`financial_ledger_entries`, and `stripe_webhook_events` tables reused

**API**: Existing `/api/partner` route group and public Stripe webhook endpoint

**Testing**: Pest with `RefreshDatabase` against disposable PostgreSQL

**Configuration**: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, and
`STRIPE_COMMISSION_PERCENT` through `config/services.php`

## Constitution Check

| Principle | Result | Evidence |
|---|---|---|
| Marketplace integrity | PASS | Destination account is derived from the booked tour's partner. |
| Auditable commerce | PASS | Connected-account creation and material pull/webhook sync changes append before/after governance audit records. |
| Integer minor units | PASS | Commission configuration is parsed to integer basis points and applied to integer minor units without floating point. |
| Idempotency | PASS | PaymentIntent reuse, webhook deduplication, stable invoice write keys, and persisted Booking invoice linkage are covered. |
| API-first and authorization | PASS | Partner endpoints use `auth:sanctum` and `partner`; dedicated 401 and non-partner 404 cases cover all three routes. |
| Evidence-based quality | PASS | PostgreSQL-backed regression suites and frontend analytics tests passed on 2026-09-22. |

## Implemented Architecture

### Partner Connect boundary

- `PartnerStripeConnectController` provides status, onboarding-link, and
  dashboard-link operations.
- `StripeConnectService` owns Stripe Express account creation, account links,
  login links, and direct status synchronization.
- Partner routes remain inside the existing authenticated partner group.

### Payment boundary

- `PaymentGateway::createPaymentIntent()` accepts optional destination,
  application-fee, and metadata arguments.
- `StripeService` enables automatic payment methods and conditionally sends
  destination-charge parameters.
- `CreatePaymentIntentAction` resolves the booked tour's partner, calculates
  the configured fee, and persists trace metadata while retaining its existing
  retry and orphan-compensation behavior.

### Webhook and invoice boundary

- `ProcessStripeWebhookAction` adds `account.updated`, `invoice.paid`,
  `invoice.payment_failed`, and `invoice.finalized` handling to the existing
  signed and deduplicated pipeline.
- `StripeInvoiceService` provides customer reuse, hosted send-invoice creation,
  finalization, and voiding as an application-service foundation. No HTTP
  invoice-creation endpoint was added.

## Repository Map

```text
backend/
|-- app/Domains/Partner/Controllers/PartnerStripeConnectController.php
|-- app/Domains/Partner/Models/Partner.php
|-- app/Domains/Payment/Actions/CreatePaymentIntentAction.php
|-- app/Domains/Payment/Actions/ProcessStripeWebhookAction.php
|-- app/Domains/Payment/Contracts/PaymentGateway.php
|-- app/Domains/Payment/Services/StripeConnectService.php
|-- app/Domains/Payment/Services/StripeAccountAuditService.php
|-- app/Domains/Payment/Services/ApplicationFeeCalculator.php
|-- app/Domains/Payment/Services/StripeInvoiceService.php
|-- app/Domains/Payment/Services/StripeService.php
|-- config/services.php
|-- database/migrations/2026_09_19_100001_add_stripe_connect_columns_to_partners_table.php
|-- database/migrations/2026_09_22_100001_add_stripe_invoice_fields_to_bookings_table.php
|-- routes/api/partner.php
`-- tests/Feature/Payment/StripeConnectAndInvoicingTest.php
```

## Delivery Phases (Retrospective)

1. Add connected-account state to Partner.
2. Add Express account services and protected partner API operations.
3. Extend the payment gateway and PaymentIntent action for destination charges.
4. Add hosted-invoice services and account/invoice webhook handlers.
5. Add focused feature coverage and merge to `main`.
6. Reconstruct Spec Kit artifacts and rerun the dedicated suite against the
   disposable PostgreSQL service.
7. Close the Constitution v2 gaps with integer fee math, invoice idempotency,
   governance audit coverage, and negative authorization tests.

## Verification Gate

Completed evidence:

```text
PASS Tests\Feature\Payment\StripeConnectAndInvoicingTest
Tests: 6 passed (28 assertions)
Duration: 102.20s
Date: 2026-09-21
```

Hardening verification completed on 2026-09-22. The targeted backend run passed
31 tests and 97 assertions across the fee calculator, partner analytics, and
Stripe Connect/invoicing suites. Frontend analytics tests, TypeScript typecheck,
ESLint, Pint, and targeted PHPStan checks also passed.

The broader Payment regression run passed **59 tests and 224 assertions**; the
isolated hardened Stripe Connect/invoicing suite passed **14 tests and 61
assertions**.
