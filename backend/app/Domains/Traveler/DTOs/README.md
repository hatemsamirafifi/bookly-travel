# DTOs

<!-- EGM contract -->

## Overview

Local source in `ProfileResponseDTO.php`.

## Interface

- [ProfileResponseDTO.php](./ProfileResponseDTO.php#L7) declares `ProfileResponseDTO`, `ProfileResponseDTO::fromUser()`.

## Invariants

- `ProfileResponseDTO::fromUser` projects an explicit list of profile fields and uses `en`, `EUR`, and `false` as defaults for missing locale, preferred currency, and marketing preference. [Profile response shape](./ProfileResponseDTO.php#L9).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
