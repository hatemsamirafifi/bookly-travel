# Reviews

<!-- EGM contract -->

## Overview

Traveler review eligibility, submission and editing, moderation, audit history, and tour rating aggregation.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `EditReviewAction`, `FlagReviewAction`, `GetTravelerReviewsAction`.
- [Controllers](./Controllers/README.md) — Public review submission, editing, and tour review lists, alongside administrative moderation.
- [DTOs](./DTOs/README.md) — Response projection for an individual entry in a traveler's review history.
- [Events](./Events/README.md) — Public declarations include `ReviewFlagged`, `ReviewSubmitted`.
- [Listeners](./Listeners/README.md) — Public declarations include `UpdateTourAggregateRating`.
- [Models](./Models/README.md) — Public declarations include `Review`, `ReviewAuditTrail`.
- [Services](./Services/README.md) — Public declarations include `ProfanityFilterService`, `ReviewValidationService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
