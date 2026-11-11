# Reviews

<!-- EGM contract -->

## Overview

Displays public tour reviews and aggregates, and provides forms and account views for submitting or editing traveler reviews.

## Interface

- [AggregateRating.tsx](./AggregateRating.tsx#L10) declares `default AggregateRating`.
- [MyReviewCard.tsx](./MyReviewCard.tsx#L15) declares `default MyReviewCard`.
- [MyReviewsList.tsx](./MyReviewsList.tsx#L16) declares `default MyReviewsList`.
- [ReviewCard.tsx](./ReviewCard.tsx#L13) declares `default ReviewCard`.
- [ReviewForm.tsx](./ReviewForm.tsx#L28) declares `default ReviewForm`.
- [ReviewList.tsx](./ReviewList.tsx#L23) declares `default ReviewList`.
- [StarRating.tsx](./StarRating.tsx#L14) declares `default StarRating`.

## Invariants

- The first reviews page replaces the list. Later pages append unseen IDs, stale requests cannot publish, and later-page failures preserve previously loaded results. [ReviewList.tsx](./ReviewList.tsx#L40) [ReviewList.tsx](./ReviewList.tsx#L67)
- The review form selects the edit or create validation schema from `existingReview` and calls the supplied handler with a rating and comment. [ReviewForm.tsx](./ReviewForm.tsx#L39) [ReviewForm.tsx](./ReviewForm.tsx#L65)

## Side Effects

- Fetches public reviews by slug and page, and loads traveler reviews through `useMyReviews`. Saving an account review calls `editReview`, closes the editor and refetches the list. [ReviewList.tsx](./ReviewList.tsx#L48) [MyReviewsList.tsx](./MyReviewsList.tsx#L16) [MyReviewsList.tsx](./MyReviewsList.tsx#L48)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
