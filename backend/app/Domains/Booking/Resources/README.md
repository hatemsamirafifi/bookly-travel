# Resources

<!-- EGM contract -->

## Overview

Local source in `PartnerBookingResource.php`.

## Interface

- [PartnerBookingResource.php](./PartnerBookingResource.php#L10) declares `PartnerBookingResource`, `PartnerBookingResource::toArray()`.

## Invariants

- `PartnerBookingResource::toArray` resolves the tour title using the booking locale, the tour source translation, and the English translation in that order. It selects the traveler account or guest identity and exposes the purchased departure snapshot through `bookingOption()`. [Title and traveler](./PartnerBookingResource.php#L12) and [option snapshot](./PartnerBookingResource.php#L51).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
