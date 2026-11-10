# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Actions`.

## Interface

- [ApprovePartnerAction.php](./ApprovePartnerAction.php#L19) declares `ApprovePartnerAction`, `ApprovePartnerAction::execute()`.
- [ApproveTourAction.php](./ApproveTourAction.php#L18) declares `ApproveTourAction`, `ApproveTourAction::execute()`.
- [ArchiveTourAction.php](./ArchiveTourAction.php#L18) declares `ArchiveTourAction`, `ArchiveTourAction::execute()`.
- [ExportBookingsAction.php](./ExportBookingsAction.php#L22) declares `ExportBookingsAction`, `ExportBookingsAction::toCsv()`, `ExportBookingsAction::download()`, `ExportBookingsAction::isAuthorized()`, `ExportBookingsAction::columns()`.
- [InvitePartnerAction.php](./InvitePartnerAction.php#L13) declares `InvitePartnerAction`, `InvitePartnerAction::execute()`.
- [ReinstatePartnerAction.php](./ReinstatePartnerAction.php#L20) declares `ReinstatePartnerAction`, `ReinstatePartnerAction::execute()`.
- [RejectPartnerAction.php](./RejectPartnerAction.php#L20) declares `RejectPartnerAction`, `RejectPartnerAction::execute()`.
- [RejectTourAction.php](./RejectTourAction.php#L18) declares `RejectTourAction`, `RejectTourAction::execute()`.
- [SuspendPartnerAction.php](./SuspendPartnerAction.php#L21) declares `SuspendPartnerAction`, `SuspendPartnerAction::execute()`.
- [TransitionBookingStatusAction.php](./TransitionBookingStatusAction.php#L19) declares `TransitionBookingStatusAction`, `TransitionBookingStatusAction::execute()`.
- [UnpublishTourAction.php](./UnpublishTourAction.php#L17) declares `UnpublishTourAction`, `UnpublishTourAction::execute()`.
- [UpdateSettingsAction.php](./UpdateSettingsAction.php#L24) declares `UpdateSettingsAction`, `UpdateSettingsAction::execute()`.
- [UpdateStaticPageAction.php](./UpdateStaticPageAction.php#L21) declares `UpdateStaticPageAction`, `UpdateStaticPageAction::execute()`.

## Invariants

- `ApproveTourAction::execute` locks the tour, checks its publication transition, and requires a title and description in the tour's authored source locale. It preserves any existing first-publication timestamp. [Publication guards](./ApproveTourAction.php#L22).
- `SuspendPartnerAction::execute` requires authorization and a nonblank suspension reason before applying the state transition. [Suspension guards](./SuspendPartnerAction.php#L28).

## Side Effects

- `ApproveTourAction::execute` publishes the tour and writes a `tour.publish` governance audit entry. [Publication effects](./ApproveTourAction.php#L50).
- `ApprovePartnerAction::execute` sets the onboarding status to approved, activates the partner, and writes a `partner.approve` audit entry. [Partner approval](./ApprovePartnerAction.php#L36).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
