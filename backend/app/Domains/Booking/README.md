# Booking

<!-- EGM contract -->

## Overview

Booking creation and ownership, departure capacity, status changes, vouchers, public reference verification, and booking audit history.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `CancelBookingAction`, `CreateBookingAction`, `GetPartnerBookingsAction`.
- [Controllers](./Controllers/README.md) — HTTP entry points for public booking and verification, partner booking operations, and booking audit queries.
- [DTOs](./DTOs/README.md) — Public declarations include `BookingResponseDTO`, `CreateBookingDTO`, `VerificationResult`.
- [Jobs](./Jobs/README.md) — Public declarations include `AnonymizeExpiredBookingData`, `CompleteBookingJob`, `SendBookingConfirmationEmail`.
- [Middleware](./Middleware/README.md) — Public declarations include `RateLimitBookingMiddleware`.
- [Models](./Models/README.md) — Public declarations include `Booking`, `BookingAuditLog`.
- [Resources](./Resources/README.md) — Public declarations include `PartnerBookingResource`.
- [Services](./Services/README.md) — Public declarations include `AuditService`, `AvailabilityService`, `VoucherService`.
- [Transformers](./Transformers/README.md) — Public declarations include `VerificationTransformer`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
