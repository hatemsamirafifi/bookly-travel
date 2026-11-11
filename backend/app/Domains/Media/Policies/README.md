# Policies

<!-- EGM contract -->

## Overview

Local source in `MediaAssetPolicy.php`.

## Interface

- [MediaAssetPolicy.php](./MediaAssetPolicy.php#L9) declares `MediaAssetPolicy`, `MediaAssetPolicy::viewAny()`, `MediaAssetPolicy::view()`, `MediaAssetPolicy::create()`, `MediaAssetPolicy::update()`, `MediaAssetPolicy::delete()`, `MediaAssetPolicy::deleteAny()`, `MediaAssetPolicy::restore()`.
- [MediaAssetPolicy.php](./MediaAssetPolicy.php#L55) also exposes `MediaAssetPolicy::forceDelete()`.

## Invariants

- `MediaAssetPolicy` grants general media actions through `manage_media`, `manage_cms`, or `manage_blog`. Restore requires `manage_media`. [General permissions](./MediaAssetPolicy.php#L13) and [restore permission](./MediaAssetPolicy.php#L50).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
