# Pages

<!-- EGM contract -->

## Overview

Lists travelers and exposes account moderation on the traveler detail page.

## Interface

- [ListTravelers.php](./ListTravelers.php#L8) declares `ListTravelers`.
- [ViewTraveler.php](./ViewTraveler.php#L19) declares `ViewTraveler`, `ViewTraveler::getTitle()`.

## Invariants

- `ViewTraveler::getHeaderActions` checks moderation eligibility and traveler role, then uses account status to determine whether to show suspension or reversal. [ViewTraveler.php](./ViewTraveler.php#L34)

## Side Effects

- `ViewTraveler::getHeaderActions` invokes `TravelerModerationService::suspend` or `reactivate` for the selected traveler. [ViewTraveler.php](./ViewTraveler.php#L54)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
