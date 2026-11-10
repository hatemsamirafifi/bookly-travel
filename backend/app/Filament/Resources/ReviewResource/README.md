# ReviewResource

<!-- EGM contract -->

## Overview

Page group for inspecting reviews and accessing moderation actions.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists and views reviews through `ReviewResource`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `ReviewResource::table` makes flagging available for visible reviews and checks target-state eligibility for hide and reinstatement, in addition to actor policy. [ReviewResource.php](../ReviewResource.php#L154) [ReviewResource.php](../ReviewResource.php#L171)

## Side Effects

- `ReviewResource::table` delegates moderation to flag, hide, and reinstatement actions. [ReviewResource.php](../ReviewResource.php#L155) [ReviewResource.php](../ReviewResource.php#L189)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
