# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits blog category records.

## Interface

- [CreateBlogCategory.php](./CreateBlogCategory.php#L8) declares `CreateBlogCategory`.
- [EditBlogCategory.php](./EditBlogCategory.php#L9) declares `EditBlogCategory`.
- [ListBlogCategories.php](./ListBlogCategories.php#L9) declares `ListBlogCategories`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
