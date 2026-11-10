# Jobs

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Jobs`.

## Interface

- [GenerateTourTranslationJob.php](./GenerateTourTranslationJob.php#L24) declares `GenerateTourTranslationJob`, `GenerateTourTranslationJob::backoff()`, `GenerateTourTranslationJob::handle()`, `GenerateTourTranslationJob::failed()`.
- [SendDailySummaryJob.php](./SendDailySummaryJob.php#L17) declares `SendDailySummaryJob`, `SendDailySummaryJob::handle()`.

## Invariants

- `GenerateTourTranslationJob::handle` checks the requested source hash against the current translation state before generation and rechecks it before writing. An already ready translation for that hash takes precedence. [Initial guards](./GenerateTourTranslationJob.php#L80) and [write guards](./GenerateTourTranslationJob.php#L127).

## Side Effects

- `GenerateTourTranslationJob::handle` records successful derivative state and can queue tour indexing after commit. [State update and indexing](./GenerateTourTranslationJob.php#L165).
- `SendDailySummaryJob::handle` creates a notification and broadcasts `DailySummaryReady` for the partner. [Summary delivery](./SendDailySummaryJob.php#L56).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
