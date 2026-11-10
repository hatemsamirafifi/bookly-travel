# Resources

<!-- EGM contract -->

## Overview

Groups Laravel browser assets and Blade templates for the admin panel, email messages, and booking vouchers.

## Subdirectories & Boundaries

- [js](./js/README.md) — The Laravel-side Vite JavaScript entry currently contains an empty comment.
- [views](./views/README.md) — Groups Blade views for transactional mail, Filament administration, and printable booking vouchers.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
