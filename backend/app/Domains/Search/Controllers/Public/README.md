# Public

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Search/Controllers/Public`.

## Interface

- [CategoryController.php](./CategoryController.php#L12) declares `CategoryController`, `CategoryController::index()`, `CategoryController::tours()`.
- [DestinationController.php](./DestinationController.php#L13) declares `DestinationController`, `DestinationController::index()`, `DestinationController::tours()`.
- [HomepageController.php](./HomepageController.php#L10) declares `HomepageController`, `HomepageController::index()`.
- [SearchController.php](./SearchController.php#L10) declares `SearchController`, `SearchController::search()`.
- [SitemapController.php](./SitemapController.php#L12) declares `SitemapController`, `SitemapController::index()`.
- [TourDetailController.php](./TourDetailController.php#L10) declares `TourDetailController`, `TourDetailController::show()`.

## Invariants

- `CategoryController::tours` requires an active category, and `DestinationController::tours` checks for a destination slug on a published tour. [Category check](./CategoryController.php#L40) and [destination check](./DestinationController.php#L30).

## Side Effects

- Public catalog controllers cache search, homepage, tour detail, and sitemap responses. [Search cache](./SearchController.php#L16), [homepage cache](./HomepageController.php#L16), [detail cache](./TourDetailController.php#L16), and [sitemap cache](./SitemapController.php#L23).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
