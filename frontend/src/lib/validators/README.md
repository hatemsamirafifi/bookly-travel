# Validators

<!-- EGM contract -->

## Overview

Zod schemas for customer and partner forms, search sorting, and mapping backend validation errors to authoring controls.

## Interface

- [auth.ts](./auth.ts#L3) declares `passwordSchema`, `loginSchema`, `registerSchema`, `forgotPasswordSchema`, `resetPasswordSchema`, `changePasswordSchema`, `guestConvertSchema`, `partnerRegisterSchema`.
- [booking.ts](./booking.ts#L13) declares `createBookingSchema`, `CreateBookingFormData`.
- [partner.ts](./partner.ts#L5) declares `unicodeCodePointLength`, `pricingTierSchema`, `availabilityRuleSchema`, `availabilityExceptionSchema`, `tourMediaSchema`, `tourItineraryStopSchema`, `tourItineraryDaySchema`, `tourItinerarySchema`.
- [partner.ts](./partner.ts#L123) also exposes `bookingOptionSchema`, `tourBasicDetailsSchema`, `tourPublishSourceSchema`, `tourMediaStepSchema`, `tourPricingStepSchema`, `tourAvailabilityStepSchema`, `isPlaceholderOptionRow`, `getSubmittedOptionRows`, `tourSourceStepSchema`, `tourWizardSchema`, `toFormErrorPath`, `mapServerErrorsToFields`, `pickFieldErrors`, `localizeServerFieldError`, `localizeServerFieldErrors`.
- [profile.ts](./profile.ts#L11) declares `profileSchema`, `ProfileFormData`.
- [review.ts](./review.ts#L3) declares `reviewSchema`, `editReviewSchema`, `ReviewFormData`.
- [search.ts](./search.ts#L12) declares `sortSchema`, `Sort`, `parseSort`.

## Invariants

- New passwords require at least eight characters, uppercase and lowercase letters, and a digit. Reset, change, guest conversion, and partner registration compare the submitted password confirmation. [auth.ts](./auth.ts#L3) [auth.ts](./auth.ts#L26) [auth.ts](./auth.ts#L56)
- Booking validation checks the basic payload shape, positive participant count and option ID, and nonnegative integer page-load price. The backend checks actual capacity, availability, and option ownership. [booking.ts](./booking.ts#L3)
- Tour source schemas use Unicode code-point counts for length limits, cap itinerary days at 30 and stops per day at 20, and validate spoken guide codes case-insensitively for duplicates. [partner.ts](./partner.ts#L3) [partner.ts](./partner.ts#L69) [partner.ts](./partner.ts#L100)
- The pricing step allows up to ten authored options. Submission helpers filter fully empty placeholder rows and keep partially authored rows for validation. [partner.ts](./partner.ts#L205) [partner.ts](./partner.ts#L223)
- Laravel field-error paths strip a supported `translations.<locale>.` prefix and alias option `price_amount` and `group_size_max` to their form names. The first message per field wins. Unmapped messages stay as received. [partner.ts](./partner.ts#L271) [partner.ts](./partner.ts#L289) [partner.ts](./partner.ts#L418)
- `parseSort` trims input and returns `undefined` outside the exact API sort whitelist, which is checked against `SearchParams['sort']` at compile time. [search.ts](./search.ts#L12) [search.ts](./search.ts#L22) [search.ts](./search.ts#L40)

## Side Effects

- None

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
