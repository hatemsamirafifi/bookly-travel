# Transformers

<!-- EGM contract -->

## Overview

Local source in `VerificationTransformer.php`.

## Interface

- [VerificationTransformer.php](./VerificationTransformer.php#L13) declares `VerificationTransformer`, `VerificationTransformer::transform()`.

## Invariants

- `VerificationTransformer::transform` emits a `data` envelope from the explicit verification DTO fields and omits each optional timestamp when null. [Public response shape](./VerificationTransformer.php#L15).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
