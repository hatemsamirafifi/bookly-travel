# PartnerResource

<!-- EGM contract -->

## Overview

Page group for reviewing partner records and onboarding states.

## Subdirectories & Boundaries

- [Pages](./Pages/README.md) — Lists and views partners through `PartnerResource`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `PartnerResource::table` makes approval and rejection available for pending records and suspension for active approved records, subject to the relevant actor policies. [PartnerResource.php](../PartnerResource.php#L147) [PartnerResource.php](../PartnerResource.php#L183)

## Side Effects

- `PartnerResource::table` invokes partner approval, rejection, suspension, reinstatement, and invitation actions. [PartnerResource.php](../PartnerResource.php#L147) [PartnerResource.php](../PartnerResource.php#L193) [PartnerResource.php](../PartnerResource.php#L221)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
