# Admin

<!-- EGM contract -->

## Overview

Provides the administrator review moderation panel with filters, pagination and reason-based hide or reinstate actions.

## Interface

- [AdminReviewPanel.tsx](./AdminReviewPanel.tsx#L15) declares `default AdminReviewPanel`.

## Invariants

- Applying or clearing filters resets the page to one. Both moderation actions require a trimmed, nonempty reason. [AdminReviewPanel.tsx](./AdminReviewPanel.tsx#L84) [AdminReviewPanel.tsx](./AdminReviewPanel.tsx#L96)

## Side Effects

- Fetches filtered reviews and calls the hide or reinstate API for the selected review. Successful mutations invalidate the `admin-reviews` query and close the confirmation dialog. [AdminReviewPanel.tsx](./AdminReviewPanel.tsx#L42) [AdminReviewPanel.tsx](./AdminReviewPanel.tsx#L60)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
