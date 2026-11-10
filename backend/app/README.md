# App

<!-- EGM contract -->

## Overview

Laravel application layer containing the domain implementations, HTTP adapters, shared Eloquent models, Filament administration panel, and runtime registration.

## Subdirectories & Boundaries

- [Console](./Console/README.md) — Artisan entry points for partner invitation expiry, scheduled CMS publication, blog permission backfill, and tour translation maintenance.
- [Domains](./Domains/README.md) — Laravel business domains for booking, payment, traveler accounts, partner operations, catalog search, reviews, publishing, media and translation. Each child groups the HTTP, persistence, and application services for its own business capability.
- [Enums](./Enums/README.md) — Backed string enums for tour, partner, and review statuses, user roles, and user locale values.
- [Events](./Events/README.md) — Shared booking email delivery failure event consumed by the operator alert listener.
- [Filament](./Filament/README.md) — Bookly administration UI with model resources and record pages, standalone operations pages, analytics widgets, and reusable form fields.
- [Http](./Http/README.md) — Shared Laravel HTTP boundary for public authentication and account operations, admin rich-text media, request validation, middleware, and JSON response shaping.
- [Jobs](./Jobs/README.md) — Queue jobs for verification mail, password reset mail, and stale guest data anonymization.
- [Listeners](./Listeners/README.md) — Operator alerting when a booking confirmation email exhausts delivery retries.
- [Mail](./Mail/README.md) — Mailables for authentication, traveler bookings and vouchers, partner bookings, and partner onboarding and status changes.
- [Models](./Models/README.md) — Shared Eloquent identity and catalog models, including tours, options, translations, destinations, categories, and auth audits.
- [Policies](./Policies/README.md) — Authorization policies for user self-service and review moderation.
- [Providers](./Providers/README.md) — Registers dependency bindings, model policies, named rate limiters, event listeners, and lifecycle hooks for the Laravel application.
- [Support](./Support/README.md) — Deploys bundled demo media used by seed and administration workflows.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

- `User::canAccessPanel` requires the `admin` role and an active status, treating a missing status as active. [User.php](./Models/User.php#L171)
- Tour projection updates run after commit and recalculate search eligibility from a fresh row. [EventServiceProvider.php](./Providers/EventServiceProvider.php#L107)

## Side Effects

- The application registers model policies, throttles, listeners, and service bindings on startup. [AppServiceProvider.php](./Providers/AppServiceProvider.php#L54) [EventServiceProvider.php](./Providers/EventServiceProvider.php#L44)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
