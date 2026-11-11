# Pages

<!-- EGM contract -->

## Overview

Lists and views reviews through `ReviewResource`.

## Interface

- [ListReviews.php](./ListReviews.php#L8) declares `ListReviews`.
- [ViewReview.php](./ViewReview.php#L8) declares `ViewReview`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
