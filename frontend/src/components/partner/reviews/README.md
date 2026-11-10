# Reviews

<!-- EGM contract -->

## Overview

Displays partner review pages and provides a form to create or edit the partner's response.

## Interface

- [index.ts](./index.ts#L1) declares `ReviewList`, `ReviewResponseForm`, `PartnerReviewsDashboard`.
- [PartnerReviewsDashboard.tsx](./PartnerReviewsDashboard.tsx#L32) declares `default PartnerReviewsDashboard`.
- [ReviewList.tsx](./ReviewList.tsx#L22) declares `ReviewList`.
- [ReviewResponseForm.tsx](./ReviewResponseForm.tsx#L19) declares `ReviewResponseForm`.

## Invariants

- The dashboard derives tour filter choices from the current API page and applies tour, rating and response filters to that page. [PartnerReviewsDashboard.tsx](./PartnerReviewsDashboard.tsx#L50) [PartnerReviewsDashboard.tsx](./PartnerReviewsDashboard.tsx#L60)
- The response form trims the submission and chooses create or update based on the existing response. [ReviewResponseForm.tsx](./ReviewResponseForm.tsx#L23) [ReviewResponseForm.tsx](./ReviewResponseForm.tsx#L57)

## Side Effects

- Fetches paginated reviews. A successful response mutation invalidates `partner-reviews` and invokes the optional success callback. [PartnerReviewsDashboard.tsx](./PartnerReviewsDashboard.tsx#L50) [ReviewResponseForm.tsx](./ReviewResponseForm.tsx#L29)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
