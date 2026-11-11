# Factories

<!-- EGM contract -->

## Overview

Generates Eloquent model attributes and reusable fixture states for destinations, guest identities, and users.

## Interface

- [DestinationFactory.php](./DestinationFactory.php#L11) declares `DestinationFactory`, `DestinationFactory::definition()`.
- [GuestIdentityFactory.php](./GuestIdentityFactory.php#L11) declares `GuestIdentityFactory`, `GuestIdentityFactory::definition()`, `GuestIdentityFactory::converted()`, `GuestIdentityFactory::anonymized()`.
- [UserFactory.php](./UserFactory.php#L13) declares `UserFactory`, `UserFactory::definition()`, `UserFactory::unverified()`, `UserFactory::traveler()`, `UserFactory::partner()`, `UserFactory::admin()`, `UserFactory::locale()`, `UserFactory::lockedOut()`.

## Invariants

- Default users are verified English-speaking travelers with a cached password hash. Default guest identities have null conversion and anonymization fields, and default destinations are active. [UserFactory.php](./UserFactory.php#L25), [GuestIdentityFactory.php](./GuestIdentityFactory.php#L25), [DestinationFactory.php](./DestinationFactory.php#L15)
- `GuestIdentityFactory::anonymized()` replaces contact fields and sets `anonymized_at`. `UserFactory::lockedOut()` sets five failed attempts and a one-minute lock. [GuestIdentityFactory.php](./GuestIdentityFactory.php#L49), [UserFactory.php](./UserFactory.php#L94)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
