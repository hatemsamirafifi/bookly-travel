# Enums

<!-- EGM contract -->

## Overview

Backed string enums for tour, partner, and review statuses, user roles, and user locale values.

## Interface

- [PartnerStatus.php](./PartnerStatus.php#L13) declares `PartnerStatus`, `PartnerStatus::Pending`, `PartnerStatus::Approved`, `PartnerStatus::Rejected`, `PartnerStatus::Suspended`.
- [ReviewStatus.php](./ReviewStatus.php#L10) declares `ReviewStatus`, `ReviewStatus::Visible`, `ReviewStatus::Hidden`, `ReviewStatus::Flagged`.
- [TourStatus.php](./TourStatus.php#L11) declares `TourStatus`, `TourStatus::Draft`, `TourStatus::PendingReview`, `TourStatus::Published`, `TourStatus::Rejected`, `TourStatus::Archived`.
- [UserLocale.php](./UserLocale.php#L8) declares `UserLocale`, `UserLocale::English`, `UserLocale::Spanish`, `UserLocale::Italian`.
- [UserRole.php](./UserRole.php#L8) declares `UserRole`, `UserRole::Traveler`, `UserRole::Partner`, `UserRole::Admin`.

## Invariants

- `UserRole` contains `traveler`, `partner`, and `admin`. `UserLocale` contains `en`, `es`, and `it`. [UserRole.php](./UserRole.php#L10) [UserLocale.php](./UserLocale.php#L10)

- Serialized values in [PartnerStatus.php](./PartnerStatus.php#L15) include `PartnerStatus::Pending` → `pending`, `PartnerStatus::Approved` → `approved`, `PartnerStatus::Rejected` → `rejected`, `PartnerStatus::Suspended` → `suspended`.

- Serialized values in [ReviewStatus.php](./ReviewStatus.php#L12) include `ReviewStatus::Visible` → `visible`, `ReviewStatus::Hidden` → `hidden`, `ReviewStatus::Flagged` → `flagged`.

- Serialized values in [TourStatus.php](./TourStatus.php#L13) include `TourStatus::Draft` → `draft`, `TourStatus::PendingReview` → `pending_review`, `TourStatus::Published` → `published`, `TourStatus::Rejected` → `rejected`, `TourStatus::Archived` → `archived`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
