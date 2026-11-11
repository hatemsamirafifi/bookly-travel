# Js

<!-- EGM contract -->

## Overview

The Laravel-side Vite JavaScript entry currently contains an empty comment.

## Interface

- `app.js` is a Vite input and currently declares no JavaScript symbols. [app.js](./app.js#L1), [Vite input](../../vite.config.js#L8)
- Source file [app.js](./app.js).

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
