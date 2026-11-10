# Admin

<!-- EGM contract -->

## Overview

Blade fragments for Bookly Admin branding and Filament theme overrides.

## Interface

- `filament.admin.logo` emits an accessible wordmark, and `filament.admin.theme` emits panel CSS variables and style overrides. [logo.blade.php](./logo.blade.php#L1), [theme.blade.php](./theme.blade.php#L1)
- Blade template [logo.blade.php](./logo.blade.php).
- Blade template [theme.blade.php](./theme.blade.php).

## Invariants

- Theme rules include visible focus and a reduced-motion override. [theme.blade.php](./theme.blade.php#L17)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
