# Public

<!-- EGM contract -->

## Overview

Local source in `ReviewController.php`.

## Interface

- [ReviewController.php](./ReviewController.php#L15) declares `ReviewController`, `ReviewController::store()`, `ReviewController::update()`, `ReviewController::index()`.

## Invariants

- `ReviewController::index` includes `visible` and `flagged` reviews in the public tour review list. [Status filter](./ReviewController.php#L74).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
