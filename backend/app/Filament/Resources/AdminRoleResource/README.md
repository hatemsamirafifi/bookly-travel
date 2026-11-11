# AdminRoleResource

<!-- EGM contract -->

## Overview

Page group for managing custom admin roles and inspecting system roles.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, and edits administrative roles.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `AdminRoleResource::canManageRoles` requires `manage_roles` or `manage_users`. [AdminRoleResource.php](../AdminRoleResource.php#L52)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
