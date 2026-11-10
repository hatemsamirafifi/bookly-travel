# Controllers

<!-- EGM contract -->

## Overview

Shared controller base and adapters for public authentication and account management and administrative editor media.

## Interface

- [Controller.php](./Controller.php#L5) declares `Controller`.
- [Admin](./Admin/README.md) — Supplies selectable public image assets to the rich-text editor.
- [Public](./Public/README.md) — Public-facing authentication flows and authenticated password and personal access token management.

## Invariants

See the linked child contracts.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
