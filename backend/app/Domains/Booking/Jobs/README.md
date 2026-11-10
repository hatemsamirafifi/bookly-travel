# Jobs

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Booking/Jobs`.

## Interface

- [AnonymizeExpiredBookingData.php](./AnonymizeExpiredBookingData.php#L26) declares `AnonymizeExpiredBookingData`, `AnonymizeExpiredBookingData::handle()`.
- [CompleteBookingJob.php](./CompleteBookingJob.php#L9) declares `CompleteBookingJob`, `CompleteBookingJob::handle()`.
- [SendBookingConfirmationEmail.php](./SendBookingConfirmationEmail.php#L33) declares `SendBookingConfirmationEmail`, `SendBookingConfirmationEmail::handle()`, `SendBookingConfirmationEmail::failed()`.

## Invariants

None recorded in this contract.

## Side Effects

- `SendBookingConfirmationEmail::handle` acquires a per-booking cache lock, sends mail, and saves `confirmation_email_sent_at`. [Confirmation work](./SendBookingConfirmationEmail.php#L51).
- `CompleteBookingJob::handle` updates confirmed bookings for past tour dates to completed. [Completion sweep](./CompleteBookingJob.php#L14).
- `AnonymizeExpiredBookingData::handle` selects bookings with a tour date older than 90 days and no anonymization marker. For each, it clears the traveler and guest identity links, redacts any cancellation reason, and writes a `data_anonymized` audit entry. [Retention query](./AnonymizeExpiredBookingData.php#L36) and [anonymization transaction](./AnonymizeExpiredBookingData.php#L54).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
