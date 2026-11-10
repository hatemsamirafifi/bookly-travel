# Reinstated

<!-- EGM contract -->

## Overview

Localized partner reinstatement email.

## Interface

- `emails.partner.reinstated.{en,es,it}` renders the localized body. Renders the business name, dashboard link, and support email. [English](./en.blade.php#L9), [Spanish](./es.blade.php#L9), [Italian](./it.blade.php#L9)
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
