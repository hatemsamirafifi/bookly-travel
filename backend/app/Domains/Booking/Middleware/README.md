# Middleware

<!-- EGM contract -->

## Overview

Local source in `RateLimitBookingMiddleware.php`.

## Interface

- [RateLimitBookingMiddleware.php](./RateLimitBookingMiddleware.php#L9) declares `RateLimitBookingMiddleware`, `RateLimitBookingMiddleware::handle()`.

## Invariants

- `RateLimitBookingMiddleware::handle` applies a 60-second limit of 10 attempts for the `create` tier and 120 for other tiers. Keys use the tier and user ID or client IP. Rejected requests receive HTTP 429 and `Retry-After`. [Limit and response](./RateLimitBookingMiddleware.php#L11) and [key](./RateLimitBookingMiddleware.php#L33).

## Side Effects

- `RateLimitBookingMiddleware::handle` increments the rate limiter before forwarding an accepted request. [Counter update](./RateLimitBookingMiddleware.php#L28).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
