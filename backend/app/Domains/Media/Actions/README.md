# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Media/Actions`.

## Interface

- [DeleteMediaAssetAction.php](./DeleteMediaAssetAction.php#L22) declares `DeleteMediaAssetAction`, `DeleteMediaAssetAction::perform()`.
- [UploadMediaAssetAction.php](./UploadMediaAssetAction.php#L15) declares `UploadMediaAssetAction`, `UploadMediaAssetAction::perform()`.

## Invariants

- `DeleteMediaAssetAction::perform` blocks deletion of referenced assets unless forced, and forced deletion requires an actor with `delete_media` or `manage_media`. [Deletion guards](./DeleteMediaAssetAction.php#L33).

## Side Effects

- `UploadMediaAssetAction::perform` writes the upload to the storage disk, stores asset metadata including the SHA-256 checksum, and queues asset processing. [Upload effects](./UploadMediaAssetAction.php#L43).
- `DeleteMediaAssetAction::perform` removes tracked usages when forced, soft-deletes or permanently deletes the asset row, and records the references and action in governance audit. [Deletion effects](./DeleteMediaAssetAction.php#L59).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
