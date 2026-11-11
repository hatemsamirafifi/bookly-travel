# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Translation/Services`.

## Interface

- [AutoTranslationService.php](./AutoTranslationService.php#L33) declares `AutoTranslationService`, `AutoTranslationService::translatableField()`, `AutoTranslationService::isHtmlField()`, `AutoTranslationService::normalizeHash()`, `AutoTranslationService::englishValue()`, `AutoTranslationService::syncAndDispatch()`, `AutoTranslationService::snapshotLocalizedAttributes()`, `AutoTranslationService::detectManualEdits()`.
- [GeminiContentTranslator.php](./GeminiContentTranslator.php#L17) declares `GeminiContentTranslator`, `GeminiContentTranslator::translateField()`.
- [TranslationHealthCheckService.php](./TranslationHealthCheckService.php#L17) declares `TranslationHealthCheckService`, `TranslationHealthCheckService::evaluateModel()`, `TranslationHealthCheckService::platformSummary()`, `TranslationHealthCheckService::retryFailed()`, `TranslationHealthCheckService::retryAllFailures()`.
- [TranslationQualityValidator.php](./TranslationQualityValidator.php#L15) declares `TranslationQualityValidator`, `TranslationQualityValidator::validate()`.

## Invariants

- `AutoTranslationService::syncAndDispatch` protects manual translations unless overwrite is requested and marks a manual translation as needing review when its source changes. [Manual translation guard](./AutoTranslationService.php#L184).

## Side Effects

- `AutoTranslationService::syncAndDispatch` persists queued field states and dispatches `TranslateContentFieldJob` per selected field and locale. [Queue write and dispatch](./AutoTranslationService.php#L204).
- `TranslationHealthCheckService::retryFailed` selects failed states for the target record, updates them, and dispatches translation jobs. [Retry path](./TranslationHealthCheckService.php#L119).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
