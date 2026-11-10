# Search

<!-- EGM contract -->

## Overview

Validates locale, search text, sort, pagination, and catalog filters across public search and scoped listing endpoints.

## Interface

- [CategoryToursRequest.php](./CategoryToursRequest.php#L11) declares `CategoryToursRequest`, `CategoryToursRequest::rules()`.
- [DestinationToursRequest.php](./DestinationToursRequest.php#L11) declares `DestinationToursRequest`, `DestinationToursRequest::rules()`.
- [LocaleRequest.php](./LocaleRequest.php#L12) declares `LocaleRequest`, `LocaleRequest::authorize()`, `LocaleRequest::rules()`.
- [SearchToursRequest.php](./SearchToursRequest.php#L16) declares `SearchToursRequest`, `SearchToursRequest::authorize()`, `SearchToursRequest::rules()`, `SearchToursRequest::withValidator()`.

## Invariants

- `SearchToursRequest::rules` requires a configured locale, caps pages at 1,000, and accepts dates from today through one year ahead. `withValidator` compares price bounds only when both are supplied. [SearchToursRequest.php](./SearchToursRequest.php#L23) [SearchToursRequest.php](./SearchToursRequest.php#L46)

## Side Effects

- `SearchToursRequest::prepareForValidation` applies `sanitizeQuery` to string `q` and merges it back into the request. [SearchToursRequest.php](./SearchToursRequest.php#L60)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
