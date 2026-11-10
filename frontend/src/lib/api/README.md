# Api

<!-- EGM contract -->

## Overview

HTTP transport and typed endpoint adapters for public content, authentication, booking and checkout, traveler accounts, and partner operations.

## Interface

- `apiClient` returns decoded JSON or `undefined` for successful 204, 205, or HEAD responses. It exposes typed errors for 404, 409, 410, 422, and 429. [client.ts](./client.ts#L63) [client.ts](./client.ts#L89) [client.ts](./client.ts#L123)
- `authApi` maps snake_case account responses to the frontend `User` and exposes error codes, field errors, and status through `AuthApiError`. Native abort errors propagate. [auth.ts](./auth.ts#L51) [auth.ts](./auth.ts#L64) [auth.ts](./auth.ts#L78)
- [auth.ts](./auth.ts#L17) declares `User`, `AuthResponse`, `UserApiType`, `AuthApiError`, `authApi`.
- [blog.ts](./blog.ts#L8) declares `getBlogPost`, `getBlogPosts`, `getBlogCategory`, `getBlogPostPreview`.
- [bookings.ts](./bookings.ts#L5) declares `CreateBookingRequest`, `BookingResponse`, `PaymentInfo`, `confirmDeterministicPayment`, `CreateBookingResult`, `createBooking`.
- [categories.ts](./categories.ts#L4) declares `CategoryListResponse`, `getCategories`, `getCategoryTours`.
- [client.ts](./client.ts#L22) declares `getApiBaseUrl`, `buildSearchParams`, `apiClient`, `ApiError`, `NotFoundError`, `ConflictError`, `ValidationError`, `GoneError`.
- [client.ts](./client.ts#L171) also exposes `RateLimitError`.
- [destinations.ts](./destinations.ts#L4) declares `DestinationListResponse`, `getDestinations`, `getDestinationTours`.
- [homepage.ts](./homepage.ts#L4) declares `getHomepageData`.
- [my-bookings.ts](./my-bookings.ts#L1) declares `cancelBooking`, `getBookingDetail`, `getMyBookings`, `getMyBookingsSummary`.
- [pages.ts](./pages.ts#L13) declares `fetchPublicStaticPage`.
- [partner.ts](./partner.ts#L35) declares `getTours`, `getTour`, `createTour`, `updateTour`, `submitTour`, `deleteTour`, `archiveTour`, `saveTourDraft`.
- [partner.ts](./partner.ts#L102) also exposes `getLatestTourDraft`, `getBookings`, `getBooking`, `updateBookingStatus`, `requestBookingCancellation`, `getReviews`, `respondToReview`, `updateReviewResponse`, `getNotifications`, `markNotificationAsRead`, `markAllNotificationsAsRead`, `getAnalytics`, `getProfile`, `updateProfile`, `getPartnerSettings`, `updatePartnerSettings`, `getSignedUploadUrl`, `registerPartner`, `getOnboardingStatus`, `resubmitApplication`, `validateInviteToken`, `completeInvite`.
- [search.ts](./search.ts#L10) declares `toMinorUnits`, `searchTours`.
- [tours.ts](./tours.ts#L5) declares `TourDetailResponse`, `getTourDetail`.
- [traveler.ts](./traveler.ts#L20) declares `BookingSummary`, `getTravelerBookingsSummary`, `getTravelerBookings`, `getTravelerBooking`, `cancelTravelerBooking`, `getTravelerProfile`, `updateTravelerProfile`, `changeTravelerPassword`.
- [traveler.ts](./traveler.ts#L90) also exposes `getTravelerWishlist`, `addTravelerWishlistItem`, `getTravelerWishlistStatus`, `removeTravelerWishlistItem`, `mergeTravelerWishlist`, `getTravelerReviews`, `downloadTravelerBookingVoucher`.
- [types.ts](./types.ts#L1) declares `TourCard`, `TourDetail`, `TourOption`, `BookingOptionSnapshot`, `TourImage`, `EnglishSourceInput`, `PartnerTourMediaInput`, `PartnerPricingTierInput`.
- [types.ts](./types.ts#L127) also exposes `PartnerAvailabilityRuleInput`, `PartnerAvailabilityExceptionInput`, `PartnerBookingOptionInput`, `ContentSourceLocale`, `PartnerTourWritePayload`, `OwnedTranslationStatus`, `TourDraftSnapshot`, `TourItineraryDay`, `PricingInfo`, `AvailabilityInfo`, `ReviewsInfo`, `PublicOperatorSummary`, `SeoMetadata`, `Category`, `Destination`, `SearchParams`, `SearchResponse`, `CuratedRail`, `HomepageData`, `BlogCategorySummary`, `BlogAuthorSummary`, `BlogPostCard`, `BlogPostDetail`, `BlogListResponse`, `BlogDetailResponse`, `BlogCategoryResponse`, `PublicStaticPage`.

## Invariants

- Server API calls prefer `API_INTERNAL_URL`, then `NEXT_PUBLIC_API_URL`, and require a configured base. Browser calls use `NEXT_PUBLIC_API_URL` or same-origin paths. [client.ts](./client.ts#L1)
- The transport adds `Accept-Language` when provided, includes credentials, and treats `requireCsrf` as a compatibility option. Bearer headers are attached by endpoint adapters when a token is available. [client.ts](./client.ts#L63) [partner.ts](./partner.ts#L24) [traveler.ts](./traveler.ts#L11)
- Public search, category, destination, and homepage reads opt into 300-second revalidation. Blog and static-page reads use `no-store`. Tour detail uses request-scoped React memoization with a `no-store` fetch. [search.ts](./search.ts#L17) [categories.ts](./categories.ts#L8) [destinations.ts](./destinations.ts#L8) [homepage.ts](./homepage.ts#L4) [blog.ts](./blog.ts#L8) [pages.ts](./pages.ts#L13) [tours.ts](./tours.ts#L9)
- `searchTours` converts UI major-unit price bounds to integer minor units and omits invalid bounds. Category and destination adapters omit the filter already scoped by the path slug. [search.ts](./search.ts#L4) [categories.ts](./categories.ts#L17) [destinations.ts](./destinations.ts#L17)
- The caller supplies one stable UUID v4 per booking submission and reuses it for retries. The booking adapter sends it as `Idempotency-Key` and includes the selected option ID and page-load price when provided. [bookings.ts](./bookings.ts#L85)
- `apiClient` defaults `Accept` to `application/json`. It merges caller headers after that default, so callers can override it. [Header merge](./client.ts#L63).

## Side Effects

- Endpoint wrappers issue HTTP requests. Booking creation and deterministic confirmation send POST requests, and the blog preview forwards the signed token as a query parameter. [bookings.ts](./bookings.ts#L60) [bookings.ts](./bookings.ts#L92) [blog.ts](./blog.ts#L69)
- Traveler voucher download fetches a same-origin PDF with a bearer header, triggers a browser download, and revokes its object URL. [traveler.ts](./traveler.ts#L136)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
