# Controllers

<!-- EGM contract -->

## Overview

Partner HTTP endpoints for tours and drafts, bookings, reviews, availability, pricing, profile and onboarding, Stripe Connect, analytics, uploads, and notifications.

## Interface

- [AnalyticsController.php](./AnalyticsController.php#L10) declares `AnalyticsController`, `AnalyticsController::index()`.
- [AvailabilityController.php](./AvailabilityController.php#L12) declares `AvailabilityController`, `AvailabilityController::index()`, `AvailabilityController::storeRule()`, `AvailabilityController::updateRule()`, `AvailabilityController::destroyRule()`, `AvailabilityController::storeException()`, `AvailabilityController::updateException()`, `AvailabilityController::destroyException()`.
- [BookingController.php](./BookingController.php#L12) declares `BookingController`, `BookingController::index()`, `BookingController::show()`, `BookingController::updateStatus()`, `BookingController::requestCancellation()`.
- [NotificationController.php](./NotificationController.php#L9) declares `NotificationController`, `NotificationController::index()`, `NotificationController::markAsRead()`, `NotificationController::markAllAsRead()`.
- [OnboardingStatusController.php](./OnboardingStatusController.php#L12) declares `OnboardingStatusController`, `OnboardingStatusController::show()`, `OnboardingStatusController::resubmit()`.
- [PartnerHugeRteMediaController.php](./PartnerHugeRteMediaController.php#L12) declares `PartnerHugeRteMediaController`, `PartnerHugeRteMediaController::__invoke()`.
- [PartnerOnboardingStatusController.php](./PartnerOnboardingStatusController.php#L12) declares `PartnerOnboardingStatusController`, `PartnerOnboardingStatusController::show()`, `PartnerOnboardingStatusController::resubmit()`.
- [PartnerStripeConnectController.php](./PartnerStripeConnectController.php#L10) declares `PartnerStripeConnectController`, `PartnerStripeConnectController::status()`, `PartnerStripeConnectController::onboard()`, `PartnerStripeConnectController::dashboard()`.
- [PricingController.php](./PricingController.php#L11) declares `PricingController`, `PricingController::index()`, `PricingController::store()`, `PricingController::update()`, `PricingController::destroy()`.
- [ProfileController.php](./ProfileController.php#L11) declares `ProfileController`, `ProfileController::show()`, `ProfileController::update()`, `ProfileController::settings()`, `ProfileController::updateSettings()`.
- [ReviewController.php](./ReviewController.php#L12) declares `ReviewController`, `ReviewController::index()`, `ReviewController::storeResponse()`, `ReviewController::updateResponse()`.
- [TourController.php](./TourController.php#L12) declares `TourController`, `TourController::index()`, `TourController::show()`, `TourController::store()`, `TourController::update()`, `TourController::destroy()`, `TourController::saveDraft()`, `TourController::latestDraft()`.
- [TourController.php](./TourController.php#L132) also exposes `TourController::submitForReview()`, `TourController::archive()`.
- [UploadController.php](./UploadController.php#L10) declares `UploadController`, `UploadController::signedUrl()`.
- [Public](./Public/README.md) — Public declarations include `PartnerInvitationController`, `PartnerRegistrationController`.

## Invariants

- `TourController` scopes tour detail and editing to the request's partner, using the owner lookup before an update. [Detail ownership](./TourController.php#L39) and [update ownership](./TourController.php#L61).

## Side Effects

- `TourController` delegates tour creation and updates, draft persistence, review submission, and archiving to `TourService`. Both the destroy and archive entry points invoke `archiveTour`. [Create and update](./TourController.php#L46) · [Draft save](./TourController.php#L96) · [Submit](./TourController.php#L132) · [Archive](./TourController.php#L173).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
