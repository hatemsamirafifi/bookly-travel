# Pages

<!-- EGM contract -->

## Overview

Lists, creates, views, and edits tours. Create and edit hooks synchronize translation data.

## Interface

- [CreateTour.php](./CreateTour.php#L10) declares `CreateTour`.
- [EditTour.php](./EditTour.php#L11) declares `EditTour`.
- [ListTours.php](./ListTours.php#L9) declares `ListTours`.
- [ViewTour.php](./ViewTour.php#L8) declares `ViewTour`.

## Invariants

- `EditTour::beforeSave` snapshots translations before saving. `afterSave` detects manual edits from that snapshot and a fresh record. [EditTour.php](./EditTour.php#L52)

## Side Effects

- `CreateTour::afterCreate` and `EditTour::afterSave` call `AutoTranslationService::syncAndDispatch`. [CreateTour.php](./CreateTour.php#L14) [EditTour.php](./EditTour.php#L61)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
