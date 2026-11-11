# Requests

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Requests`.

## Interface

- [CompleteInvitationRequest.php](./CompleteInvitationRequest.php#L7) declares `CompleteInvitationRequest`, `CompleteInvitationRequest::authorize()`, `CompleteInvitationRequest::rules()`.
- [PartnerRegistrationRequest.php](./PartnerRegistrationRequest.php#L7) declares `PartnerRegistrationRequest`, `PartnerRegistrationRequest::authorize()`, `PartnerRegistrationRequest::rules()`.
- [PartnerResubmitRequest.php](./PartnerResubmitRequest.php#L7) declares `PartnerResubmitRequest`, `PartnerResubmitRequest::authorize()`, `PartnerResubmitRequest::rules()`.
- [StoreReviewResponseRequest.php](./StoreReviewResponseRequest.php#L8) declares `StoreReviewResponseRequest`, `StoreReviewResponseRequest::authorize()`, `StoreReviewResponseRequest::rules()`.
- [StoreTourRequest.php](./StoreTourRequest.php#L9) declares `StoreTourRequest`, `StoreTourRequest::authorize()`, `StoreTourRequest::rules()`, `StoreTourRequest::withValidator()`.
- [TourContentRules.php](./TourContentRules.php#L19) declares `TourContentRules`, `TourContentRules::normalizeSourcePatch()`, `TourContentRules::normalizeEnglishPatch()`, `TourContentRules::normalizeItinerary()`, `TourContentRules::sourceRules()`, `TourContentRules::itineraryRules()`, `TourContentRules::guideLanguageRules()`, `TourContentRules::mediaRules()`.
- [TourContentRules.php](./TourContentRules.php#L271) also exposes `TourContentRules::optionRules()`, `TourContentRules::optionIdPaths()`, `TourContentRules::normalizeOptions()`, `TourContentRules::castItineraryInts()`, `TourContentRules::forbiddenPresentPaths()`.
- [UpdateBookingStatusRequest.php](./UpdateBookingStatusRequest.php#L8) declares `UpdateBookingStatusRequest`, `UpdateBookingStatusRequest::authorize()`, `UpdateBookingStatusRequest::rules()`, `UpdateBookingStatusRequest::messages()`.
- [UpdateProfileRequest.php](./UpdateProfileRequest.php#L9) declares `UpdateProfileRequest`, `UpdateProfileRequest::authorize()`, `UpdateProfileRequest::rules()`.
- [UpdateTourRequest.php](./UpdateTourRequest.php#L11) declares `UpdateTourRequest`, `UpdateTourRequest::authorize()`, `UpdateTourRequest::rules()`, `UpdateTourRequest::withValidator()`.

## Invariants

- `UpdateTourRequest` derives validation from the stored source locale and validates each supplied option ID against the current tour's options. [Post-validation](./UpdateTourRequest.php#L105).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
