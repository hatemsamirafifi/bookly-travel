# Contracts

<!-- EGM contract -->

## Overview

Local source in `ContentTranslatorContract.php`.

## Interface

- [ContentTranslatorContract.php](./ContentTranslatorContract.php#L11) declares `ContentTranslatorContract`, `ContentTranslatorContract::translateField()`.

## Invariants

- `ContentTranslatorContract::translateField` returns translated text, provider, and model provenance for a source field. Implementations preserve HTML structure when requested. [Translator result contract](./ContentTranslatorContract.php#L11).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
