# Infolists

<!-- EGM contract -->

## Overview

Filament infolist view for the tour availability table.

## Interface

- `filament.infolists.availability-slots` takes the Filament record and invokes `AvailabilitySlotService::renderSlotsTable()`. [availability-slots.blade.php](./availability-slots.blade.php#L1)
- Blade template [availability-slots.blade.php](./availability-slots.blade.php).

## Invariants

- Renders the service-generated HTML directly only for an `App\Models\Tour` record. [availability-slots.blade.php](./availability-slots.blade.php#L5)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
