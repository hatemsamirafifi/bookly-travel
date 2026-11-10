# Docker

<!-- EGM contract -->

## Overview

Startup permissions and OPcache settings for the PHP container.

## Interface

- `entrypoint.sh` prepares Laravel storage and cache paths, then runs its arguments as the container command. [entrypoint.sh](./entrypoint.sh#L8)
- Source file [entrypoint.sh](./entrypoint.sh).

## Invariants

- The entrypoint exits on command failure. OPcache is enabled with timestamp validation disabled. [entrypoint.sh](./entrypoint.sh#L2), [opcache.ini](./opcache.ini#L15)

## Side Effects

- Creates runtime paths and recursively sets `www-data:www-data` ownership and `775` mode on `storage` and `bootstrap/cache`. [entrypoint.sh](./entrypoint.sh#L8)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
