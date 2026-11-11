# Settings

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Settings`.

## Interface

- [BookingSettings.php](./BookingSettings.php#L7) declares `BookingSettings`, `BookingSettings::group()`.
- [ContactSettings.php](./ContactSettings.php#L7) declares `ContactSettings`, `ContactSettings::group()`.
- [GeneralSettings.php](./GeneralSettings.php#L7) declares `GeneralSettings`, `GeneralSettings::group()`.
- [SeoSettings.php](./SeoSettings.php#L7) declares `SeoSettings`, `SeoSettings::group()`.

## Invariants

- The settings classes map to the `general`, `booking`, `contact`, and `seo` groups. [General](./GeneralSettings.php#L21), [booking](./BookingSettings.php#L21), [contact](./ContactSettings.php#L19), and [SEO](./SeoSettings.php#L21).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
