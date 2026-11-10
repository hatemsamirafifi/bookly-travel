# AuthorProfileResource

<!-- EGM contract -->

## Overview

Page group for creating and editing blog author records.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, and edits author profiles with localized names and biographies.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
