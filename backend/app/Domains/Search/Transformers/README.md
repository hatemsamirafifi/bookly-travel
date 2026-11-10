# Transformers

<!-- EGM contract -->

## Overview

Local source in `TourCardTransformer.php`.

## Interface

- [TourCardTransformer.php](./TourCardTransformer.php#L8) declares `TourCardTransformer`, `TourCardTransformer::transform()`, `TourCardTransformer::formatImageUrl()`.

## Invariants

- `TourCardTransformer::transform` uses a current locale translation or the authored source. `resolveNextAvailableDate` only advertises a date from tomorrow onward. [Localized card](./TourCardTransformer.php#L10) and [future date guard](./TourCardTransformer.php#L42).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
