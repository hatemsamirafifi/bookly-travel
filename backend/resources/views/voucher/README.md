# Voucher

<!-- EGM contract -->

## Overview

Printable HTML for a booking voucher with localized copy and an embedded verification QR image.

## Interface

- `voucher.booking` receives the booking, tour title and date, formatted total, optional meeting point, locale, and a QR image data URI. [Booking fields](./booking.blade.php#L120), [QR image](./booking.blade.php#L159)
- Blade template [booking.blade.php](./booking.blade.php).

## Invariants

- Unknown or missing locales fall back to English. Labels also have per-key English fallback, and the displayed verification host comes from the voucher public base URL. [Locale and host](./booking.blade.php#L78)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
