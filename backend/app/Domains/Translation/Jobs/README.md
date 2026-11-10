# Jobs

<!-- EGM contract -->

## Overview

Local source in `TranslateContentFieldJob.php`.

## Interface

- [TranslateContentFieldJob.php](./TranslateContentFieldJob.php#L33) declares `TranslateContentFieldJob`, `TranslateContentFieldJob::handle()`.

## Invariants

- `TranslateContentFieldJob::handle` skips missing, manually edited, and needs-review field states. It fails tour fields managed by the tour generation pipeline and requires a nonempty English source value. [Job state guard](./TranslateContentFieldJob.php#L54) and [source checks](./TranslateContentFieldJob.php#L81).

## Side Effects

- `TranslateContentFieldJob::handle` updates status and attempt information, calls the configured translator, then saves the translation result and field state in a transaction. [Job processing](./TranslateContentFieldJob.php#L69) and [result write](./TranslateContentFieldJob.php#L120).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
