# Transformers

<!-- EGM contract -->

## Overview

List and detail projections for blog articles in the requested locale.

## Interface

- [BlogPostDetailTransformer.php](./BlogPostDetailTransformer.php#L11) declares `BlogPostDetailTransformer`, `BlogPostDetailTransformer::transform()`.
- [BlogPostTransformer.php](./BlogPostTransformer.php#L8) declares `BlogPostTransformer`, `BlogPostTransformer::transform()`, `BlogPostTransformer::formatImageUrl()`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
