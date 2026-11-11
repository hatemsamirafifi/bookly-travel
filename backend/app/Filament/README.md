# Filament

<!-- EGM contract -->

## Overview

Bookly administration UI with model resources and record pages, standalone operations pages, analytics widgets, and reusable form fields.

## Subdirectories & Boundaries

- [Components](./Components/README.md) — Reusable Filament rich-text editor and media selection field.
- [Pages](./Pages/README.md) — Standalone admin views for the dashboard, settings, file management, storage, demo content, translation health, operational monitoring, and finance.
- [Resources](./Resources/README.md) — Filament form, table, detail, and action definitions for administrative catalog, marketplace, content, media, finance, and governance workflows.
- [Widgets](./Widgets/README.md) — Admin dashboard summaries for platform totals, booking trends and recent bookings, rankings, and links to moderation queues.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- The admin panel discovers resources, pages, and widgets. `User::canAccessPanel` requires the `admin` role and an active status, treating a missing status as active. [AdminPanelProvider.php](../Providers/Filament/AdminPanelProvider.php#L64) [User.php](../Models/User.php#L171)
- Global dashboard analytics widgets check `view_all_analytics`. [PlatformOverviewWidget.php](./Widgets/PlatformOverviewWidget.php#L20)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
