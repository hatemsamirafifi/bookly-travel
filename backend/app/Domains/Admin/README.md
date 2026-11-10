# Admin

<!-- EGM contract -->

## Overview

Administrative permissions and governance audit, partner and tour moderation, booking transitions, static pages, and platform settings.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `ApprovePartnerAction`, `ApproveTourAction`, `ArchiveTourAction`.
- [Models](./Models/README.md) — Public declarations include `AdminPermission`, `AdminRole`, `CmsContent`.
- [Policies](./Policies/README.md) — Public declarations include `AuthAuditLogPolicy`, `AvailabilityPolicy`, `BookingPolicy`.
- [Requests](./Requests/README.md) — Public declarations include `RejectTourRequest`, `TransitionBookingStatusRequest`, `UpdateSettingsRequest`.
- [Services](./Services/README.md) — Public declarations include `AdminAuthorizationService`, `AdminUserService`, `AvailabilitySlotService`.
- [Settings](./Settings/README.md) — Public declarations include `BookingSettings`, `ContactSettings`, `GeneralSettings`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
