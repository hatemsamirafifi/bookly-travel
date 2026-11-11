# DTOs

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Booking/DTOs`.

## Interface

- [BookingResponseDTO.php](./BookingResponseDTO.php#L8) declares `BookingResponseDTO`, `BookingResponseDTO::fromBooking()`.
- [CreateBookingDTO.php](./CreateBookingDTO.php#L5) declares `CreateBookingDTO`.
- [VerificationResult.php](./VerificationResult.php#L14) declares `VerificationResult`, `VerificationResult::fromBooking()`.

## Invariants

- `VerificationResult::fromBooking` selects an explicit set of public verification fields. The DTO contains the reference, status, tour title and date, participant count, and optional creation and voucher timestamps. [Verification projection](./VerificationResult.php#L14).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
