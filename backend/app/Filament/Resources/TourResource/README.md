# TourResource

<!-- EGM contract -->

## Overview

Page group for creating, inspecting, and editing tours and their translations.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, views, and edits tours. Create and edit hooks synchronize translation data.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `TourResource::table` checks policy and state when showing publish, reject, unpublish, and archive actions. [TourResource.php](../TourResource.php#L242) [TourResource.php](../TourResource.php#L272)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
