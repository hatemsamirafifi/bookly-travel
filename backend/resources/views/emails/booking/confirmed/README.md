# Confirmed

<!-- EGM contract -->

## Overview

Localized traveler confirmation email.

## Interface

- `emails.booking.confirmed.{en,es,it}` renders the localized body. Renders the booking reference, tour and date, participant count, and formatted total. [English](./en.blade.php#L10), [Spanish](./es.blade.php#L10), [Italian](./it.blade.php#L10)
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
