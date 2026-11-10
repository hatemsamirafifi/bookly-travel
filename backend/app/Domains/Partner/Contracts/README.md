# Contracts

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Contracts`.

## Interface

- [SourceAwareTourContentTranslator.php](./SourceAwareTourContentTranslator.php#L14) declares `SourceAwareTourContentTranslator`, `SourceAwareTourContentTranslator::translateFrom()`.
- [TourContentTranslator.php](./TourContentTranslator.php#L5) declares `TourContentTranslator`, `TourContentTranslator::translate()`.

## Invariants

- `TourContentTranslator::translate` translates a field-path map. Providers supporting non-English authored sources expose `SourceAwareTourContentTranslator::translateFrom` with separate source and target locale arguments. [Basic contract](./TourContentTranslator.php#L11) and [source-aware contract](./SourceAwareTourContentTranslator.php#L14).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
