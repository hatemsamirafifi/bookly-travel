# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Reviews/Actions`.

## Interface

- [EditReviewAction.php](./EditReviewAction.php#L14) declares `EditReviewAction`, `EditReviewAction::execute()`.
- [FlagReviewAction.php](./FlagReviewAction.php#L19) declares `FlagReviewAction`, `FlagReviewAction::execute()`.
- [GetTravelerReviewsAction.php](./GetTravelerReviewsAction.php#L9) declares `GetTravelerReviewsAction`, `GetTravelerReviewsAction::execute()`.
- [HideReviewAction.php](./HideReviewAction.php#L12) declares `HideReviewAction`, `HideReviewAction::execute()`.
- [ReinstateReviewAction.php](./ReinstateReviewAction.php#L12) declares `ReinstateReviewAction`, `ReinstateReviewAction::execute()`.
- [SubmitReviewAction.php](./SubmitReviewAction.php#L14) declares `SubmitReviewAction`, `SubmitReviewAction::execute()`.

## Invariants

- `EditReviewAction::execute` requires the owner to edit within 48 hours. [Review edit guards](./EditReviewAction.php#L20).
- `FlagReviewAction::execute` requires a visible review, a nonblank reason, and an admin actor. `HideReviewAction::execute` and `ReinstateReviewAction::execute` check the review's allowed transitions. [Flag](./FlagReviewAction.php#L23), [hide](./HideReviewAction.php#L16), and [reinstate](./ReinstateReviewAction.php#L16).

## Side Effects

- `SubmitReviewAction::execute` creates a review and dispatches `ReviewSubmitted`, then dispatches `ReviewFlagged` when the comment matches configured terms. [Submission and events](./SubmitReviewAction.php#L21).
- `HideReviewAction::execute` and `ReinstateReviewAction::execute` write the new status and audit records in a transaction and emit `ReviewSubmitted` afterward to trigger aggregate recalculation. [Hide effects](./HideReviewAction.php#L25) and [reinstate effects](./ReinstateReviewAction.php#L25).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
