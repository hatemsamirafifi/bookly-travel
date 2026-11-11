# Contracts

<!-- EGM contract -->

## Overview

Local source in `PaymentGateway.php`.

## Interface

- [PaymentGateway.php](./PaymentGateway.php#L5) declares `PaymentGateway`, `PaymentGateway::createPaymentIntent()`, `PaymentGateway::refund()`, `PaymentGateway::cancelPaymentIntent()`.

## Invariants

- `PaymentGateway::createPaymentIntent` requires an integer amount, currency, and idempotency key. Destination account, application fee, and metadata are optional. `refund` supports an optional idempotency key. [Gateway signatures](./PaymentGateway.php#L5).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
