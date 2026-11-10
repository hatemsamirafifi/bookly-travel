# Views

<!-- EGM contract -->

## Overview

Groups Blade views for transactional mail, Filament administration, and printable booking vouchers.

## Subdirectories & Boundaries

- [emails](./emails/README.md) — Shared transactional email layout and message-specific Blade view groups.
- [filament](./filament/README.md) — Groups admin branding, page views, custom fields, infolist entries, and dashboard widgets for the Filament panel.
- [voucher](./voucher/README.md) — Printable HTML for a booking voucher with localized copy and an embedded verification QR image.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
