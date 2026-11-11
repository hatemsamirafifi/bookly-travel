# Edit

<!-- EGM contract -->

## Overview

Partner tour editor for authored content, booking options, media, and review submission.

## Interface

- `/{locale}/partner/tours/{id}/edit` uses the route tour ID to load the editor and provides a preview toggle and separate review submission action for drafts. [Tour editor](./page.tsx#L258) [Review control](./page.tsx#L712) [Preview toggle](./page.tsx#L1077)
- [page.tsx](./page.tsx#L258) declares `default PartnerTourEditPage`.

## Invariants

- Saves only the tour’s authored language within `translations`, preserving untouched canonical nulls. Unchanged options, cover image, and media are omitted. Dirty media preserves order, cover flags, and alt-text presence. Invalid pending alt text blocks save. [Save guard and payload](./page.tsx#L546)
- Draft restore merges into the snapshot’s source language. Omitted fields keep their values and explicit nulls or empty arrays clear supported fields. Only a not-found response is treated as an absent draft. [Draft restoration](./page.tsx#L497)
- HTTP 422 responses map server paths to localized form errors. [Validation mapping](./page.tsx#L607)

## Side Effects

- Reads the tour and optionally its latest draft, saves through a bearer-authenticated PUT, and submits through POST to the tour’s `/submit` endpoint. Successful writes reload the tour. [Tour fetch](./page.tsx#L357) [Draft fetch](./page.tsx#L501) [Save](./page.tsx#L571) [Submit](./page.tsx#L640)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
