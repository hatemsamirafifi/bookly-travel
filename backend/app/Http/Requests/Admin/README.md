# Admin

<!-- EGM contract -->

## Overview

Validates administrative review, audit, and financial ledger input.

## Interface

- [AdminReviewActionRequest.php](./AdminReviewActionRequest.php#L7) declares `AdminReviewActionRequest`, `AdminReviewActionRequest::authorize()`, `AdminReviewActionRequest::rules()`.
- [AdminReviewIndexRequest.php](./AdminReviewIndexRequest.php#L7) declares `AdminReviewIndexRequest`, `AdminReviewIndexRequest::authorize()`, `AdminReviewIndexRequest::rules()`.
- [AuditIndexRequest.php](./AuditIndexRequest.php#L7) declares `AuditIndexRequest`, `AuditIndexRequest::authorize()`, `AuditIndexRequest::rules()`.
- [FinancialLedgerIndexRequest.php](./FinancialLedgerIndexRequest.php#L7) declares `FinancialLedgerIndexRequest`, `FinancialLedgerIndexRequest::authorize()`, `FinancialLedgerIndexRequest::rules()`.

## Invariants

- `AuditIndexRequest::rules` restricts actor type and action to named values and requires positive page numbers. [AuditIndexRequest.php](./AuditIndexRequest.php#L14)
- `AdminReviewIndexRequest::authorize` leaves access control to middleware or gates. [AdminReviewIndexRequest.php](./AdminReviewIndexRequest.php#L9)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
