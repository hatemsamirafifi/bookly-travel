# Domains

<!-- EGM contract -->

## Overview

Laravel business domains for booking, payment, traveler accounts, partner operations, catalog search, reviews, publishing, media and translation. Each child groups the HTTP, persistence, and application services for its own business capability.

## Subdirectories & Boundaries

- [Admin](./Admin/README.md) — Administrative permissions and governance audit, partner and tour moderation, booking transitions, static pages, and platform settings.
- [Auth](./Auth/README.md) — Traveler registration, login and account lockouts, email verification, password recovery, and guest booking linkage.
- [Blog](./Blog/README.md) — Localized article publishing, scheduled visibility, signed previews, categories and author metadata, and blog cache invalidation.
- [Booking](./Booking/README.md) — Booking creation and ownership, departure capacity, status changes, vouchers, public reference verification, and booking audit history.
- [CMS](./CMS/README.md) — Static page delivery, reusable rich-text sanitization, and revision history for editable content.
- [Media](./Media/README.md) — Media upload, asset processing, storage and folder management, and content usage tracking.
- [Partner](./Partner/README.md) — Partner onboarding and account operations, owner-scoped tour editing and review submission, authored tour content and translations, booking access, and partner notifications.
- [Payment](./Payment/README.md) — Payment gateways and Stripe Connect, payment intent and refund flows, Stripe webhook reconciliation, booking payment outcomes, and financial ledger access.
- [Reviews](./Reviews/README.md) — Traveler review eligibility, submission and editing, moderation, audit history, and tour rating aggregation.
- [Search](./Search/README.md) — Public tour discovery and detail, homepage data, destination and category browse, search indexing, and sitemap output.
- [Translation](./Translation/README.md) — Field-level translation dispatch, review and retry state, provider and model provenance, and translated content quality checks.
- [Traveler](./Traveler/README.md) — Traveler account profile operations and authenticated access to the traveler's wishlist and review history.
- [Wishlist](./Wishlist/README.md) — Persistence and application actions for saved tours, with localized tour summaries and per-tour membership status.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
