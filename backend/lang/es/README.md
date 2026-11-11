# Es

<!-- EGM contract -->

## Overview

Spanish translation catalogs for Laravel auth email, homepage rails, search and homepage SEO.

## Interface

- The locale exports `emails.verification`, `emails.password_reset`, `emails.account_locked_out`, `homepage.rails`, `search.rate_limit`, `search.durations`, and `seo.homepage` translation groups. [emails.php](./emails.php#L4), [homepage.php](./homepage.php#L4), [search.php](./search.php#L7), [seo.php](./seo.php#L4)
- Laravel translation file [emails.php](./emails.php).
- Laravel translation file [homepage.php](./homepage.php).
- Laravel translation file [search.php](./search.php).
- Laravel translation file [seo.php](./seo.php).

## Invariants

- Auth email catalogs use named placeholders for recipient, platform and timing values. [emails.php](./emails.php#L4)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
