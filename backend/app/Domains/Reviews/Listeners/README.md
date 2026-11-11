# Listeners

<!-- EGM contract -->

## Overview

Local source in `UpdateTourAggregateRating.php`.

## Interface

- [UpdateTourAggregateRating.php](./UpdateTourAggregateRating.php#L10) declares `UpdateTourAggregateRating`, `UpdateTourAggregateRating::handle()`.

## Invariants

- `UpdateTourAggregateRating::handle` includes both visible and flagged reviews when aggregating tour ratings. [Aggregate query](./UpdateTourAggregateRating.php#L12).

## Side Effects

- `UpdateTourAggregateRating::handle` persists the calculated average and count on the tour. [Aggregate update](./UpdateTourAggregateRating.php#L24).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
