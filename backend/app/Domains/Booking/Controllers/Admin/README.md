# Admin

<!-- EGM contract -->

## Overview

Local source in `AuditController.php`.

## Interface

- [AuditController.php](./AuditController.php#L12) declares `AuditController`, `AuditController::index()`, `AuditController::show()`.

## Invariants

- `AuditController` applies booking, actor, action, and time filters only when the corresponding validated input is present. [Audit filtering](./AuditController.php#L16).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
