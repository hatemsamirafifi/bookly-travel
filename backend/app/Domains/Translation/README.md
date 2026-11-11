# Translation

<!-- EGM contract -->

## Overview

Field-level translation dispatch, review and retry state, provider and model provenance, and translated content quality checks.

## Subdirectories & Boundaries

- [Contracts](./Contracts/README.md) — Public declarations include `ContentTranslatorContract`.
- [Jobs](./Jobs/README.md) — Public declarations include `TranslateContentFieldJob`.
- [Models](./Models/README.md) — Public declarations include `TranslationFieldState`.
- [Services](./Services/README.md) — Public declarations include `AutoTranslationService`, `GeminiContentTranslator`, `TranslationHealthCheckService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
