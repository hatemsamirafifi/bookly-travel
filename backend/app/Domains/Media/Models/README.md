# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Media/Models`.

## Interface

- [MediaAsset.php](./MediaAsset.php#L14) declares `MediaAsset`, `MediaAsset::folder()`, `MediaAsset::uploader()`, `MediaAsset::usages()`, `MediaAsset::tags()`, `MediaAsset::scopeImages()`, `MediaAsset::fileExists()`, `MediaAsset::isUsable()`.
- [MediaAsset.php](./MediaAsset.php#L103) also exposes `MediaAsset::missingImagePlaceholderSvg()`, `MediaAsset::missingImagePlaceholderUrl()`, `MediaAsset::previewUrl()`, `MediaAsset::publicUrl()`, `MediaAsset::isImage()`.
- [MediaFolder.php](./MediaFolder.php#L11) declares `MediaFolder`, `MediaFolder::parent()`, `MediaFolder::children()`, `MediaFolder::assets()`, `MediaFolder::createdBy()`, `MediaFolder::tags()`.
- [MediaTag.php](./MediaTag.php#L8) declares `MediaTag`, `MediaTag::assets()`.
- [MediaUsage.php](./MediaUsage.php#L9) declares `MediaUsage`, `MediaUsage::asset()`, `MediaUsage::usable()`.

## Invariants

- `MediaAsset::fileExists` checks the asset's configured disk and storage key. `MediaUsage::usable` resolves the tracked content through a polymorphic relation. [File lookup](./MediaAsset.php#L87) and [usage relation](./MediaUsage.php#L30).

- Lifecycle constants in [MediaAsset.php](./MediaAsset.php#L56) include `STATUS_PENDING` → `pending`, `STATUS_READY` → `ready`, `STATUS_FAILED` → `failed`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
