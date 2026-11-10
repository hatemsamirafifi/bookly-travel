# Auth

<!-- EGM contract -->

## Overview

Localized sign-in, account registration, password recovery, and email verification pages.

## Subdirectories & Boundaries

- [forgot-password](./forgot-password/README.md) — Renders the localized password recovery request form.
- [login](./login/README.md) — Renders the localized sign-in form with optional session recovery context.
- [partner-register](./partner-register/README.md) — Partner signup form under the localized auth prefix.
- [register](./register/README.md) — Localized traveler registration page.
- [reset-password](./reset-password/README.md) — Localized password reset form populated from a recovery link.
- [verify-email](./verify-email/README.md) — Localized email verification status page.

## Interface

- Source entry points. [Login](./login/page.tsx#L41) [Registration](./register/page.tsx#L35) [Recovery](./forgot-password/page.tsx#L30) [Verification](./verify-email/page.tsx#L39)

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
