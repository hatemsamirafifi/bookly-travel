# Models

<!-- EGM contract -->

## Overview

Local source in `TranslationFieldState.php`.

## Interface

- [TranslationFieldState.php](./TranslationFieldState.php#L19) declares `TranslationFieldState`, `TranslationFieldState::translatable()`, `TranslationFieldState::manualEditor()`.

## Invariants

- `TranslationFieldState` attaches to the content record polymorphically and associates manual edits with a user. [Content and editor relations](./TranslationFieldState.php#L71).

- Lifecycle constants in [TranslationFieldState.php](./TranslationFieldState.php#L23) include `STATUS_MISSING` → `missing`, `STATUS_QUEUED` → `queued`, `STATUS_TRANSLATING` → `translating`, `STATUS_COMPLETED` → `completed`, `STATUS_OUTDATED` → `outdated`, `STATUS_FAILED` → `failed`, `STATUS_NEEDS_REVIEW` → `needs_review`, `STATUS_MANUALLY_EDITED` → `manually_edited`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
