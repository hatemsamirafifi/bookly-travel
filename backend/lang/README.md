# Lang

<!-- EGM contract -->

## Overview

Groups backend translation catalogs for English, Spanish, and Italian.

## Subdirectories & Boundaries

- [en](./en/README.md) — English translation catalogs for Laravel auth email, homepage rails, search and homepage SEO.
- [es](./es/README.md) — Spanish translation catalogs for Laravel auth email, homepage rails, search and homepage SEO.
- [it](./it/README.md) — Italian translation catalogs for Laravel auth email, homepage rails, search and homepage SEO.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
