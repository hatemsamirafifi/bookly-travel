# Tours

<!-- EGM contract -->

## Overview

Pure derivations for tour detail presentation, booking selection, option-aware checkout, and localized validation messages.

## Interface

- [booking-reasons.ts](./booking-reasons.ts#L7) declares `BookingReasonScope`, `BookingReasonMessage`, `resolveBookingReasonMessage`.
- [booking-selection.ts](./booking-selection.ts#L11) declares `BOOKING_SUPPORTED_CURRENCY`, `BookingInvalidReason`, `BookingSelectionInput`, `BookingSelectionView`, `isRealCalendarDateString`, `isPositiveSafeInteger`, `isSupportedCurrency`, `BookingLocale`.
- [booking-selection.ts](./booking-selection.ts#L76) also exposes `isSupportedLocale`, `areGroupBoundsValid`, `isParticipantCountValid`, `getOfferedFutureDates`, `isOfferedFutureDate`, `isBookableAvailability`, `resolveInitialDate`, `resolveInitialCount`, `isPriceAmountValid`, `isPriceCurrencySupported`, `getEstimateCents`, `formatOfferedDateUtc`, `buildCheckoutUrl`, `buildAuthContinuationUrl`, `buildRegisterContinuationUrl`, `getBookingInvalidReasons`, `getBookingSelectionView`, `ResolvedOption`, `hasStrayOptionParameter`, `resolveSelectedOption`, `BookingWithOptionInput`, `BookingWithOptionView`, `getBookingWithOptionView`.
- [detail-presentation.ts](./detail-presentation.ts#L14) declares `DetailSectionId`, `DetailSection`, `ContentLocale`, `LanguageDisclosure`, `DetailPresentation`, `isUsableText`, `usableEntries`, `hasUsableEntries`.
- [detail-presentation.ts](./detail-presentation.ts#L82) also exposes `hasUsableItinerary`, `hasDifficulty`, `hasGenuineReviewCount`, `isGenuineReviewScore`, `getDetailSections`, `getPresentSectionIds`, `shouldShowContentFallback`, `shouldShowItineraryFallback`, `getLanguageDisclosure`, `getGuideLanguageNames`, `resolveImageAltLocale`, `getContentLanguageName`, `getDetailPresentation`.

## Invariants

- The base booking model requires explicit `is_unavailable: false`, a real offered calendar day strictly after the supplied UTC day, positive safe integer group bounds and participant count, and supported EUR pricing. Estimates are display values and are excluded from checkout URLs. [booking-selection.ts](./booking-selection.ts#L55) [booking-selection.ts](./booking-selection.ts#L80) [booking-selection.ts](./booking-selection.ts#L95) [booking-selection.ts](./booking-selection.ts#L121) [booking-selection.ts](./booking-selection.ts#L185) [booking-selection.ts](./booking-selection.ts#L239)
- Tours with options require a valid current-tour option. Its pricing, availability, and group bounds drive the booking selection. A stray option parameter blocks continuation for an option-less tour. [booking-selection.ts](./booking-selection.ts#L338) [booking-selection.ts](./booking-selection.ts#L413)
- Checkout carries tour slug, count, date, and the selected option ID when present. Login and registration wrap the same locale-safe booking URL in `returnUrl`. [booking-selection.ts](./booking-selection.ts#L185) [booking-selection.ts](./booking-selection.ts#L210) [booking-selection.ts](./booking-selection.ts#L470)
- Optional detail sections require usable content. Review summaries require a positive integer count and a finite average in `(0, 5]` for stars. General content and itinerary language fallbacks are disclosed independently when usable content differs from the page locale. [detail-presentation.ts](./detail-presentation.ts#L63) [detail-presentation.ts](./detail-presentation.ts#L95) [detail-presentation.ts](./detail-presentation.ts#L137) [detail-presentation.ts](./detail-presentation.ts#L175)
- `resolveBookingReasonMessage` maps each model failure to an existing `tour` or `common` catalog key. Change flags select date and count reselection messages. [booking-reasons.ts](./booking-reasons.ts#L29)

## Side Effects

- None

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
