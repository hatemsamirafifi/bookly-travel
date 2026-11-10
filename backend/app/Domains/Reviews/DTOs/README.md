# DTOs

<!-- EGM contract -->

## Overview

Response projection for an individual entry in a traveler's review history.

## Interface

- [TravelerReviewDTO.php](./TravelerReviewDTO.php#L7) declares `TravelerReviewDTO`, `TravelerReviewDTO::fromReview()`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
