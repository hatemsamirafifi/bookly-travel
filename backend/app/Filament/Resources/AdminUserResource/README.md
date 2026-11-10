# AdminUserResource

<!-- EGM contract -->

## Overview

Page group for creating and maintaining administrator accounts, including role and direct permission assignments.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, and edits administrator accounts.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `AdminUserResource::canManageUsers` requires `manage_users`. [AdminUserResource.php](../AdminUserResource.php#L79)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
