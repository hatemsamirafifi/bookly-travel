# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Services`.

## Interface

- [ApplicationFeeCalculator.php](./ApplicationFeeCalculator.php#L7) declares `ApplicationFeeCalculator`, `ApplicationFeeCalculator::calculate()`.
- [DeterministicPaymentGateway.php](./DeterministicPaymentGateway.php#L12) declares `DeterministicPaymentGateway`, `DeterministicPaymentGateway::createPaymentIntent()`, `DeterministicPaymentGateway::refund()`, `DeterministicPaymentGateway::cancelPaymentIntent()`.
- [LedgerService.php](./LedgerService.php#L8) declares `LedgerService`, `LedgerService::recordCharge()`, `LedgerService::hasChargeRecord()`, `LedgerService::recordRefund()`.
- [StripeAccountAuditService.php](./StripeAccountAuditService.php#L8) declares `StripeAccountAuditService`, `StripeAccountAuditService::snapshot()`, `StripeAccountAuditService::recordPartnerChange()`, `StripeAccountAuditService::recordWebhookChange()`.
- [StripeConnectService.php](./StripeConnectService.php#L9) declares `StripeConnectService`, `StripeConnectService::createExpressAccount()`, `StripeConnectService::createAccountLink()`, `StripeConnectService::createLoginLink()`, `StripeConnectService::syncAccountStatus()`.
- [StripeInvoiceService.php](./StripeInvoiceService.php#L9) declares `StripeInvoiceService`, `StripeInvoiceService::getOrCreateCustomer()`, `StripeInvoiceService::createInvoiceForBooking()`, `StripeInvoiceService::voidInvoice()`.
- [StripeService.php](./StripeService.php#L10) declares `StripeService`, `StripeService::createPaymentIntent()`, `StripeService::refund()`, `StripeService::cancelPaymentIntent()`, `StripeService::parseIntentId()`.

## Invariants

- `ApplicationFeeCalculator` accepts nonnegative amounts in minor units and commission percentages between 0 and 100 with at most two decimal places. [Amount and percentage validation](./ApplicationFeeCalculator.php#L19).
- `DeterministicPaymentGateway` can be constructed only in local or testing environments. [Gateway constructor](./DeterministicPaymentGateway.php#L14).
- `StripeConnectService::createLoginLink` requires a Stripe connected account ID before requesting a dashboard login link. [Account guard](./StripeConnectService.php#L84).
- The fee calculator uses integer basis points and half-up rounding. For nonzero commission it rejects amounts above the safe multiplication bound. [Fee calculation](./ApplicationFeeCalculator.php#L18).

## Side Effects

- `StripeConnectService` calls Stripe for account operations and persists account state on the partner record. [Account creation](./StripeConnectService.php#L47) and [account synchronization](./StripeConnectService.php#L115).
- `StripeInvoiceService` requires a traveler or guest identity email for invoicing and updates the booking with invoice data. [Invoice contact](./StripeInvoiceService.php#L76) and [booking update](./StripeInvoiceService.php#L163).
- `LedgerService::recordCharge` and `recordRefund` use `firstOrCreate` for debit and credit entries keyed by payment ID and entry type. Each returns whether it created the entry. [Debit](./LedgerService.php#L10) · [Credit](./LedgerService.php#L36).
- `StripeService` currently passes the payment-intent idempotency value in the request parameter array. Its refund method uses the same placement when a key is supplied. The installed SDK derives the `Idempotency-Key` header from request options. Local replay handling and ledger deduplication are separate from Stripe request deduplication. [Intent and refund calls](./StripeService.php#L29).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
