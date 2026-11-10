# Media

<!-- EGM contract -->

## Overview

Media upload, asset processing, storage and folder management, and content usage tracking.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `DeleteMediaAssetAction`, `UploadMediaAssetAction`.
- [Jobs](./Jobs/README.md) — Public declarations include `ProcessMediaAssetJob`.
- [Models](./Models/README.md) — Public declarations include `MediaAsset`, `MediaFolder`, `MediaTag`.
- [Policies](./Policies/README.md) — Public declarations include `MediaAssetPolicy`.
- [Services](./Services/README.md) — Public declarations include `FileManagerService`, `MediaStorageService`, `MediaUsageTrackerService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
