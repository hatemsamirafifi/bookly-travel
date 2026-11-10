# Admin

<!-- EGM contract -->

## Overview

Supplies selectable public image assets to the rich-text editor.

## Interface

- [HugeRteMediaController.php](./HugeRteMediaController.php#L13) declares `HugeRteMediaController`, `HugeRteMediaController::__invoke()`.

## Invariants

- `HugeRteMediaController::__invoke` requires one of `manage_blog`, `manage_cms`, or `manage_media` and lists ready, public image assets with files present. [HugeRteMediaController.php](./HugeRteMediaController.php#L15)

## Side Effects

- Queries the newest 100 matching records, checks their backing files, and returns asset URLs, labels, and alternative text. [HugeRteMediaController.php](./HugeRteMediaController.php#L22)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
