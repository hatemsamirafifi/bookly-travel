# Console

<!-- EGM contract -->

## Overview

Artisan entry points for partner invitation expiry, scheduled CMS publication, blog permission backfill, and tour translation maintenance.

## Subdirectories & Boundaries

- [Commands](./Commands/README.md) — Operator commands that update scheduled content, permissions, invitation state, and tour translation data.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
