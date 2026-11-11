# Suspended

<!-- EGM contract -->

## Overview

Localized partner suspension email.

## Interface

- `emails.partner.suspended.{en,es,it}` renders the localized body. Renders the business name, suspension reason, and support email. [English](./en.blade.php#L9), [Spanish](./es.blade.php#L9), [Italian](./it.blade.php#L9)
- Blade template [en.blade.php](./en.blade.php).
- Blade template [es.blade.php](./es.blade.php).
- Blade template [it.blade.php](./it.blade.php).

## Invariants

- The supplied reason uses escaped Blade interpolation. [English](./en.blade.php#L13), [Spanish](./es.blade.php#L13), [Italian](./it.blade.php#L13)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
