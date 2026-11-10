# Settings

<!-- EGM contract -->

## Overview

Spatie settings migrations initialize persisted settings defaults.

## Interface

- `general` initializes the site name, support address, USD, UTC and maintenance flag. `seo` initializes metadata defaults and enables the sitemap. [General defaults](./2026_06_20_100000_create_general_settings.php#L7), [SEO defaults](./2026_06_20_100001_create_seo_settings.php#L7)
- `contact` initializes contact details and social links. `booking` initializes guest checkout, booking window, guest limit, cancellation cutoff and auto-completion timing. [Contact defaults](./2026_06_20_100002_create_contact_settings.php#L7), [Booking defaults](./2026_06_20_100003_create_booking_settings.php#L7)
- [2026_06_20_100000_create_general_settings.php](./2026_06_20_100000_create_general_settings.php#L7) declares `up()`.
- [2026_06_20_100001_create_seo_settings.php](./2026_06_20_100001_create_seo_settings.php#L7) declares `up()`.
- [2026_06_20_100002_create_contact_settings.php](./2026_06_20_100002_create_contact_settings.php#L7) declares `up()`.
- [2026_06_20_100003_create_booking_settings.php](./2026_06_20_100003_create_booking_settings.php#L7) declares `up()`.

## Invariants

- Booking settings default to guest checkout enabled, a 365-day window, 20 guests, a 48-hour cancellation cutoff and a two-day auto-complete setting. [Booking defaults](./2026_06_20_100003_create_booking_settings.php#L9)

## Side Effects

- Each `up()` registers persistent values through the settings migrator. [General migration](./2026_06_20_100000_create_general_settings.php#L9), [Booking migration](./2026_06_20_100003_create_booking_settings.php#L9)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
