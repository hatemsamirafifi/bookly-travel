# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Services`.

## Interface

- [BlogCache.php](./BlogCache.php#L13) declares `BlogCache`, `BlogCache::remember()`.
- [PreviewTokenService.php](./PreviewTokenService.php#L8) declares `PreviewTokenService`, `PreviewTokenService::generate()`, `PreviewTokenService::verify()`.

## Invariants

- `PreviewTokenService::verify` checks token structure, exact slug, HMAC SHA-256 signature, and expiration. [Token verification](./PreviewTokenService.php#L36).

## Side Effects

- `BlogCache::remember` uses tagged cache entries when the cache store supports tags. [Cache store selection](./BlogCache.php#L29).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
