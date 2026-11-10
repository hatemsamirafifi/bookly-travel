# Public

<!-- EGM contract -->

## Overview

PHP web entry point and browser-accessible assets.

## Interface

- `index.php` captures the HTTP request and passes it to Laravel. [index.php](./index.php#L16)
- Source file [index.php](./index.php).
- [js](./js/README.md) — Browser adapter for HugeRTE, Alpine and Livewire in the Laravel admin panel.

## Invariants

- Executes the maintenance bootstrap if present before loading Composer or the application. [index.php](./index.php#L8)

## Side Effects

- Includes the Composer autoloader and Laravel bootstrap before dispatching the request. [index.php](./index.php#L13)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
