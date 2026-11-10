# Controllers

<!-- EGM contract -->

## Overview

HTTP delivery of article lists, articles, category pages, and signed previews.

## Subdirectories & Boundaries

- [Public](./Public/README.md) — Public declarations include `BlogCategoryController`, `BlogPostController`, `BlogPreviewController`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
