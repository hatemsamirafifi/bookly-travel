# Support

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Search/Support`.

## Interface

- [DestinationAggregator.php](./DestinationAggregator.php#L17) declares `DestinationAggregator`, `DestinationAggregator::popular()`, `DestinationAggregator::extractCountry()`.
- [DurationBucket.php](./DurationBucket.php#L12) declares `DurationBucket`, `DurationBucket::HalfDay`, `DurationBucket::FullDay`, `DurationBucket::MultiDay`, `DurationBucket::halfDayMax()`, `DurationBucket::fullDayMax()`, `DurationBucket::filterExpression()`, `DurationBucket::sqlCase()`.
- [SearchableAttributes.php](./SearchableAttributes.php#L13) declares `SearchableAttributes`, `SearchableAttributes::forLocale()`.

## Invariants

- `DurationBucket` shares the same thresholds between Meilisearch filters and SQL facet counts. Half-day ends at 240 minutes and full-day at 480 minutes. [Bucket filters](./DurationBucket.php#L18) and [SQL counts](./DurationBucket.php#L46).

- Serialized values in [DurationBucket.php](./DurationBucket.php#L14) include `DurationBucket::HalfDay` → `half-day`, `DurationBucket::FullDay` → `full-day`, `DurationBucket::MultiDay` → `multi-day`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
