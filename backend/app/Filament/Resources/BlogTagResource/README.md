# BlogTagResource

<!-- EGM contract -->

## Overview

Page group for editing blog taxonomy tags.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, and edits blog tags with localized values.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
