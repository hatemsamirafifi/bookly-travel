# Pages

<!-- EGM contract -->

## Overview

Lists saved revisions and exposes a restore action on the detail page.

## Interface

- [ListContentRevisions.php](./ListContentRevisions.php#L8) declares `ListContentRevisions`.
- [ViewContentRevision.php](./ViewContentRevision.php#L11) declares `ViewContentRevision`.

## Invariants

None recorded in this contract.

## Side Effects

- `ViewContentRevision::getHeaderActions` invokes `ContentRevisionService::restore` with the record and authenticated actor after confirmation. [ViewContentRevision.php](./ViewContentRevision.php#L15)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
