# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits administrative roles.

## Interface

- [CreateAdminRole.php](./CreateAdminRole.php#L11) declares `CreateAdminRole`.
- [EditAdminRole.php](./EditAdminRole.php#L12) declares `EditAdminRole`.
- [ListAdminRoles.php](./ListAdminRoles.php#L9) declares `ListAdminRoles`.

## Invariants

- `EditAdminRole::handleRecordUpdate` updates permissions only for custom roles and hides the delete action for system roles. [EditAdminRole.php](./EditAdminRole.php#L25)

## Side Effects

- `CreateAdminRole::handleRecordCreation` persists a custom role with a normalized slug and permission flag map. `EditAdminRole::handleRecordUpdate` saves permitted changes. [CreateAdminRole.php](./CreateAdminRole.php#L15) [EditAdminRole.php](./EditAdminRole.php#L25)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
