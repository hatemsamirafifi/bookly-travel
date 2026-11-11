# Controllers

<!-- EGM contract -->

## Overview

Public review submission, editing, and tour review lists, alongside administrative moderation.

## Subdirectories & Boundaries

- [Admin](./Admin/README.md) — Administrative review list and hide/reinstate endpoints.
- [Public](./Public/README.md) — Public declarations include `ReviewController`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
