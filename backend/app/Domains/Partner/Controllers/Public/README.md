# Public

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Controllers/Public`.

## Interface

- [PartnerInvitationController.php](./PartnerInvitationController.php#L11) declares `PartnerInvitationController`, `PartnerInvitationController::show()`, `PartnerInvitationController::complete()`.
- [PartnerRegistrationController.php](./PartnerRegistrationController.php#L17) declares `PartnerRegistrationController`, `PartnerRegistrationController::__invoke()`.

## Invariants

- `PartnerInvitationController::show` requires an existing valid invitation before returning invitation data. [Invitation lookup](./PartnerInvitationController.php#L13).

## Side Effects

- `PartnerRegistrationController::__invoke` creates the self-registration records in a transaction. [Registration transaction](./PartnerRegistrationController.php#L22).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
