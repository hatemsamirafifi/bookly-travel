# Requests

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Requests`.

## Interface

- [RejectTourRequest.php](./RejectTourRequest.php#L10) declares `RejectTourRequest`, `RejectTourRequest::authorize()`, `RejectTourRequest::rules()`.
- [TransitionBookingStatusRequest.php](./TransitionBookingStatusRequest.php#L11) declares `TransitionBookingStatusRequest`, `TransitionBookingStatusRequest::authorize()`, `TransitionBookingStatusRequest::rules()`.
- [UpdateSettingsRequest.php](./UpdateSettingsRequest.php#L16) declares `UpdateSettingsRequest`, `UpdateSettingsRequest::authorize()`, `UpdateSettingsRequest::rules()`.
- [UpdateStaticPageRequest.php](./UpdateStaticPageRequest.php#L16) declares `UpdateStaticPageRequest`, `UpdateStaticPageRequest::authorize()`, `UpdateStaticPageRequest::rules()`.

## Invariants

- `UpdateStaticPageRequest::authorize` requires an authenticated user with `manage_cms`. `UpdateSettingsRequest::authorize` requires `manage_settings`. [CMS authorization](./UpdateStaticPageRequest.php#L18) and [settings authorization](./UpdateSettingsRequest.php#L18).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
