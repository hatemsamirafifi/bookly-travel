# BlogPostResource

<!-- EGM contract -->

## Overview

Page group for authoring and previewing blog posts and dispatching translation work.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists, creates, views, and edits blog posts. The edit page provides preview and translation actions.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
