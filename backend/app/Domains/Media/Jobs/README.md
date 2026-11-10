# Jobs

<!-- EGM contract -->

## Overview

Local source in `ProcessMediaAssetJob.php`.

## Interface

- [ProcessMediaAssetJob.php](./ProcessMediaAssetJob.php#L20) declares `ProcessMediaAssetJob`, `ProcessMediaAssetJob::handle()`.

## Invariants

- `ProcessMediaAssetJob::handle` checks that the source file exists before processing variants. [File existence check](./ProcessMediaAssetJob.php#L28).

## Side Effects

- `ProcessMediaAssetJob::handle` records processing metadata on the asset and can write a thumbnail to the asset disk. [Asset update](./ProcessMediaAssetJob.php#L36) and [thumbnail storage](./ProcessMediaAssetJob.php#L127).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
