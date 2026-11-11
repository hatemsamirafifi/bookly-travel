# Config

<!-- EGM contract -->

## Overview

Defines Laravel configuration for persistence, security, search, queues, mail, storage and external service adapters.

## Interface

- Database, cache, queue, and session drivers are selected by `DB_CONNECTION`, `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER`. The code defaults to SQLite for the database and database drivers for the other three. [database.php](./database.php#L20), [cache.php](./cache.php#L18), [queue.php](./queue.php#L16), [session.php](./session.php#L21)
- Scout defaults to Meilisearch and configures Redis `scout` index jobs. [scout.php](./scout.php#L19), [scout.php](./scout.php#L45), [scout.php](./scout.php#L107)
- Stripe, payment gateway selection, Gemini translation, and voucher origin use environment-backed configuration. [services.php](./services.php#L39)
- Spatie settings registers general, SEO, contact and booking settings, reads migrations from `database/settings`, and defaults to a database repository. [settings.php](./settings.php#L20), [settings.php](./settings.php#L35)
- Laravel configuration file [app.php](./app.php).
- Laravel configuration file [auth.php](./auth.php).
- Laravel configuration file [bookings.php](./bookings.php).
- Laravel configuration file [cache.php](./cache.php).
- Laravel configuration file [database.php](./database.php).
- Laravel configuration file [filesystems.php](./filesystems.php).
- Laravel configuration file [logging.php](./logging.php).
- Laravel configuration file [mail.php](./mail.php).
- Laravel configuration file [profanity.php](./profanity.php).
- Laravel configuration file [queue.php](./queue.php).
- Laravel configuration file [sanctum.php](./sanctum.php).
- Laravel configuration file [scout.php](./scout.php).
- Laravel configuration file [services.php](./services.php).
- Laravel configuration file [session.php](./session.php).
- Laravel configuration file [settings.php](./settings.php).

## Invariants

- Sanctum uses the `web` guard and configures a seven-day token expiry. [sanctum.php](./sanctum.php#L17)
- Stripe commission defaults to the decimal string `15.00`, the voucher base URL strips trailing slashes, and bookings without a snapshotted start time have a `09:00` fallback. [services.php](./services.php#L39), [services.php](./services.php#L57), [bookings.php](./bookings.php#L9)
- The local disk uses `storage/app/private` and the public disk uses `storage/app/public`. [filesystems.php](./filesystems.php#L33)

## Side Effects

- Selects where runtime consumers store data or dispatch work, including PostgreSQL/Redis connections, local and S3 disks, and mail transports. Mail defaults to the log transport. [database.php](./database.php#L87), [database.php](./database.php#L146), [filesystems.php](./filesystems.php#L33), [mail.php](./mail.php#L17), [mail.php](./mail.php#L38)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
