# Tour

<!-- EGM contract -->

## Overview

Composes the public tour detail page and shares one option, date and participant selection across desktop and mobile booking surfaces.

## Interface

- `TourDetail` consumes the catalog detail and locale. It passes the tour's pricing, availability, options and UTC-day snapshot to `TourBookingController`. [TourDetail.tsx](./TourDetail.tsx#L23) [TourDetail.tsx](./TourDetail.tsx#L148)
- [AvailabilityCalendar.tsx](./AvailabilityCalendar.tsx#L6) declares `AvailabilityCalendarProps`, `default AvailabilityCalendar`.
- [BookingCard.tsx](./BookingCard.tsx#L11) declares `BookingCardAuth`, `BookingCountStepperProps`, `BookingCountStepper`, `BookingReasonListProps`, `BookingReasonList`, `BookingContinuationProps`, `BookingContinuation`, `BookingCardProps`.
- [BookingCard.tsx](./BookingCard.tsx#L216) also exposes `default BookingCard`.
- [BookingCTA.tsx](./BookingCTA.tsx#L19) declares `default BookingCTA`.
- [ImageGallery.tsx](./ImageGallery.tsx#L15) declares `default ImageGallery`.
- [MobileBookingAction.tsx](./MobileBookingAction.tsx#L19) declares `default MobileBookingAction`.
- [MobileBookingBar.tsx](./MobileBookingBar.tsx#L19) declares `MobileBookingBarProps`, `default MobileBookingBar`.
- [OperatorSummary.tsx](./OperatorSummary.tsx#L14) declares `default OperatorSummary`.
- [OptionSelector.tsx](./OptionSelector.tsx#L27) declares `formatOptionPriceDisplay`, `default OptionSelector`.
- [RelatedTours.tsx](./RelatedTours.tsx#L10) declares `default RelatedTours`.
- [ReviewList.tsx](./ReviewList.tsx#L8) declares `default ReviewList`.
- [TourBookingController.tsx](./TourBookingController.tsx#L18) declares `TourBookingControllerProps`, `default TourBookingController`.
- [TourDetail.tsx](./TourDetail.tsx#L34) declares `default TourDetail`.
- [TourFacts.tsx](./TourFacts.tsx#L15) declares `default TourFacts`.
- [TourGallery.tsx](./TourGallery.tsx#L90) declares `default TourGallery`.
- [TourHeader.tsx](./TourHeader.tsx#L26) declares `default TourHeader`.
- [TourHighlights.tsx](./TourHighlights.tsx#L10) declares `default TourHighlights`.
- [TourInclusions.tsx](./TourInclusions.tsx#L12) declares `default TourInclusions`.
- [TourItinerary.tsx](./TourItinerary.tsx#L11) declares `default TourItinerary`.
- [TourMeetingPoint.tsx](./TourMeetingPoint.tsx#L5) declares `TourMeetingPoint`, `TourCancellation`, `TourImportantInformation`.
- [TourOverview.tsx](./TourOverview.tsx#L8) declares `DESCRIPTION_PREVIEW_CODE_POINTS`, `default TourOverview`.
- [TourReviews.tsx](./TourReviews.tsx#L12) declares `default TourReviews`.
- [TourSectionNav.tsx](./TourSectionNav.tsx#L5) declares `TourSectionNavEntry`, `default TourSectionNav`.

## Invariants

- Derive navigation entries from the same presentation model used to determine which detail sections are present. Keep actual content and itinerary language disclosures separate. [TourDetail.tsx](./TourDetail.tsx#L34) [TourDetail.tsx](./TourDetail.tsx#L55)
- Tours with options start without an option selected. Switching options derives date and party defaults from the new option, and a new tour slug resets the selection. Updated props on the same tour can invalidate the existing selection without silently replacing it. [TourBookingController.tsx](./TourBookingController.tsx#L96) [TourBookingController.tsx](./TourBookingController.tsx#L134) [TourBookingController.tsx](./TourBookingController.tsx#L191)
- Disable selection controls while auth is restoring. The continuation requires a valid checkout URL, leads signed-in travelers to checkout and routes signed-out travelers through sign-in or registration. Recheck the UTC day on click. [TourBookingController.tsx](./TourBookingController.tsx#L265) [TourBookingController.tsx](./TourBookingController.tsx#L286) [BookingCard.tsx](./BookingCard.tsx#L127)

## Side Effects

- Refreshes the UTC day after hydration, at UTC midnight and when the document becomes visible. The controller clears timers and listeners on cleanup. [TourBookingController.tsx](./TourBookingController.tsx#L154)
- Section navigation measures the sticky obstruction, observes section visibility, and updates the URL fragment after moving to a target. [TourSectionNav.tsx](./TourSectionNav.tsx#L139) [TourSectionNav.tsx](./TourSectionNav.tsx#L179) [TourSectionNav.tsx](./TourSectionNav.tsx#L252)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
