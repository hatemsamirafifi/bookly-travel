# Support

<!-- EGM contract -->

## Overview

Deploys bundled demo media used by seed and administration workflows.

## Interface

- [DemoMediaDeployer.php](./DemoMediaDeployer.php#L10) declares `DemoMediaDeployer`, `DemoMediaDeployer::getSourceDirectory()`, `DemoMediaDeployer::deployAll()`, `DemoMediaDeployer::deployCategories()`, `DemoMediaDeployer::deployBlogCategories()`, `DemoMediaDeployer::deployTours()`, `DemoMediaDeployer::deployBlogCovers()`, `DemoMediaDeployer::ensureStorageLink()`.

## Invariants

- `DemoMediaDeployer` copies missing assets from available fixture directories. `ensureStorageLink` runs the command when the public storage path is neither an existing file nor a link. [DemoMediaDeployer.php](./DemoMediaDeployer.php#L49) [DemoMediaDeployer.php](./DemoMediaDeployer.php#L162)

## Side Effects

- `DemoMediaDeployer::deployAll` delegates to asset-specific deployment methods that create directories and copy category, tour, and blog images. `ensureStorageLink` invokes `storage:link` and logs failures. [DemoMediaDeployer.php](./DemoMediaDeployer.php#L37) [DemoMediaDeployer.php](./DemoMediaDeployer.php#L132) [DemoMediaDeployer.php](./DemoMediaDeployer.php#L162)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
