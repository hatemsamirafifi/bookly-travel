# Partner register

<!-- EGM contract -->

## Overview

Partner signup form under the localized auth prefix.

## Interface

- `/{locale}/auth/partner-register` passes the optional `returnUrl` to `PartnerRegisterForm`. [Signup page](./page.tsx#L27)
- [page.tsx](./page.tsx#L6) declares `generateMetadata`, `default PartnerRegisterPage`.

## Invariants

- Metadata uses `/{locale}/auth/partner-register` as canonical. [Metadata](./page.tsx#L6)

## Side Effects

None recorded in this contract.

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
