# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/CMS/Services`.

## Interface

- [ContentRevisionService.php](./ContentRevisionService.php#L19) declares `ContentRevisionService`, `ContentRevisionService::recordRevision()`, `ContentRevisionService::diff()`, `ContentRevisionService::restore()`.
- [HtmlSanitizerService.php](./HtmlSanitizerService.php#L19) declares `HtmlSanitizerService`, `HtmlSanitizerService::sanitize()`, `HtmlSanitizerService::sanitizeLocalizedMap()`.

## Invariants

- `ContentRevisionService::recordRevision` locks the latest revision for the target and excludes timestamps and `lock_version` from its attribute snapshot. [Snapshot and version selection](./ContentRevisionService.php#L25).
- `HtmlSanitizerService::sanitize` normalizes blank input to an empty string and filters inline styles after applying the HTML sanitizer. [Sanitization pipeline](./HtmlSanitizerService.php#L148).

## Side Effects

- `ContentRevisionService::recordRevision` stores the snapshot and diff and advances the target's `lock_version` when that column exists. [Revision write](./ContentRevisionService.php#L43).
- `ContentRevisionService::restore` saves the target's restored attributes and records the restoration. [Restore path](./ContentRevisionService.php#L101).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
