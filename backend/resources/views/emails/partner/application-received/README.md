# Application received

<!-- EGM contract -->

## Overview

Localized partner application receipt email.

## Interface

- `emails.partner.application-received.{en,es,it}` renders the localized body. Renders the business and contact names and links to onboarding. [English](./en.blade.php#L4), [Spanish](./es.blade.php#L4), [Italian](./it.blade.php#L4)
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
