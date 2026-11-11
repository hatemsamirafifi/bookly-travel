# Http

<!-- EGM contract -->

## Overview

Shared Laravel HTTP boundary for public authentication and account operations, admin rich-text media, request validation, middleware, and JSON response shaping.

## Subdirectories & Boundaries

- [Controllers](./Controllers/README.md) — Shared controller base and adapters for public authentication and account management and administrative editor media.
- [Middleware](./Middleware/README.md) — Per-request role, admin permission, access token expiry, and public search throttle controls.
- [Requests](./Requests/README.md) — Form request validators for authentication and account credentials, administrative filters, and public catalog queries.
- [Resources](./Resources/README.md) — Field allowlists for user and guest identities and public, partner, and admin review responses.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `AdminPermissionMiddleware::handle` delegates permission checks to `AdminAuthorizationService`. [AdminPermissionMiddleware.php](./Middleware/AdminPermissionMiddleware.php#L14)
- `SessionController::destroy` resolves the requested token through the current user's relationship. [SessionController.php](./Controllers/Public/Account/SessionController.php#L45)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
