# Pages

<!-- EGM contract -->

## Overview

Admin page views for platform settings, demo content, file management, finances, operations, storage and translation health.

## Interface

- File management reads breadcrumbs, active assets and trash from the backing page. Storage analytics reads the overview and formats byte counts with `humanSize()`. [File manager](./file-manager-page.blade.php#L3), [Storage analytics](./storage-analytics-page.blade.php#L3)
- Financial reconciliation uses summary and report data. Operations and translation health use their page's snapshot and summary, respectively. [Financial overview](./financial-overview-page.blade.php#L14), [Operations](./operational-monitoring-page.blade.php#L3), [Translation health](./translation-health-dashboard.blade.php#L3)
- Demo content renders tours, posts and category collections passed to the view. [Demo content](./demo-content-manager.blade.php#L51)
- Blade template [demo-content-manager.blade.php](./demo-content-manager.blade.php).
- Blade template [file-manager-page.blade.php](./file-manager-page.blade.php).
- Blade template [financial-overview-page.blade.php](./financial-overview-page.blade.php).
- Blade template [operational-monitoring-page.blade.php](./operational-monitoring-page.blade.php).
- Blade template [settings.blade.php](./settings.blade.php).
- Blade template [storage-analytics-page.blade.php](./storage-analytics-page.blade.php).
- Blade template [translation-health-dashboard.blade.php](./translation-health-dashboard.blade.php).

## Invariants

None recorded in this contract.

## Side Effects

- The settings form wires submission to the backing component's `save` action. [settings.blade.php](./settings.blade.php#L2)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
