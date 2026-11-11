# Requests

<!-- EGM contract -->

## Overview

Form request validators for authentication and account credentials, administrative filters, and public catalog queries.

## Subdirectories & Boundaries

- [Admin](./Admin/README.md) — Validates administrative review, audit, and financial ledger input.
- [Auth](./Auth/README.md) — Validates traveler login, registration, password operations, and guest identity and conversion input.
- [Public](./Public/README.md) — Shared locale and filter validation for public catalog search and browse requests.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
