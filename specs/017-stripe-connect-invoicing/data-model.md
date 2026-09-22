# Data Model: Stripe Connect and Invoicing Foundation

## Partner additions

Migration:
`backend/database/migrations/2026_09_19_100001_add_stripe_connect_columns_to_partners_table.php`

| Field | Type | Default / Nullability | Meaning |
|---|---|---|---|
| `stripe_account_id` | string, unique | nullable | Stripe connected-account identifier. |
| `stripe_charges_enabled` | boolean | `false` | Stripe reports that the account may accept charges. |
| `stripe_payouts_enabled` | boolean | `false` | Stripe reports that payouts are enabled. |
| `stripe_onboarding_completed` | boolean | `false` | Bookly-derived completion flag. |
| `stripe_details_submitted` | boolean | `false` | Stripe reports that required account details were submitted. |

`Partner::isPayoutReady()` returns true only when an account identifier exists
and both charges and payouts are enabled. Payment routing is intentionally
narrower: `CreatePaymentIntentAction` checks the account identifier and
charges-enabled flag.

## Existing Payment metadata

Destination-charge creation persists the following keys in the existing
`payments.metadata` structure when values are present:

| Key | Meaning |
|---|---|
| `client_secret` | Existing retry-compatible PaymentIntent client secret. |
| `destination_account_id` | Connected account selected for the booking. |
| `application_fee_amount` | Bookly fee in integer minor units. |
| `partner_id` | Local partner identifier for traceability. |

Invoice-paid processing stores invoice context in Payment metadata:

- `invoice_id`
- `hosted_invoice_url`
- `invoice_pdf`

## Existing webhook-event data

`stripe_webhook_events` remains the deduplication boundary. Processing inserts
the Stripe event identifier before applying state changes; an existing event
identifier short-circuits repeated delivery.

## Existing booking and ledger data

- `invoice.paid` resolves a Booking by `metadata.booking_reference`.
- It creates or reuses a succeeded charge Payment.
- It confirms the Booking and synchronizes its payment-intent reference.
- It delegates charge recording to the existing `LedgerService`.

## Booking invoice linkage

Migration:
`backend/database/migrations/2026_09_22_100001_add_stripe_invoice_fields_to_bookings_table.php`

| Field | Type | Nullability | Meaning |
|---|---|---|---|
| `stripe_invoice_id` | string, unique | nullable | Stable Stripe invoice linked to this booking. |
| `stripe_invoice_url` | text | nullable | Current Stripe-hosted invoice URL. |
| `stripe_invoice_pdf` | text | nullable | Current invoice PDF URL. |
| `stripe_invoice_status` | string(30) | nullable | Last locally observed Stripe invoice status. |

The invoice identifier is persisted immediately after Stripe invoice creation
and before finalization. A retry retrieves that invoice and only finalizes it
when it is still a draft. Stable booking-scoped idempotency keys protect line
item, invoice, and finalization writes if a remote success occurs before the
local linkage is committed.

## Governance audit records

Connected-account creation and material status synchronization append to the
existing `governance_audit_logs` table. Records include the Partner target,
before/after Stripe state, provider/source metadata, and either the Partner
actor plus requesting user identifier or the trusted `system` webhook actor.

## Intentionally absent

Spec `017` does not add Bookly-owned earnings, settlement, transfer, payout, or
reconciliation entities. Spec `019` must design those entities only after
choosing the Stripe reconciliation source of truth and proving that it cannot
double-transfer destination-charge funds.

The invoice linkage and Stripe-account audit records above are operational
hardening data, not Bookly-owned settlement entities.
