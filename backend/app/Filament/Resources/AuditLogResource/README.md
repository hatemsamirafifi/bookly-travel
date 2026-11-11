# AuditLogResource

<!-- EGM contract -->

## Overview

Page group for inspecting authentication audit records.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists authentication audit records through `AuditLogResource`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `AuditLogResource::canViewAny` requires `view_audit_log`. `canCreate` denies creation. [AuditLogResource.php](../AuditLogResource.php#L30) [AuditLogResource.php](../AuditLogResource.php#L109)

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
