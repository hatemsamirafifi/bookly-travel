# Pages

<!-- EGM contract -->

## Overview

Lists tours and displays the availability details of the selected tour.

## Interface

- [ListAvailability.php](./ListAvailability.php#L8) declares `ListAvailability`.
- [ViewAvailability.php](./ViewAvailability.php#L8) declares `ViewAvailability`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
