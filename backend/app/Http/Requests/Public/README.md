# Public

<!-- EGM contract -->

## Overview

Shared locale and filter validation for public catalog search and browse requests.

## Subdirectories & Boundaries

- [Search](./Search/README.md) — Validates locale, search text, sort, pagination, and catalog filters across public search and scoped listing endpoints.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
