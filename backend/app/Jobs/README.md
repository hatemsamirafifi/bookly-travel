# Jobs

<!-- EGM contract -->

## Overview

Queue jobs for verification mail, password reset mail, and stale guest data anonymization.

## Interface

- [AnonymizeStaleGuestIdentities.php](./AnonymizeStaleGuestIdentities.php#L14) declares `AnonymizeStaleGuestIdentities`, `AnonymizeStaleGuestIdentities::handle()`.
- [SendPasswordResetEmail.php](./SendPasswordResetEmail.php#L14) declares `SendPasswordResetEmail`, `SendPasswordResetEmail::handle()`.
- [SendVerificationEmail.php](./SendVerificationEmail.php#L14) declares `SendVerificationEmail`, `SendVerificationEmail::handle()`.

## Invariants

- `AnonymizeStaleGuestIdentities::handle` selects guests whose latest tour date is more than 24 months old and excludes future bookings, converted guests, and already anonymized guests. [AnonymizeStaleGuestIdentities.php](./AnonymizeStaleGuestIdentities.php#L21)
- `SendVerificationEmail::handle` refreshes the user and skips verified or previously sent users. [SendVerificationEmail.php](./SendVerificationEmail.php#L38)

## Side Effects

- `AnonymizeStaleGuestIdentities::handle` anonymizes selected records and logs the count. `SendPasswordResetEmail::handle` creates a reset token and submits a mailable. `SendVerificationEmail::handle` generates a signed URL, submits the verification mailable, and conditionally stamps `verification_email_sent_at`. [AnonymizeStaleGuestIdentities.php](./AnonymizeStaleGuestIdentities.php#L48) [SendPasswordResetEmail.php](./SendPasswordResetEmail.php#L38) [SendVerificationEmail.php](./SendVerificationEmail.php#L47)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
