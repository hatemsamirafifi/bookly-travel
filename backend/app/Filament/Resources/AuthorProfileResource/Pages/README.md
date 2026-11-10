# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits author profiles with localized names and biographies.

## Interface

- [CreateAuthorProfile.php](./CreateAuthorProfile.php#L10) declares `CreateAuthorProfile`.
- [EditAuthorProfile.php](./EditAuthorProfile.php#L10) declares `EditAuthorProfile`.
- [ListAuthorProfiles.php](./ListAuthorProfiles.php#L8) declares `ListAuthorProfiles`.

## Invariants

None recorded in this contract.

## Side Effects

- `CreateAuthorProfile::handleRecordCreation` creates an `AuthorProfile` record after mapping localized form fields. `EditAuthorProfile::handleRecordUpdate` passes the existing locale maps into the form-data conversion and persists the update. [CreateAuthorProfile.php](./CreateAuthorProfile.php#L17) [EditAuthorProfile.php](./EditAuthorProfile.php#L36)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
