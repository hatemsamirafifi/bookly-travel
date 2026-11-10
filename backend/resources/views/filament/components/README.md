# Components

<!-- EGM contract -->

## Overview

Filament field view that binds a HugeRTE editor to Livewire form state.

## Interface

- `filament.components.hugerte-editor` passes the state path, editor ID, toolbar buttons and toolbar profile to the Alpine component. [hugerte-editor.blade.php](./hugerte-editor.blade.php#L1)
- Blade template [hugerte-editor.blade.php](./hugerte-editor.blade.php).

## Invariants

- The Livewire-bound model textarea stays outside the editor's `wire:ignore` subtree. [hugerte-editor.blade.php](./hugerte-editor.blade.php#L9), [hugerte-editor.blade.php](./hugerte-editor.blade.php#L25)

## Side Effects

- Emits deferred browser script tags for HugeRTE and the Bookly adapter once per render. [hugerte-editor.blade.php](./hugerte-editor.blade.php#L34)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
