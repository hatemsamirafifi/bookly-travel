# Database

<!-- EGM contract -->

## Overview

Owns schema evolution, application settings migrations, seed data, and Eloquent model factories.

## Subdirectories & Boundaries

- [factories](./factories/README.md) — Generates Eloquent model attributes and reusable fixture states for destinations, guest identities, and users.
- [migrations](./migrations/README.md) — Laravel migrations define the relational schema, PostgreSQL-specific constraints and historical data backfills. The interface lists table creation sites.
- [seeders](./seeders/README.md) — Creates development and browser fixtures, marketplace demo content, admin access, and canonical admin roles.
- [settings](./settings/README.md) — Spatie settings migrations initialize persisted settings defaults.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
