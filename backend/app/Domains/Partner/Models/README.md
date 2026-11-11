# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Models`.

## Interface

- [AvailabilityException.php](./AvailabilityException.php#L9) declares `AvailabilityException`, `AvailabilityException::tour()`.
- [AvailabilityRule.php](./AvailabilityRule.php#L9) declares `AvailabilityRule`, `AvailabilityRule::tour()`.
- [Notification.php](./Notification.php#L8) declares `Notification`, `Notification::partner()`, `Notification::scopeUnread()`, `Notification::markAsRead()`.
- [Partner.php](./Partner.php#L14) declares `Partner`, `Partner::isPayoutReady()`, `Partner::user()`, `Partner::tours()`, `Partner::profile()`, `Partner::governanceAuditLogs()`, `Partner::settings()`, `Partner::notifications()`.
- [Partner.php](./Partner.php#L83) also exposes `Partner::unreadNotifications()`, `Partner::canTransitionTo()`, `Partner::canTransition()`, `Partner::removeToursFromDiscovery()`, `Partner::restoreToursToDiscovery()`.
- [PartnerInvitation.php](./PartnerInvitation.php#L11) declares `PartnerInvitation`, `PartnerInvitation::invitedByAdmin()`, `PartnerInvitation::partner()`, `PartnerInvitation::scopePending()`, `PartnerInvitation::scopeValid()`, `PartnerInvitation::isExpired()`, `PartnerInvitation::isConsumed()`, `PartnerInvitation::isValid()`.
- [PartnerProfile.php](./PartnerProfile.php#L10) declares `PartnerProfile`, `PartnerProfile::partner()`, `PartnerProfile::getRawPayoutAccountNumber()`, `PartnerProfile::getRawPayoutIban()`, `PartnerProfile::getRawPayoutSwiftBic()`.
- [PartnerSettings.php](./PartnerSettings.php#L8) declares `PartnerSettings`, `PartnerSettings::partner()`.
- [PricingTier.php](./PricingTier.php#L9) declares `PricingTier`, `PricingTier::tour()`.
- [ReviewResponse.php](./ReviewResponse.php#L8) declares `ReviewResponse`, `ReviewResponse::partner()`.
- [TourDraft.php](./TourDraft.php#L9) declares `TourDraft`, `TourDraft::tour()`, `TourDraft::partner()`.
- [TourMedia.php](./TourMedia.php#L18) declares `TourMedia`, `TourMedia::tour()`.

## Invariants

- `PartnerInvitation::isValid` requires pending status and an invitation that has yet to expire. [Invitation validity](./PartnerInvitation.php#L57).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
