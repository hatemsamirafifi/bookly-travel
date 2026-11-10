# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Services`.

## Interface

- [AnalyticsService.php](./AnalyticsService.php#L10) declares `AnalyticsService`, `AnalyticsService::getSummary()`, `AnalyticsService::getBookingsOverTime()`, `AnalyticsService::getAnalytics()`.
- [BookingService.php](./BookingService.php#L12) declares `BookingService`, `BookingService::listForPartner()`, `BookingService::getForPartner()`, `BookingService::markAsCompleted()`, `BookingService::requestCancellation()`, `BookingService::getBookingSummary()`.
- [GeminiTourTranslator.php](./GeminiTourTranslator.php#L10) declares `GeminiTourTranslator`, `GeminiTourTranslator::translate()`, `GeminiTourTranslator::translateFrom()`.
- [LegacyTourItineraryNormalizer.php](./LegacyTourItineraryNormalizer.php#L39) declares `LegacyTourItineraryNormalizer`, `LegacyTourItineraryNormalizer::normalize()`.
- [NotificationService.php](./NotificationService.php#L9) declares `NotificationService`, `NotificationService::listForPartner()`, `NotificationService::getUnreadCount()`, `NotificationService::markAsRead()`, `NotificationService::markAllAsRead()`, `NotificationService::create()`.
- [PartnerOnboardingService.php](./PartnerOnboardingService.php#L9) declares `PartnerOnboardingService`, `PartnerOnboardingService::getStatus()`, `PartnerOnboardingService::canCreateTours()`, `PartnerOnboardingService::getRejectionReason()`, `PartnerOnboardingService::getSuspensionReason()`, `PartnerOnboardingService::getLifecycleTimestamp()`.
- [PermanentTranslationException.php](./PermanentTranslationException.php#L5) declares `PermanentTranslationException`.
- [ProfileService.php](./ProfileService.php#L9) declares `ProfileService`, `ProfileService::getProfile()`, `ProfileService::getSettings()`, `ProfileService::getProfileWithSettings()`, `ProfileService::updateProfile()`, `ProfileService::updateSettings()`, `ProfileService::generateLogoUploadUrl()`.
- [ReviewService.php](./ReviewService.php#L12) declares `ReviewService`, `ReviewService::listForPartner()`, `ReviewService::getReviewForPartner()`, `ReviewService::createResponse()`, `ReviewService::updateResponse()`, `ReviewService::getTourReviewSummaries()`.
- [TourService.php](./TourService.php#L21) declares `TourService`, `TourService::listForPartner()`, `TourService::getForPartner()`, `TourService::requireOwnedTour()`, `TourService::resolveCategoryId()`, `TourService::createTour()`, `TourService::updateTour()`, `TourService::getOwnedTourDetail()`.
- [TourService.php](./TourService.php#L499) also exposes `TourService::submitForReview()`, `TourService::archiveTour()`, `TourService::saveDraft()`, `TourService::getLatestDraft()`.
- [TourTranslationService.php](./TourTranslationService.php#L18) declares `TourTranslationService`, `TourTranslationService::sourcePayload()`, `TourTranslationService::sourceOptionsPayload()`, `TourTranslationService::sourceLocale()`, `TourTranslationService::derivativeLocales()`, `TourTranslationService::sourceTranslation()`, `TourTranslationService::normalizeSourceProjection()`, `TourTranslationService::sourceHash()`.
- [TourTranslationService.php](./TourTranslationService.php#L251) also exposes `TourTranslationService::withContentLock()`, `TourTranslationService::planSourceDispatches()`, `TourTranslationService::dispatchPlannedTranslations()`, `TourTranslationService::queueIfChanged()`, `TourTranslationService::reconcileTour()`, `TourTranslationService::hasCurrentDerivatives()`, `TourTranslationService::strings()`, `TourTranslationService::validateTranslatablePayload()`, `TourTranslationService::currentTranslation()`, `TourTranslationService::localizedOptionText()`, `TourTranslationService::publicStatus()`.
- [TransientTranslationException.php](./TransientTranslationException.php#L5) declares `TransientTranslationException`.

## Invariants

- `TourService::requireOwnedTour` uses the partner-scoped lookup and throws not found when the tour is outside that scope. [Owner guard](./TourService.php#L36).
- `TourTranslationService::applyStrings` validates the returned translation shape before applying provider strings. [Translation application](./TourTranslationService.php#L573).

## Side Effects

- `TourService::createTour` and `TourService::updateTour` persist tour content and dispatch planned derivative translations after the respective write operations. [Create dispatch](./TourService.php#L179) and [update dispatch](./TourService.php#L309).
- `TourService` updates matching options and retires omitted options when synchronizing the option set. [Option synchronization](./TourService.php#L619).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
