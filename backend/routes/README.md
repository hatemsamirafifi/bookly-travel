# Routes

<!-- EGM contract -->

## Overview

Registers web readiness and media routes, schedules recurring Artisan work, and groups the HTTP API route files.

## Interface

- `GET /` returns JSON readiness. `GET /admin/hugerte/media` uses web authentication. [web.php](./web.php#L6)
- The `inspire` Artisan command writes a quote. [console.php](./console.php#L11)
- Source file [console.php](./console.php).
- Source file [web.php](./web.php).
- [api](./api/README.md) — Defines marketplace HTTP route methods and middleware for public, partner, and admin surfaces. Laravel mounts these files under `/api/public`, `/api/partner`, and `/api/admin`.

## Invariants

- Scheduled jobs and commands use non-overlap guards. Pending booking expiry has a ten-minute lock expiry. [console.php](./console.php#L16), [console.php](./console.php#L21), [console.php](./console.php#L37)

## Side Effects

- Schedules booking and guest anonymization daily, pending booking expiration every five minutes, booking completion hourly, partner invitation expiration daily, and scheduled CMS publishing every minute. [console.php](./console.php#L16), [console.php](./console.php#L37)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
