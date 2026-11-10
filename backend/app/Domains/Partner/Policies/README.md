# Policies

<!-- EGM contract -->

## Overview

Local source in `TourPolicy.php`.

## Interface

- [TourPolicy.php](./TourPolicy.php#L8) declares `TourPolicy`, `TourPolicy::view()`, `TourPolicy::update()`, `TourPolicy::delete()`, `TourPolicy::archive()`.

## Invariants

- `TourPolicy` checks the tour's `partner_id` against the user ID. Its archive decision also requires the absence of confirmed bookings dated today or later. [Ownership checks](./TourPolicy.php#L10) and [archive guard](./TourPolicy.php#L25).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
