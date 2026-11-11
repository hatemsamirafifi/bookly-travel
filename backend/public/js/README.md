# Js

<!-- EGM contract -->

## Overview

Browser adapter for HugeRTE, Alpine and Livewire in the Laravel admin panel.

## Interface

- `window.BooklyHugeRte` exposes `mount`, `unmount`, `mountAll`, and `commit`. [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L230)
- Source file [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js).

## Invariants

- Mounting skips detached wrappers or wrappers with an existing instance. Editor HTML syncs to the corresponding model textarea. [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L36), [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L94)

## Side Effects

- Loads the local HugeRTE asset if needed, fetches the admin media-picker endpoint, dispatches bubbling `input` and `change` events, and mounts editors on document and Livewire lifecycle events. [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L9), [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L52), [bookly-hugerte-adapter.js](./bookly-hugerte-adapter.js#L220)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
