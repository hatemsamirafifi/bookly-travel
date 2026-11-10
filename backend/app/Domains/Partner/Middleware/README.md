# Middleware

<!-- EGM contract -->

## Overview

Local source in `PartnerRoleMiddleware.php`.

## Interface

- [PartnerRoleMiddleware.php](./PartnerRoleMiddleware.php#L9) declares `PartnerRoleMiddleware`, `PartnerRoleMiddleware::handle()`.

## Invariants

- `PartnerRoleMiddleware::handle` requires the `partner` token ability and a persisted partner record. Active partners with `approved` or legacy `complete` status have general access. Other statuses are subject to the route allowlist and GET-only tour access. [Authentication and onboarding guards](./PartnerRoleMiddleware.php#L11).

## Side Effects

- `PartnerRoleMiddleware::handle` adds the database partner ID to request attributes. [Request context](./PartnerRoleMiddleware.php#L25).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
