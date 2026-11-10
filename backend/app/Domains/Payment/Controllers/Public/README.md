# Public

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Controllers/Public`.

## Interface

- [DeterministicPaymentController.php](./DeterministicPaymentController.php#L9) declares `DeterministicPaymentController`, `DeterministicPaymentController::__invoke()`.
- [StripeWebhookController.php](./StripeWebhookController.php#L11) declares `StripeWebhookController`, `StripeWebhookController::__invoke()`.

## Invariants

- `StripeWebhookController` requires a `Stripe-Signature` header and converts malformed payloads or invalid signatures to bad-request errors. [Webhook ingress](./StripeWebhookController.php#L14).
- `DeterministicPaymentController` gates access on the configured driver and the local or testing environment. [Deterministic endpoint guard](./DeterministicPaymentController.php#L13).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
