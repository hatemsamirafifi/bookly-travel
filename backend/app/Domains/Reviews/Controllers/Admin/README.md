# Admin

<!-- EGM contract -->

## Overview

Administrative review list and hide/reinstate endpoints.

## Interface

- [AdminReviewController.php](./AdminReviewController.php#L15) declares `AdminReviewController`, `AdminReviewController::index()`, `AdminReviewController::hide()`, `AdminReviewController::reinstate()`.

## Invariants

None recorded in this contract.

## Side Effects

- `AdminReviewController::hide` and `AdminReviewController::reinstate` invoke the respective moderation action with the authenticated actor and validated reason. [Moderation endpoints](./AdminReviewController.php#L59).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
