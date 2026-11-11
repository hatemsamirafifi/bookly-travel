# Availability

<!-- EGM contract -->

## Overview

Manages recurring availability rules and one-off exceptions for a partner tour.

## Interface

- `/{locale}/partner/tours/{id}/availability` uses the resolved tour ID for all availability operations. Its client state tracks rules and exceptions separately. [Availability editor](./page.tsx#L30)
- [page.tsx](./page.tsx#L30) declares `default AvailabilityPage`.

## Invariants

- Rule payloads include weekdays for weekly rules. Exception payloads encode optional time and type-dependent capacity or price multiplier. [Rule payload](./page.tsx#L97) [Exception payload](./page.tsx#L134)

## Side Effects

- Uses bearer-authenticated GET, POST, and DELETE calls to the partner availability API. Deletions require browser confirmation. The page refreshes the list after a successful write. [Read](./page.tsx#L59) [Create rule](./page.tsx#L85) [Create exception](./page.tsx#L122) [Delete rule](./page.tsx#L160) [Delete exception](./page.tsx#L178)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
