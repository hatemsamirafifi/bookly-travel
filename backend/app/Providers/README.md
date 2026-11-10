# Providers

<!-- EGM contract -->

## Overview

Registers dependency bindings, model policies, named rate limiters, event listeners, and lifecycle hooks for the Laravel application.

## Interface

- [AppServiceProvider.php](./AppServiceProvider.php#L49) declares `AppServiceProvider`, `AppServiceProvider::register()`, `AppServiceProvider::boot()`.
- [EventServiceProvider.php](./EventServiceProvider.php#L36) declares `EventServiceProvider`, `EventServiceProvider::boot()`, `EventServiceProvider::shouldDiscoverEvents()`.
- [RouteServiceProvider.php](./RouteServiceProvider.php#L8) declares `RouteServiceProvider`, `RouteServiceProvider::boot()`.
- [Filament](./Filament/README.md) — Registers the Bookly admin panel, its login, discovered pages, resources and widgets, and authenticated session middleware.

## Invariants

- `EventServiceProvider::boot` defers tour projection dispatch until after commit and evaluates `Tour::shouldBeSearchable` from a fresh row on save. [EventServiceProvider.php](./EventServiceProvider.php#L107)

## Side Effects

- Tour saves enqueue indexing or removal, and deletes enqueue removal. Projection dispatch failures are logged. Saves or deletes on tours, tour translations, and categories flush the `homepage` tag when supported. [EventServiceProvider.php](./EventServiceProvider.php#L107) [EventServiceProvider.php](./EventServiceProvider.php#L127) [EventServiceProvider.php](./EventServiceProvider.php#L148)
- `EventServiceProvider::$listen` connects auth, booking email delivery failure, payment, and review events to their listeners. [EventServiceProvider.php](./EventServiceProvider.php#L44)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
