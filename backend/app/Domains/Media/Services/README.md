# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Media/Services`.

## Interface

- [FileManagerService.php](./FileManagerService.php#L21) declares `FileManagerService`, `FileManagerService::createFolder()`, `FileManagerService::renameFolder()`, `FileManagerService::moveFolder()`, `FileManagerService::deleteFolder()`, `FileManagerService::moveAsset()`, `FileManagerService::copyAsset()`, `FileManagerService::restoreAsset()`.
- [FileManagerService.php](./FileManagerService.php#L242) also exposes `FileManagerService::breadcrumbs()`.
- [MediaStorageService.php](./MediaStorageService.php#L17) declares `MediaStorageService`, `MediaStorageService::validate()`.
- [MediaUsageTrackerService.php](./MediaUsageTrackerService.php#L17) declares `MediaUsageTrackerService`, `MediaUsageTrackerService::syncUsage()`, `MediaUsageTrackerService::syncFromHtml()`, `MediaUsageTrackerService::usageCount()`, `MediaUsageTrackerService::usagesFor()`.
- [StorageAnalyticsService.php](./StorageAnalyticsService.php#L15) declares `StorageAnalyticsService`, `StorageAnalyticsService::overview()`.

## Invariants

- `FileManagerService::createFolder` rejects an invalid slug or duplicate sibling slug. `FileManagerService::moveFolder` prevents moving a folder into itself or its descendants. [Create validation](./FileManagerService.php#L27) and [move validation](./FileManagerService.php#L104).

## Side Effects

- `FileManagerService::copyAsset` copies the file on its storage disk, creates a distinct asset record, and queues processing. [Asset copy](./FileManagerService.php#L175).
- `MediaUsageTrackerService::syncUsage` synchronizes usage rows for the target record's specific field. [Usage synchronization](./MediaUsageTrackerService.php#L24).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
