# DestinationResource

<!-- EGM contract -->

## Overview

Page group for maintaining tour destinations.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, and edits destination records.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `DestinationResource` recognizes `manage_tours` or `manage_cms` when checking management access. [DestinationResource.php](../DestinationResource.php#L124)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
