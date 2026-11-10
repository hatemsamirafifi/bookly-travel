# Models

<!-- EGM contract -->

## Overview

Local source in `ContentRevision.php`.

## Interface

- [ContentRevision.php](./ContentRevision.php#L20) declares `ContentRevision`, `ContentRevision::revisionable()`, `ContentRevision::actor()`, `ContentRevision::staticPages()`, `ContentRevision::blogPosts()`.

## Invariants

- `ContentRevision` omits an update timestamp and resolves its revision target with a polymorphic relation. [Timestamp contract](./ContentRevision.php#L22) and [revision target](./ContentRevision.php#L53).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
