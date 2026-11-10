# MediaAssetResource

<!-- EGM contract -->

## Overview

Page group for browsing media assets, uploading files, and editing metadata.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists media assets and exposes create and edit forms. The create handler forwards staged files to the domain uploader.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `MediaAssetResource::canViewAny` recognizes media, CMS, or blog management permissions. [MediaAssetResource.php](../MediaAssetResource.php#L197)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
