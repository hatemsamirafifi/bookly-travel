# Bookly Architecture Invariants & Gates

The machine-checkable rules the orchestrator enforces between workflow steps. These exist because each one maps to a high-impact failure in the PRD Risk Register (§20) or a Critical FR. Skipping a gate is how overbooking and ledger drift ship.

## Invariant gates (enforced between steps)

| Gate | Enforced at | Rule | Source |
|------|-------------|------|--------|
| DB-First | Step 3 → 4 | No service/controller task starts until `db-agent` migrations committed | Repo rule + PRD §8 |
| Contract-Before-UI | Step 4 → 5 | No frontend task starts until `contract-agent` artifact exists | PRD §9 seam |
| Idempotency | Step 6 | Booking-create path consumes client idempotency key | FR-004.6 |
| Ledger Immutability | Step 6 | `financial_ledger_entries` has no UPDATE/DELETE path | FR-005.5 |
| Overbooking Lock | Step 6 | Availability decrement uses `FOR UPDATE` or atomic conditional increment | Risk Register |
| i18n Parity | Step 6 | Every new translation key in en, es, it | FR-010.5 |
| Stripe Payment Intents | Step 6 | No `Charge::create`; PaymentIntent API only | FR-005.1 |
| Integer Cents | Step 6 | All money fields are integer cents | PRD §14 |

## How a gate is checked

Each gate is verified by a specific, observable signal — not vibes:

- **DB-First**: a migration file for the FR exists under `backend/database/migrations/` and `db-agent` has sent its schema payload.
- **Contract-Before-UI**: a contract artifact exists at `docs/contracts/<fr>.json` and `contract-agent` has broadcast the publish message.
- **Idempotency**: grep the booking-create action for `idempotency_key` handling; `review-agent` confirms.
- **Ledger Immutability**: grep `financial_ledger_entries` model + any service touching it for `->update(` / `->delete(` / `forceDelete(` — none allowed.
- **Overbooking Lock**: the booking transaction shows `lockForUpdate()` or a `WHERE booked_count + n <= capacity` atomic increment.
- **i18n Parity**: `i18n-agent` verdict == `PARITY_OK`.
- **Stripe Payment Intents**: grep backend for `Charge::create` / `charges()->create` — none allowed.
- **Integer Cents**: schema columns for amounts end in `_cents` and are `integer`/`bigint`, not `decimal`/`float`.

## What a gate failure does

A failed gate does **not** retry the same step. It routes a directive to the owning agent:

| Failed gate | Routed to |
|-------------|-----------|
| DB-First | `db-agent` — emit the missing migration |
| Contract-Before-UI | `contract-agent` — publish the artifact |
| Idempotency / Ledger / Overbooking / Stripe / Cents | `backend-agent` (via `debug-agent` if a test caught it) |
| i18n Parity | `frontend-agent` and/or `backend-agent` (whichever introduced the key) |

The orchestrator re-runs the gate after the owner reports the fix and `test-agent` confirms green. No gate, no progression.

## PRD dependency gate (Step 2)

Before any task breakdown, the orchestrator checks PRD §18 dependency graph. A feature is refused if its prerequisites are not `✅ Done`. Current remaining chain:

```
015 Partner Onboarding → 012 Pricing & Availability → 013 Admin Moderation
014 Notifications & Vouchers (depends on 007 ✅)
```

`prd-validation-agent` performs this check and returns `dependencies_ok`.