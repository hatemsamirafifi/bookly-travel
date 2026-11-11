# Pages

<!-- EGM contract -->

## Overview

Lists and displays booking records. The list adds a CSV export action.

## Interface

- [ListBookings.php](./ListBookings.php#L22) declares `ListBookings`.
- [ViewBooking.php](./ViewBooking.php#L8) declares `ViewBooking`.

## Invariants

- `ListBookings::getHeaderActions` strips blank export filters before passing them to `ExportBookingsAction`. [ListBookings.php](./ListBookings.php#L60)

## Side Effects

- `ListBookings::getHeaderActions` invokes `ExportBookingsAction::download` for the current actor and selected status and date filters. [ListBookings.php](./ListBookings.php#L60)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
