# New booking

<!-- EGM contract -->

## Overview

Localized notification to the partner about a new booking.

## Interface

- `emails.partner.new-booking.{en,es,it}` renders the localized body. Renders the booking, tour, date, participant count, total, and traveler details. [English](./en.blade.php#L7), [Spanish](./es.blade.php#L7), [Italian](./it.blade.php#L7)
- Blade template [en.blade.php](./en.blade.php).
- Blade template [es.blade.php](./es.blade.php).
- Blade template [it.blade.php](./it.blade.php).

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
