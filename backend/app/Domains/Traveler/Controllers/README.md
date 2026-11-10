# Controllers

<!-- EGM contract -->

## Overview

HTTP controllers for traveler profile, wishlist, and review history.

## Subdirectories & Boundaries

- [Public](./Public/README.md) — Traveler-facing profile endpoints, wishlist list/add/remove/status/merge operations, and the traveler's review history.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
