# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits administrator accounts.

## Interface

- [CreateAdminUser.php](./CreateAdminUser.php#L10) declares `CreateAdminUser`.
- [EditAdminUser.php](./EditAdminUser.php#L12) declares `EditAdminUser`.
- [ListAdminUsers.php](./ListAdminUsers.php#L9) declares `ListAdminUsers`.

## Invariants

- `CreateAdminUser::handleRecordCreation` converts selected permission flags to a map and selected role IDs to integers before calling the service. [CreateAdminUser.php](./CreateAdminUser.php#L14)

## Side Effects

- The create and edit record handlers call `AdminUserService::createAdmin` and `updateAdmin`. [CreateAdminUser.php](./CreateAdminUser.php#L14) [EditAdminUser.php](./EditAdminUser.php#L27)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
