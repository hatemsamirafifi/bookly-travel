# CMS

<!-- EGM contract -->

## Overview

Static page delivery, reusable rich-text sanitization, and revision history for editable content.

## Subdirectories & Boundaries

- [Controllers](./Controllers/README.md) — HTTP delivery for localized CMS static pages.
- [Models](./Models/README.md) — Public declarations include `ContentRevision`.
- [Services](./Services/README.md) — Public declarations include `ContentRevisionService`, `HtmlSanitizerService`.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
