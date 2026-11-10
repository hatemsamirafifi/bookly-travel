# Pages

<!-- EGM contract -->

## Overview

Lists media assets and exposes create and edit forms. The create handler forwards staged files to the domain uploader.

## Interface

- [CreateMediaAsset.php](./CreateMediaAsset.php#L16) declares `CreateMediaAsset`.
- [EditMediaAsset.php](./EditMediaAsset.php#L9) declares `EditMediaAsset`.
- [ListMediaAssets.php](./ListMediaAssets.php#L9) declares `ListMediaAssets`.

## Invariants

None recorded in this contract.

## Side Effects

- `CreateMediaAsset::storeFromPendingPath` materializes a temporary upload from the media disk, invokes `UploadMediaAssetAction::perform`, and cleans up the temporary file and staged disk path in `finally`. `handleRecordCreation` synchronizes selected tags on returned assets. [CreateMediaAsset.php](./CreateMediaAsset.php#L25) [CreateMediaAsset.php](./CreateMediaAsset.php#L53)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
