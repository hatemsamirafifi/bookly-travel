# ContentRevisionResource

<!-- EGM contract -->

## Overview

Page group for inspecting content history and restoring revisions.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists saved revisions and exposes a restore action on the detail page.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `ContentRevisionResource::canAccess` uses the `viewAny` policy and `canCreate` denies creation. [ContentRevisionResource.php](../ContentRevisionResource.php#L83)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
