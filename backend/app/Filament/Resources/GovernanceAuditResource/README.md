# GovernanceAuditResource

<!-- EGM contract -->

## Overview

Page group for inspecting governance audit events.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists governance audits and displays details for the selected entry.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `GovernanceAuditResource` denies create, edit, and delete. [GovernanceAuditResource.php](../GovernanceAuditResource.php#L159)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
