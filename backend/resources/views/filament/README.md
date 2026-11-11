# Filament

<!-- EGM contract -->

## Overview

Groups admin branding, page views, custom fields, infolist entries, and dashboard widgets for the Filament panel.

## Subdirectories & Boundaries

- [admin](./admin/README.md) — Blade fragments for Bookly Admin branding and Filament theme overrides.
- [components](./components/README.md) — Filament field view that binds a HugeRTE editor to Livewire form state.
- [infolists](./infolists/README.md) — Filament infolist view for the tour availability table.
- [pages](./pages/README.md) — Admin page views for platform settings, demo content, file management, finances, operations, storage and translation health.
- [widgets](./widgets/README.md) — Dashboard widget view for shortcuts to Filament work queues.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
