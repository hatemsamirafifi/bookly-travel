# Public

<!-- EGM contract -->

## Overview

Local source in `StaticPageController.php`.

## Interface

- [StaticPageController.php](./StaticPageController.php#L26) declares `StaticPageController`, `StaticPageController::show()`.

## Invariants

- `StaticPageController::show` looks up a page by slug and applies the published-status filter for ordinary public requests. [Page visibility query](./StaticPageController.php#L33).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
