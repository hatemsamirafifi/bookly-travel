# Reviews

<!-- EGM contract -->

## Overview

HTTP adapters for public reviews, partner review listings, and admin moderation.

## Interface

- [review-api.ts](./review-api.ts#L68) declares `submitReview`, `editReview`, `fetchTourReviews`, `fetchPartnerReviews`, `AdminReview`, `AdminReviewsResponse`, `fetchAdminReviews`, `hideReview`.
- [review-api.ts](./review-api.ts#L171) also exposes `reinstateReview`.

## Invariants

- Public review calls use `NEXT_PUBLIC_API_URL` or a relative base, attach a bearer token when available, and surface server messages with HTTP status on failure. Partner and admin wrappers use the shared `apiClient`. [review-api.ts](./review-api.ts#L5) [review-api.ts](./review-api.ts#L43) [review-api.ts](./review-api.ts#L94)
- Public tour reviews default to page 1 with five results. Partner and admin lists default to 100 and 20 per page, respectively. [review-api.ts](./review-api.ts#L82) [review-api.ts](./review-api.ts#L103) [review-api.ts](./review-api.ts#L141)

## Side Effects

- Submitting or editing a review issues POST or PUT requests. Hiding and reinstating reviews issue admin POST requests with a moderation reason. [review-api.ts](./review-api.ts#L68) [review-api.ts](./review-api.ts#L162)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
