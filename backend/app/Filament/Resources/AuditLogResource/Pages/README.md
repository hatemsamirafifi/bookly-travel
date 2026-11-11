# Pages

<!-- EGM contract -->

## Overview

Lists authentication audit records through `AuditLogResource`.

## Interface

- [ListAuditLogs.php](./ListAuditLogs.php#L8) declares `ListAuditLogs`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
