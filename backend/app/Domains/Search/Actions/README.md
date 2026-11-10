# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Search/Actions`.

## Interface

- [ConfigureSearchIndexAction.php](./ConfigureSearchIndexAction.php#L7) declares `ConfigureSearchIndexAction`, `ConfigureSearchIndexAction::execute()`.
- [GetHomepageDataAction.php](./GetHomepageDataAction.php#L10) declares `GetHomepageDataAction`, `GetHomepageDataAction::execute()`.
- [GetPublicOperatorSummaryAction.php](./GetPublicOperatorSummaryAction.php#L8) declares `GetPublicOperatorSummaryAction`, `GetPublicOperatorSummaryAction::execute()`.
- [GetRelatedToursAction.php](./GetRelatedToursAction.php#L11) declares `GetRelatedToursAction`, `GetRelatedToursAction::execute()`.
- [GetScopedToursAction.php](./GetScopedToursAction.php#L13) declares `GetScopedToursAction`, `GetScopedToursAction::execute()`.
- [GetTourDetailAction.php](./GetTourDetailAction.php#L12) declares `GetTourDetailAction`, `GetTourDetailAction::execute()`.
- [IndexTourAction.php](./IndexTourAction.php#L9) declares `IndexTourAction`, `IndexTourAction::handle()`.
- [RemoveFromIndexAction.php](./RemoveFromIndexAction.php#L9) declares `RemoveFromIndexAction`, `RemoveFromIndexAction::handle()`.
- [SearchToursAction.php](./SearchToursAction.php#L13) declares `SearchToursAction`, `SearchToursAction::execute()`.

## Invariants

- `SearchToursAction::execute` restricts search fields to the requested locale and shared attributes. `buildFilterString` always requires published status and preserves a zero-valued price bound. [Search attributes](./SearchToursAction.php#L24) and [base filters](./SearchToursAction.php#L77).
- `IndexTourAction::handle` indexes only a tour that still exists and passes `shouldBeSearchable()`. [Index eligibility](./IndexTourAction.php#L17).

## Side Effects

- `IndexTourAction::handle` writes the tour to the search index through `searchable()`. [Index write](./IndexTourAction.php#L26).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
