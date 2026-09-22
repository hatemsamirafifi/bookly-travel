# Feature Specification: Stripe Connect and Invoicing Foundation

**Feature Branch**: `feature/stripe-payments-connect-invoicing`

**Created**: 2026-09-19

**Retrospective Documentation**: 2026-09-21

**Status**: Delivered and Constitution v2 Hardening Verified

**Implementation Commit**: `76b1f1e`

**Merge Commit**: `11c534e`

**Constitution**: Bookly Constitution v2.0.0

> This specification was reconstructed from the merged implementation. It
> describes behavior present on `main`; it does not authorize new settlement
> behavior or retroactively claim features that the merge did not deliver.

## Delivery Evidence

- The original merged baseline passed **6 tests and 28 assertions** on
  2026-09-21 against the disposable PostgreSQL service.
- The 2026-09-22 hardening suite additionally verifies integer-only commission
  rounding, invoice retry reuse, governance audit records, and unauthenticated
  and non-partner denial for all three partner Stripe endpoints.
- Hosted invoices now persist their Stripe linkage on Booking and every Stripe
  creation/finalization/void write uses a stable idempotency key.

## Clarifications

### Retrospective decisions

- **Connect model**: Stripe Express connected accounts.
- **Charge model**: Destination charges using
  `transfer_data.destination`, with a configurable platform application fee.
- **Eligibility**: A booking uses the destination-charge path only when its
  tour's partner has a `stripe_account_id` and `stripe_charges_enabled = true`.
  Otherwise, the existing platform charge path remains in use.
- **Invoice boundary**: The merge provides a hosted-invoice service and webhook
  handling foundation. It does not expose an invoice-creation HTTP endpoint or
  a complete invoice-management UI.
- **Settlement boundary**: Bookly-owned earnings records, payout history,
  reconciliation, payout holds/releases, and scheduled settlement jobs are not
  part of this specification. They belong to Spec `019`.

## User Scenarios & Testing

### User Story 1 - Partner connects a Stripe Express account (Priority: P1)

An authenticated partner can inspect their connected-account status, request a
hosted onboarding link, and request a single-use Express Dashboard login link
after an account exists.

**Acceptance Scenarios**:

1. **Given** a partner has no Stripe account, **when** they request onboarding,
   **then** the backend creates an Express account, stores its identifier and
   current capability flags, and returns a Stripe-hosted onboarding URL.
2. **Given** a partner already has a connected account, **when** they request
   onboarding again, **then** the existing account is reused and a fresh account
   link is returned.
3. **Given** a partner requests status, **when** an account exists, **then** the
   backend refreshes charges, payouts, details-submitted, and onboarding flags
   from Stripe before returning the response.
4. **Given** no account exists, **when** the partner requests a dashboard link,
   **then** the API returns `422` and does not invent a dashboard URL.

### User Story 2 - Booking payments route to eligible partners (Priority: P1)

When a booking belongs to a partner whose connected account can accept charges,
the PaymentIntent is created as a destination charge and retains Bookly's
configured application fee.

**Acceptance Scenarios**:

1. **Given** the partner has an enabled connected account, **when** the booking
   PaymentIntent is created, **then** Stripe receives the destination account,
   the calculated application fee, and booking/tour/partner metadata.
2. **Given** no eligible connected account exists, **when** the PaymentIntent is
   created, **then** the existing non-destination payment path is preserved.
3. **Given** the configured commission is `15%` and the total is `20000` minor
   units, **when** the destination payment is created, **then** the application
   fee is `3000` minor units.
4. **Given** the action is retried after a Payment row contains a client secret,
   **when** it executes again, **then** it reuses the existing intent rather than
   creating a duplicate.

### User Story 3 - Stripe state synchronizes through signed webhooks (Priority: P1)

Stripe account and invoice lifecycle events update Bookly's persisted partner,
booking, payment, ledger, and webhook-processing state through the existing
signed, deduplicated webhook pipeline.

**Acceptance Scenarios**:

1. **Given** a matching `account.updated` event, **when** the webhook is
   processed, **then** the connected partner's capability and onboarding flags
   are synchronized.
2. **Given** an `invoice.paid` event with a valid booking reference, **when** it
   is processed, **then** the booking is confirmed, a succeeded Payment is
   present, and the financial ledger records the charge.
3. **Given** the same Stripe event is delivered more than once, **when** it is
   processed, **then** the webhook-event store prevents duplicate effects.

### User Story 4 - Operations can create a hosted invoice in code (Priority: P2)

Application code can use `StripeInvoiceService` to find or create a Stripe
customer, add a booking line item, create and finalize a send-invoice invoice,
and receive its hosted URL and PDF metadata.

**Acceptance Scenarios**:

1. **Given** a booking with a traveler or guest email, **when** the service
   creates an invoice, **then** it returns the invoice identifier, hosted URL,
   PDF URL, status, amount due, and currency.
2. **Given** a booking has no traveler or guest email, **when** invoice creation
   is requested, **then** the service throws an invalid-argument error before
   creating an invoice.
3. **Given** an open or uncollectible invoice identifier, **when** the void
   method is invoked, **then** the service delegates the void operation to
   Stripe.

## Functional Requirements

- **FR-001**: The system MUST persist the connected-account identifier and the
  charges-enabled, payouts-enabled, details-submitted, and onboarding-completed
  flags on the partner record.
- **FR-002**: The partner Stripe API MUST remain protected by Sanctum and the
  existing partner-role middleware.
- **FR-003**: The system MUST create Stripe Express accounts and hosted account
  links without creating a second connected account for a partner that already
  has one.
- **FR-004**: The system MUST generate Express Dashboard login links only for
  partners with a stored connected-account identifier.
- **FR-005**: PaymentIntent creation MUST enable Stripe automatic payment
  methods.
- **FR-006**: Eligible partner bookings MUST use destination charges; ineligible
  bookings MUST retain the existing platform charge behavior.
- **FR-007**: The application fee MUST be calculated from the integer booking
  total using a decimal-string configuration (default `15.00`) converted to
  integer basis points, with half-up integer rounding and no floating-point
  financial arithmetic.
- **FR-008**: Destination account, application fee, and partner identifiers MUST
  be persisted in Payment metadata for traceability.
- **FR-009**: `account.updated` MUST synchronize the matching partner using the
  stored Stripe account identifier.
- **FR-010**: The hosted-invoice service MUST support customer reuse, one booking
  line item, send-invoice creation, finalization, and voiding.
- **FR-011**: The webhook processor MUST handle `invoice.paid`,
  `invoice.payment_failed`, and `invoice.finalized` without bypassing existing
  booking, Payment, or ledger behavior.
- **FR-012**: Stripe webhook processing MUST continue to deduplicate events by
  Stripe event identifier before applying state changes.
- **FR-013**: No implementation in this spec may create Bookly-owned settlement
  records or a second transfer for a booking already routed by a destination
  charge.
- **FR-014**: Connected-account creation and material capability/onboarding
  changes MUST append governance audit records with actor/source and
  before/after state.
- **FR-015**: Hosted-invoice creation MUST reuse a persisted booking invoice on
  retry and MUST send stable Stripe idempotency keys for all write operations.

## Constitution v2 Alignment

- **Financial arithmetic**: `ApplicationFeeCalculator` converts the configured
  decimal percentage to integer basis points and calculates from integer minor
  units with explicit half-up rounding.
- **Governance audit**: Partner-request and Stripe-webhook account changes append
  immutable audit records with before/after snapshots and source metadata.
- **Invoice idempotency**: Booking stores the Stripe invoice linkage; customer,
  line-item, invoice, finalization, and void writes use stable keys, and a retry
  retrieves/finalizes the same invoice.
- **Authorization evidence**: Each Stripe partner endpoint has dedicated 401
  and authenticated-traveler 404 regression cases.

## Success Criteria

- **SC-001**: The PostgreSQL-backed payment and analytics suites plus the
  integer-calculator unit suite pass with no failed assertions.
- **SC-002**: An eligible `20000`-minor-unit booking at the default `15%`
  commission persists a `3000`-minor-unit application fee and the correct
  destination account.
- **SC-003**: `account.updated` synchronizes all four partner Stripe status flags
  for the matching account.
- **SC-004**: `invoice.paid` confirms the booking and produces both a succeeded
  Payment and a ledger charge.
- **SC-005**: The three partner Stripe routes reject unauthenticated requests
  and conceal them from authenticated non-partners.
- **SC-006**: Commission calculation uses no floating-point arithmetic and
  material connected-account state changes create governance audit records.
- **SC-007**: Invoice retries reuse the same invoice result and do not issue a
  second line-item or invoice creation request.

## Out of Scope

- Bookly-owned `partner_earnings` or settlement records.
- Partner payout/settlement history in the Bookly database or UI.
- Admin payout holds, releases, approvals, or batch processing.
- Scheduled transfer or payout jobs.
- Stripe balance-transaction and payout reconciliation.
- Tax reporting and automated tax calculation.
- A dedicated partner frontend for the new Stripe endpoints.
