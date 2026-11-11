# Widgets

<!-- EGM contract -->

## Overview

Dashboard widget view for shortcuts to Filament work queues.

## Interface

- `filament.widgets.queue-shortcuts-widget` renders each entry from `queues()` using its key, URL, label, color and count. [queue-shortcuts-widget.blade.php](./queue-shortcuts-widget.blade.php#L2), [queue-shortcuts-widget.blade.php](./queue-shortcuts-widget.blade.php#L7)
- Blade template [queue-shortcuts-widget.blade.php](./queue-shortcuts-widget.blade.php).

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
