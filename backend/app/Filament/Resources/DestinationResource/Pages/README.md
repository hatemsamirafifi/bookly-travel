# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits destination records.

## Interface

- [CreateDestination.php](./CreateDestination.php#L8) declares `CreateDestination`.
- [EditDestination.php](./EditDestination.php#L10) declares `EditDestination`.
- [ListDestinations.php](./ListDestinations.php#L10) declares `ListDestinations`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
