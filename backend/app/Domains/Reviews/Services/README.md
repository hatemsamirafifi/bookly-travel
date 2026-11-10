# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Reviews/Services`.

## Interface

- [ProfanityFilterService.php](./ProfanityFilterService.php#L8) declares `ProfanityFilterService`, `ProfanityFilterService::scan()`.
- [ReviewValidationService.php](./ReviewValidationService.php#L11) declares `ReviewValidationService`, `ReviewValidationService::validate()`.

## Invariants

- `ReviewValidationService::validate` requires a completed booking owned by the traveler, a tour date within the 30-day review window, no existing review for that booking, and a successful payment record. [Review eligibility](./ReviewValidationService.php#L16).

## Side Effects

- Writes application logs via `Log::warning()` in [ProfanityFilterService.php](./ProfanityFilterService.php#L38).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
