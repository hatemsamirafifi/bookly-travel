# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Reviews/Models`.

## Interface

- [Review.php](./Review.php#L15) declares `Review`, `Review::booking()`, `Review::tour()`, `Review::traveler()`, `Review::auditTrails()`, `Review::reviewResponse()`, `Review::isEdited()`, `Review::canTransitionTo()` and 1 more declarations.
- [ReviewAuditTrail.php](./ReviewAuditTrail.php#L9) declares `ReviewAuditTrail`, `ReviewAuditTrail::review()`, `ReviewAuditTrail::actor()`.

## Invariants

- `Review::canTransitionTo` uses an explicit mapping of allowed targets per review status. `Review::canEdit` limits edits to the first 48 hours. [Transition map](./Review.php#L89) and [edit window](./Review.php#L102).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
