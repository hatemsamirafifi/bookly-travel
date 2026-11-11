# Policies

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Policies`.

## Interface

- [BlogCategoryPolicy.php](./BlogCategoryPolicy.php#L9) declares `BlogCategoryPolicy`, `BlogCategoryPolicy::viewAny()`, `BlogCategoryPolicy::view()`, `BlogCategoryPolicy::create()`, `BlogCategoryPolicy::update()`, `BlogCategoryPolicy::delete()`, `BlogCategoryPolicy::deleteAny()`, `BlogCategoryPolicy::restore()`.
- [BlogCategoryPolicy.php](./BlogCategoryPolicy.php#L48) also exposes `BlogCategoryPolicy::forceDelete()`.
- [BlogPostPolicy.php](./BlogPostPolicy.php#L9) declares `BlogPostPolicy`, `BlogPostPolicy::viewAny()`, `BlogPostPolicy::view()`, `BlogPostPolicy::create()`, `BlogPostPolicy::update()`, `BlogPostPolicy::delete()`, `BlogPostPolicy::deleteAny()`, `BlogPostPolicy::restore()`.
- [BlogPostPolicy.php](./BlogPostPolicy.php#L48) also exposes `BlogPostPolicy::forceDelete()`.

## Invariants

- `BlogPostPolicy` and `BlogCategoryPolicy` delegate authorization to the `manage_blog` permission. [Post policy](./BlogPostPolicy.php#L13) and [category policy](./BlogCategoryPolicy.php#L13).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
