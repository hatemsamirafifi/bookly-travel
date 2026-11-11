# Filament

<!-- EGM contract -->

## Overview

Registers the Bookly admin panel, its login, discovered pages, resources and widgets, and authenticated session middleware.

## Interface

- [AdminPanelProvider.php](./AdminPanelProvider.php#L23) declares `AdminPanelProvider`, `AdminPanelProvider::panel()`.

## Invariants

- `AdminPanelProvider::panel` sets ID and path `admin`, applies session and CSRF middleware, and attaches Filament's `Authenticate` middleware. [AdminPanelProvider.php](./AdminPanelProvider.php#L25) [AdminPanelProvider.php](./AdminPanelProvider.php#L73)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
