# Concerns

<!-- EGM contract -->

## Overview

Shares the reviewer display name logic across public and partner serializers.

## Interface

- [FormatsReviewerName.php](./FormatsReviewerName.php#L14) declares `FormatsReviewerName`.

## Invariants

- `FormatsReviewerName::reviewerName` uses `Anonymous Traveler` when the wrapped resource has no traveler and otherwise takes the first space-delimited name token when present. [FormatsReviewerName.php](./FormatsReviewerName.php#L16)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
