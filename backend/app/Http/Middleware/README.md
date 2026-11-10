# Middleware

<!-- EGM contract -->

## Overview

Per-request role, admin permission, access token expiry, and public search throttle controls.

## Interface

- [AdminPermissionMiddleware.php](./AdminPermissionMiddleware.php#L10) declares `AdminPermissionMiddleware`, `AdminPermissionMiddleware::handle()`.
- [RateLimitSearchMiddleware.php](./RateLimitSearchMiddleware.php#L21) declares `RateLimitSearchMiddleware`, `RateLimitSearchMiddleware::handle()`, `RateLimitSearchMiddleware::keyFor()`.
- [RefreshTokenExpiry.php](./RefreshTokenExpiry.php#L20) declares `RefreshTokenExpiry`, `RefreshTokenExpiry::handle()`.
- [RoleMiddleware.php](./RoleMiddleware.php#L9) declares `RoleMiddleware`, `RoleMiddleware::handle()`.

## Invariants

- `RoleMiddleware::handle` and `AdminPermissionMiddleware::handle` reject unauthorized requests with 403. [RoleMiddleware.php](./RoleMiddleware.php#L11) [AdminPermissionMiddleware.php](./AdminPermissionMiddleware.php#L14)
- `RateLimitSearchMiddleware::keyFor` scopes the bucket to endpoint type and IP. The one-minute limits are 60 for search, 120 for detail, listing, and homepage, and 10 for sitemap. [RateLimitSearchMiddleware.php](./RateLimitSearchMiddleware.php#L25) [RateLimitSearchMiddleware.php](./RateLimitSearchMiddleware.php#L62) [RateLimitSearchMiddleware.php](./RateLimitSearchMiddleware.php#L82)

## Side Effects

- `RefreshTokenExpiry::handle` saves a new `expires_at` when the current token is a persisted Sanctum `PersonalAccessToken` and `sanctum.expiration` is enabled. [RefreshTokenExpiry.php](./RefreshTokenExpiry.php#L22)
- `RateLimitSearchMiddleware::handle` sets the Laravel locale and records successful throttle admissions. [RateLimitSearchMiddleware.php](./RateLimitSearchMiddleware.php#L38)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
