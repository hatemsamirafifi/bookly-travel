# Search

<!-- EGM contract -->

## Overview

Public tour discovery and detail, homepage data, destination and category browse, search indexing, and sitemap output.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `ConfigureSearchIndexAction`, `GetHomepageDataAction`, `GetPublicOperatorSummaryAction`.
- [Controllers](./Controllers/README.md) — HTTP controllers for the public catalog, discovery collections, and sitemap.
- [Support](./Support/README.md) — Public declarations include `DestinationAggregator`, `DurationBucket`, `SearchableAttributes`.
- [Transformers](./Transformers/README.md) — Public declarations include `TourCardTransformer`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
