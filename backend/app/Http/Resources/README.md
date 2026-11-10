# Resources

<!-- EGM contract -->

## Overview

Field allowlists for user and guest identities and public, partner, and admin review responses.

## Interface

- [AdminReviewResource.php](./AdminReviewResource.php#L11) declares `AdminReviewResource`, `AdminReviewResource::toArray()`.
- [GuestIdentityResource.php](./GuestIdentityResource.php#L10) declares `GuestIdentityResource`, `GuestIdentityResource::toArray()`.
- [PartnerReviewResource.php](./PartnerReviewResource.php#L20) declares `PartnerReviewResource`, `PartnerReviewResource::toArray()`.
- [ReviewResource.php](./ReviewResource.php#L11) declares `ReviewResource`, `ReviewResource::toArray()`.
- [UserResource.php](./UserResource.php#L10) declares `UserResource`, `UserResource::toArray()`.
- [Concerns](./Concerns/README.md) — Shares the reviewer display name logic across public and partner serializers.

## Invariants

- `ReviewResource::toArray` and `PartnerReviewResource::toArray` use the shared display-name formatter and omit raw traveler and booking IDs. The partner resource includes tour and response fields only when the relevant relationships are loaded. [ReviewResource.php](./ReviewResource.php#L15) [PartnerReviewResource.php](./PartnerReviewResource.php#L24)
- `AdminReviewResource::toArray` includes the audit trail only when `auditTrails` is loaded. [AdminReviewResource.php](./AdminReviewResource.php#L26)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
