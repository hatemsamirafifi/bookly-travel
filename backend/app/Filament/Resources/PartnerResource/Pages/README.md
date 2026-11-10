# Pages

<!-- EGM contract -->

## Overview

Lists and views partners through `PartnerResource`.

## Interface

- [ListPartners.php](./ListPartners.php#L8) declares `ListPartners`.
- [ViewPartner.php](./ViewPartner.php#L8) declares `ViewPartner`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
