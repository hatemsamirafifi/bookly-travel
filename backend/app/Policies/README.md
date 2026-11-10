# Policies

<!-- EGM contract -->

## Overview

Authorization policies for user self-service and review moderation.

## Interface

- [ReviewPolicy.php](./ReviewPolicy.php#L9) declares `ReviewPolicy`, `ReviewPolicy::manage()`, `ReviewPolicy::viewAny()`.
- [UserPolicy.php](./UserPolicy.php#L8) declares `UserPolicy`, `UserPolicy::view()`, `UserPolicy::update()`, `UserPolicy::delete()`, `UserPolicy::manageSessions()`, `UserPolicy::changePassword()`, `UserPolicy::hasRole()`, `UserPolicy::hasPermission()`.

## Invariants

- `UserPolicy::view`, `update`, `delete`, `manageSessions`, and `changePassword` all require equal user IDs. [UserPolicy.php](./UserPolicy.php#L13) [UserPolicy.php](./UserPolicy.php#L37)
- `ReviewPolicy::manage` and `viewAny` both ask `AdminAuthorizationService` for `moderate_reviews`. [ReviewPolicy.php](./ReviewPolicy.php#L17)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
