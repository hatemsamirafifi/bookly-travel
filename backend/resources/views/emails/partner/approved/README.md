# Approved

<!-- EGM contract -->

## Overview

Localized partner approval email.

## Interface

- `emails.partner.approved.{en,es,it}` renders the localized body. Renders the business name and links to the partner dashboard. [English](./en.blade.php#L4), [Spanish](./es.blade.php#L4), [Italian](./it.blade.php#L4)
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
