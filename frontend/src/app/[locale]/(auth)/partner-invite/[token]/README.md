# [token]

<!-- EGM contract -->

## Overview

Checks a partner invitation before rendering its acceptance form.

## Interface

- `/{locale}/partner-invite/{token}` reads the locale and token from async params and supplies a validated invitation and its token to `PartnerInviteForm`. [Page props](./page.tsx#L15) [Invite form](./page.tsx#L60)
- [page.tsx](./page.tsx#L8) declares `metadata`, `default PartnerInvitePage`.

## Invariants

- The page emits `noindex, nofollow`. A failed or empty invite lookup shows a localized invalid state with a link to standard registration. [Metadata](./page.tsx#L8) [Invalid state](./page.tsx#L34)

## Side Effects

- Calls `validateInviteToken` on the server before rendering the form. [Token validation](./page.tsx#L26)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
