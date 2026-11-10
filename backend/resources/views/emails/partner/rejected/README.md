# Rejected

<!-- EGM contract -->

## Overview

Localized partner application rejection email.

## Interface

- `emails.partner.rejected.{en,es,it}` renders the localized body. Renders the business name, rejection reason, and support email. [English](./en.blade.php#L5), [Spanish](./es.blade.php#L5), [Italian](./it.blade.php#L5)
- Blade template [en.blade.php](./en.blade.php).
- Blade template [es.blade.php](./es.blade.php).
- Blade template [it.blade.php](./it.blade.php).

## Invariants

- The supplied reason uses escaped Blade interpolation. [English](./en.blade.php#L9), [Spanish](./es.blade.php#L9), [Italian](./it.blade.php#L9)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
