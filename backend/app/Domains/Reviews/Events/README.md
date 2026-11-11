# Events

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Reviews/Events`.

## Interface

- [ReviewFlagged.php](./ReviewFlagged.php#L9) declares `ReviewFlagged`.
- [ReviewSubmitted.php](./ReviewSubmitted.php#L9) declares `ReviewSubmitted`.

## Invariants

- `ReviewSubmitted` carries the review model. `ReviewFlagged` also carries the matched keywords. [Submitted payload](./ReviewSubmitted.php#L13) and [flagged payload](./ReviewFlagged.php#L13).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
