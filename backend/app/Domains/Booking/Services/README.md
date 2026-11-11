# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Booking/Services`.

## Interface

- [AuditService.php](./AuditService.php#L8) declares `AuditService`, `AuditService::log()`.
- [AvailabilityService.php](./AvailabilityService.php#L9) declares `AvailabilityService`, `AvailabilityService::checkAndReserve()`.
- [VoucherService.php](./VoucherService.php#L22) declares `VoucherService`, `VoucherService::generate()`, `VoucherService::getOrGenerate()`, `VoucherService::downloadPathForOwner()`, `VoucherService::resolveTourTitle()`, `VoucherService::contentHash()`, `VoucherService::verificationUrl()`.

## Invariants

- `AvailabilityService::checkAndReserve` requires a caller-owned transaction. It counts `pending_payment`, `confirmed`, and `completed` seats in the matching tour, date, and option pool, with a separate pool for bookings without an option. [Capacity query](./AvailabilityService.php#L11).

## Side Effects

- `AvailabilityService::checkAndReserve` locks the matching booking rows while counting participants. [Booking locks](./AvailabilityService.php#L25).
- `AuditService::log` creates a booking audit row with actor, action, previous and resulting state, and optional metadata. [Audit write](./AuditService.php#L10).
- `VoucherService` writes the rendered PDF to a temporary file before moving it to its final path. [PDF write](./VoucherService.php#L43).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
