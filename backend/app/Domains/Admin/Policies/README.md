# Policies

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Policies`.

## Interface

- [AuthAuditLogPolicy.php](./AuthAuditLogPolicy.php#L13) declares `AuthAuditLogPolicy`, `AuthAuditLogPolicy::viewAny()`, `AuthAuditLogPolicy::view()`, `AuthAuditLogPolicy::create()`, `AuthAuditLogPolicy::update()`, `AuthAuditLogPolicy::delete()`, `AuthAuditLogPolicy::deleteAny()`.
- [AvailabilityPolicy.php](./AvailabilityPolicy.php#L14) declares `AvailabilityPolicy`, `AvailabilityPolicy::viewAny()`, `AvailabilityPolicy::view()`, `AvailabilityPolicy::create()`, `AvailabilityPolicy::update()`, `AvailabilityPolicy::delete()`, `AvailabilityPolicy::deleteAny()`.
- [BookingPolicy.php](./BookingPolicy.php#L13) declares `BookingPolicy`, `BookingPolicy::viewAny()`, `BookingPolicy::view()`, `BookingPolicy::transition()`.
- [CategoryPolicy.php](./CategoryPolicy.php#L9) declares `CategoryPolicy`, `CategoryPolicy::viewAny()`, `CategoryPolicy::view()`, `CategoryPolicy::create()`, `CategoryPolicy::update()`, `CategoryPolicy::delete()`, `CategoryPolicy::deleteAny()`, `CategoryPolicy::restore()`.
- [CategoryPolicy.php](./CategoryPolicy.php#L53) also exposes `CategoryPolicy::forceDelete()`.
- [ContentRevisionPolicy.php](./ContentRevisionPolicy.php#L15) declares `ContentRevisionPolicy`, `ContentRevisionPolicy::viewAny()`, `ContentRevisionPolicy::view()`.
- [GovernanceAuditPolicy.php](./GovernanceAuditPolicy.php#L14) declares `GovernanceAuditPolicy`, `GovernanceAuditPolicy::viewAny()`, `GovernanceAuditPolicy::view()`, `GovernanceAuditPolicy::create()`, `GovernanceAuditPolicy::update()`, `GovernanceAuditPolicy::delete()`, `GovernanceAuditPolicy::deleteAny()`.
- [PartnerPolicy.php](./PartnerPolicy.php#L13) declares `PartnerPolicy`, `PartnerPolicy::viewAny()`, `PartnerPolicy::view()`, `PartnerPolicy::approve()`, `PartnerPolicy::reject()`, `PartnerPolicy::suspend()`, `PartnerPolicy::unsuspend()`.
- [StaticPagePolicy.php](./StaticPagePolicy.php#L13) declares `StaticPagePolicy`, `StaticPagePolicy::viewAny()`, `StaticPagePolicy::view()`, `StaticPagePolicy::create()`, `StaticPagePolicy::update()`, `StaticPagePolicy::delete()`, `StaticPagePolicy::deleteAny()`.
- [TourPolicy.php](./TourPolicy.php#L17) declares `TourPolicy`, `TourPolicy::viewAny()`, `TourPolicy::view()`, `TourPolicy::create()`, `TourPolicy::update()`, `TourPolicy::delete()`, `TourPolicy::deleteAny()`, `TourPolicy::publish()`.
- [TourPolicy.php](./TourPolicy.php#L61) also exposes `TourPolicy::reject()`, `TourPolicy::unpublish()`, `TourPolicy::bulkPublish()`, `TourPolicy::bulkReject()`.

## Invariants

- Tour, partner, and booking administrative actions require their corresponding `manage_tours`, `manage_partners`, and `manage_bookings` permissions. Governance audit views require `view_audit_log`. [Tour policy](./TourPolicy.php#L21), [partner policy](./PartnerPolicy.php#L17), [booking policy](./BookingPolicy.php#L17), and [audit policy](./GovernanceAuditPolicy.php#L18).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
