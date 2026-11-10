# Public

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Controllers/Public`.

## Interface

- [BlogCategoryController.php](./BlogCategoryController.php#L12) declares `BlogCategoryController`, `BlogCategoryController::show()`.
- [BlogPostController.php](./BlogPostController.php#L14) declares `BlogPostController`, `BlogPostController::index()`, `BlogPostController::show()`.
- [BlogPreviewController.php](./BlogPreviewController.php#L10) declares `BlogPreviewController`, `BlogPreviewController::show()`.

## Invariants

- `BlogPreviewController::show` returns 403 when the token is absent and sends successful previews with cache-prevention and crawler exclusion headers. [Preview response](./BlogPreviewController.php#L12).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
