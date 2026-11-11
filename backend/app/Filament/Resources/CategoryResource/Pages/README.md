# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits tour category records.

## Interface

- [CreateCategory.php](./CreateCategory.php#L8) declares `CreateCategory`.
- [EditCategory.php](./EditCategory.php#L9) declares `EditCategory`.
- [ListCategories.php](./ListCategories.php#L9) declares `ListCategories`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
