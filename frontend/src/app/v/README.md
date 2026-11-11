# V

<!-- EGM contract -->

## Overview

Standalone layout for the public `/v/[reference]` voucher link.

## Interface

- [layout.tsx](./layout.tsx#L5) declares `metadata`, `default VoucherLayout`.
- [\[reference\]](./%5Breference%5D/README.md) — Renders a public voucher verification card for a booking reference.

## Invariants

- The root layout sets the HTML language to `en` and emits `noindex, nofollow` metadata. The page below it negotiates its display language separately. [Voucher layout](./layout.tsx#L5) [HTML shell](./layout.tsx#L20) [Page translations](./[reference]/page.tsx#L107)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
