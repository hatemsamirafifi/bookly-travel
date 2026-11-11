# Partner register

<!-- EGM contract -->

## Overview

Public partner application form under the locale prefix.

## Interface

- `/{locale}/partner-register` renders `PartnerRegistrationForm` under localized onboarding copy. [Application page](./page.tsx#L33)
- [page.tsx](./page.tsx#L7) declares `generateMetadata`, `default PartnerRegisterPage`.

## Invariants

- Metadata uses `/{locale}/partner-register` as canonical. [Metadata](./page.tsx#L7)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
