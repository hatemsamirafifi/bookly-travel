# Bootstrap

<!-- EGM contract -->

## Overview

Constructs the Laravel application and configures the Filament panel, route mounts, middleware aliases and exception responses.

## Interface

- Mounts the web and console route files, `/up`, and the `/api/public`, `/api/partner`, and `/api/admin` route groups. [app.php](./app.php#L19)
- Registers the `role`, `partner`, `admin.can`, `rate.limit`, and `refresh.token` middleware aliases. [app.php](./app.php#L41)
- Source file [app.php](./app.php).
- Source file [providers.php](./providers.php).

## Invariants

- Unauthenticated API requests receive JSON `401`. Validation and explicit HTTP response exceptions retain their own renderers. Other API server errors use a generic message. [app.php](./app.php#L53), [app.php](./app.php#L69)
- Only `POST api/partner/tours/*/drafts/save` skips string trimming and empty-string conversion to preserve draft snapshot values. [app.php](./app.php#L64)

## Side Effects

- Application creation registers the Filament admin panel provider and the project application and event providers. [app.php](./app.php#L19), [providers.php](./providers.php#L6)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
