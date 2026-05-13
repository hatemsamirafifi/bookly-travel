# Feature Specification: Payment Processing

**Feature Branch**: `008-payment-processing`  
**Created**: 2026-05-11  
**Status**: Draft  
**Input**: User description: "Phase 8 — Payment processing with Stripe integration, payment capture on booking confirmation, refund on cancellation, immutable financial ledger, and webhook-driven payment lifecycle"

## Clarifications

### Session 2026-05-11

- Q: How should travelers provide their payment details? → A: Stripe Elements — embedded card input on Bookly's booking page (traveler never leaves Bookly).
- Q: Should Payment and FinancialLedgerEntry be separate tables or unified? → A: Two separate tables — `payments` (mutable Stripe state) + `financial_ledger_entries` (immutable append-only audit log).
- Q: When should the Payment Intent be created — before or after availability is reserved? → A: Backend-first — reserve availability, create Payment Intent server-side, return `client_secret` to frontend for Stripe Elements card confirmation.
- Q: Should the booking status model add `pending_payment` or reuse existing statuses? → A: Add `pending_payment` and `expired` as new statuses. Flow: `pending_payment` → `confirmed` (on payment success) or `expired` (on 15-min timeout/payment failure).
- Q: How should `pending_payment` bookings count toward availability? → A: Hold spots — `pending_payment` bookings reserve spots (counted in availability alongside `confirmed`), released only on expiry or payment failure.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pay for a Tour Booking (Priority: P1)

A traveler confirms a booking on Bookly and is seamlessly charged for the full amount. When the traveler clicks "Confirm Booking" (spec 007), the system captures payment using the traveler's payment method. The traveler sees a clear confirmation that their booking has been paid, with a payment receipt visible in their booking details. The charge amount matches exactly what was displayed during the booking process — no surprises.

**Why this priority**: Revenue capture is the fundamental commercial action. Without payment collection, the platform generates no revenue. Every booking must result in a successful charge or a clear failure with the booking not proceeding.

**Independent Test**: Can be fully tested by completing a booking flow, verifying a charge is created, confirming the booking status reflects payment success, and checking that the financial ledger contains an immutable record of the transaction.

**Acceptance Scenarios**:

1. **Given** a traveler confirms a booking for 2 participants at €89/person, **When** the payment is processed, **Then** a charge of €178.00 is captured, the booking status remains `confirmed`, and the traveler sees a payment confirmation with a transaction reference.
2. **Given** a traveler submits a booking, **When** the payment method is declined (insufficient funds, expired card), **Then** the booking is not created, no charge is captured, and the traveler sees a clear error message asking them to update their payment method.
3. **Given** a payment has been captured for a booking, **When** the traveler views their booking details, **Then** the payment amount, currency, last four digits of the card, and transaction date are visible.
4. **Given** a traveler submits the same booking twice (network retry with same idempotency key), **When** the system processes both requests, **Then** only one charge is created and the duplicate request returns the existing booking and payment.
5. **Given** a booking is confirmed and paid, **When** the partner subsequently changes the tour price, **Then** the captured charge amount remains unchanged — the traveler pays the price at the time of booking, not the current price.

---

### User Story 2 - Receive a Refund on Cancellation (Priority: P2)

A traveler cancels an eligible booking and receives an automatic refund. The refund amount matches the original charge, is processed back to the original payment method, and is reflected in both the traveler's booking details and the admin financial audit trail.

**Why this priority**: Refund capability is essential for traveler trust and legally required in many jurisdictions. Cancellation without refund support would violate consumer protection expectations and undermine platform credibility.

**Independent Test**: Can be fully tested by creating a paid booking, cancelling it within the cancellation window, verifying the refund is issued to the original payment method, and checking that both the booking audit trail and financial ledger reflect the refund.

**Acceptance Scenarios**:

1. **Given** a traveler has a confirmed, paid booking eligible for free cancellation, **When** the traveler cancels the booking (spec 007), **Then** a full refund is issued to the original payment method, the refund amount matches the original charge, and the traveler sees the refund status in their booking details.
2. **Given** a refund has been initiated, **When** processing completes (via webhook), **Then** the booking's financial record is updated to `refunded`, the financial ledger contains an immutable refund entry linked to the original charge, and the refund amount cannot be altered after recording.
3. **Given** a traveler cancels a booking, **When** the refund fails (e.g., the payment method can no longer accept refunds), **Then** the booking remains cancelled, an admin alert is raised, and the admin can initiate a manual resolution.
4. **Given** a traveler attempts to cancel the same booking twice, **When** the second cancellation request arrives, **Then** the system returns the existing cancellation result and does not issue a duplicate refund.

---

### User Story 3 - Payment Lifecycle via Webhooks (Priority: P2)

The payment gateway (Stripe) sends webhook events to Bookly as payment states change — charge succeeded, charge failed, refund completed, dispute opened. Bookly processes each webhook idempotently, updating the financial ledger and booking status accordingly, and alerting admins when manual intervention is required.

**Why this priority**: Webhook-driven state management is essential for reliability. Network failures, delayed processing, and asynchronous Stripe events mean the platform cannot rely solely on synchronous API responses. Webhooks are the source of truth for payment state.

**Independent Test**: Can be fully tested by sending simulated Stripe webhook payloads to the webhook endpoint and verifying that the financial ledger, booking status, and admin alerts are updated correctly for each event type.

**Acceptance Scenarios**:

1. **Given** a `payment_intent.succeeded` webhook arrives, **When** the system processes it, **Then** the corresponding payment record is marked as `succeeded`, the booking status is confirmed, and a financial ledger entry is created.
2. **Given** a `charge.refunded` webhook arrives for a cancelled booking, **When** the system processes it, **Then** the payment record is marked as `refunded` and the financial ledger contains a credit entry.
3. **Given** a `charge.dispute.created` webhook arrives, **When** the system processes it, **Then** the payment record is flagged as `disputed`, an admin alert is triggered, and the booking is not automatically cancelled (admin must decide).
4. **Given** a webhook event has already been processed (duplicate delivery), **When** Stripe resends the same event ID, **Then** the system returns a 200 response without re-processing or creating duplicate ledger entries.
5. **Given** a webhook arrives with an invalid Stripe signature, **When** the system receives it, **Then** the request is rejected with 400, no data is modified, and the attempt is logged for security monitoring.

---

### User Story 4 - Admin Financial Audit Trail (Priority: P3)

Platform administrators view the complete financial history for any booking — charges, refunds, disputes, and their lifecycle states. The financial ledger is immutable: entries can only be appended, never modified or deleted. Administrators can filter and export financial records for reconciliation and compliance.

**Why this priority**: Financial auditability is a constitutional requirement (Principle V) and necessary for regulatory compliance, tax reporting, and dispute resolution. Without it, the platform cannot demonstrate financial accountability.

**Independent Test**: Can be fully tested by creating bookings with various payment outcomes, then querying the admin audit API to verify all financial events are present, correctly linked, chronologically ordered, and immutable.

**Acceptance Scenarios**:

1. **Given** a booking has been paid and then refunded, **When** an admin views the booking's audit trail (spec 007), **Then** the `linked_financial_events` array contains both the charge and the refund, each with amount, currency, status, and timestamp.
2. **Given** multiple bookings have been processed, **When** an admin queries the financial ledger with date range and status filters, **Then** the results are correctly filtered, paginated, and include all required financial fields.
3. **Given** a financial ledger entry has been created, **When** any user or system attempts to modify or delete it, **Then** the operation is rejected — financial records are append-only.

---

### User Story 5 - Partner Payout Visibility (Priority: P3)

Partners can see the financial summary for their tours — total revenue, pending payouts, completed payouts, and refund deductions. Partners do not have access to raw payment details (card numbers, Stripe customer IDs) but can see aggregate financial performance per tour and per time period.

**Why this priority**: Partners need visibility into their earnings to trust the platform and manage their business. However, actual automated payouts are out of scope for Phase 1 (per constitution), so this is limited to visibility only.

**Independent Test**: Can be fully tested by creating bookings for a partner's tours, verifying the partner API returns correct revenue totals, and confirming that sensitive payment details are not exposed.

**Acceptance Scenarios**:

1. **Given** a partner has tours with confirmed, paid bookings, **When** the partner views their financial summary, **Then** they see total revenue, booking count, and average booking value per tour — all calculated from confirmed charges only (not pending or failed).
2. **Given** some bookings have been refunded, **When** the partner views their financial summary, **Then** refund amounts are deducted from the total revenue, with the net revenue clearly shown.
3. **Given** a partner views financial details, **When** they inspect individual transactions, **Then** no raw payment method details (card numbers, Stripe IDs, customer payment tokens) are visible — only amounts, dates, and booking references.

### Edge Cases

- What happens when a booking is confirmed but the charge fails asynchronously (via webhook)?  
  → The booking status transitions from `pending_payment` to `expired`, reserved spots are released, and the traveler is notified.
- What happens when Stripe is temporarily unavailable during booking confirmation?  
  → The booking is not created. The system returns a 503 with a retry suggestion. No orphan bookings are created without payment.
- What happens when a refund is partially completed (e.g., original charge was $100, only $80 refunded)?  
  → The system records the partial refund as a separate ledger entry. The booking shows both the original charge and the partial refund.
- What happens when a dispute is resolved in the traveler's favor?  
  → An admin processes the resolution, the financial ledger records the dispute outcome, and the booking remains cancelled.
- What happens during a currency mismatch (booking in EUR, Stripe account in USD)?  
  → Bookly operates in EUR only for Phase 1. All charges and refunds are in EUR. Currency conversion is out of scope.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST capture payment at booking confirmation time using Stripe Payment Intents with Stripe Elements embedded on the booking page, with the charge amount matching the booking's `total_price` field exactly. The traveler never leaves the Bookly site during payment.
- **FR-002**: System MUST create a Stripe Payment Intent server-side *after* successfully reserving availability, with `currency: eur` and the booking's idempotency key to prevent duplicate charges. The `client_secret` is returned to the frontend for Stripe Elements confirmation.
- **FR-003**: The booking flow MUST follow a two-step orchestration: (1) backend reserves availability and creates the booking in `pending_payment` status with a Payment Intent, (2) frontend confirms the Payment Intent via Stripe Elements, (3) Stripe webhook confirms payment success, transitioning the booking to `confirmed`. If payment fails or the traveler abandons, the pending booking and reserved spots are released after a timeout.
- **FR-004**: System MUST issue a full refund to the original payment method when a booking is cancelled within the cancellation window (spec 007), using Stripe's Refund API.
- **FR-005**: System MUST NOT issue refunds automatically for cancellations outside the cancellation window. Such cases MUST be routed to admin review.
- **FR-006**: System MUST receive and process Stripe webhook events for: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `charge.dispute.created`, `charge.dispute.closed`.
- **FR-007**: System MUST verify Stripe webhook signatures using the endpoint secret before processing any webhook payload.
- **FR-008**: System MUST process webhooks idempotently — duplicate event deliveries MUST NOT produce duplicate ledger entries, duplicate refunds, or inconsistent state.
- **FR-009**: System MUST maintain an immutable financial ledger where every charge, refund, and dispute is recorded as an append-only entry. No ledger entry may be updated or deleted by any user or system process.
- **FR-010**: System MUST link every financial ledger entry to its corresponding booking via `booking_id`, enabling the admin audit trail (spec 007 `linked_financial_events`).
- **FR-011**: System MUST expose payment status and receipt information (amount, currency, last four digits of card, transaction date) to the traveler via the booking detail API.
- **FR-012**: System MUST expose a partner financial summary API showing total revenue, refund deductions, net revenue, and booking count per tour — aggregated from confirmed charges only.
- **FR-013**: System MUST NOT expose raw Stripe identifiers (customer IDs, payment method tokens, full card numbers) to travelers or partners. Only admins may see Stripe-level identifiers.
- **FR-014**: System MUST alert administrators when a payment fails after booking confirmation (via webhook) or when a refund fails, using the existing admin notification channel (spec 007 FR-028).
- **FR-015**: System MUST handle Stripe downtime gracefully — if the Stripe API is unreachable during booking confirmation, the system MUST return a retriable error (503) without creating an orphan booking.
- **FR-016**: System MUST support Stripe test mode for development and staging environments, with production keys used only in the production environment.
- **FR-017**: All Stripe API keys and webhook secrets MUST be loaded from environment variables, never hardcoded (per constitution Security Principles).
- **FR-018**: The booking page MUST embed Stripe Elements (via Stripe.js) for card input, creating a Payment Intent client-side and confirming payment without redirecting the traveler away from Bookly. PCI compliance is handled via Stripe.js tokenization (SAQ-A scope).
- **FR-019**: Pending bookings (created during step 1 of the two-step flow) MUST hold/reserve their spots in the availability count. The `AvailabilityService` MUST count both `pending_payment` and `confirmed` bookings toward tour capacity. Pending bookings MUST be automatically expired and their reserved spots released if payment is not confirmed within 15 minutes. A scheduled job or event-driven cleanup MUST handle this to prevent indefinite availability holds.
- **FR-020**: The booking status model MUST be extended with two new statuses: `pending_payment` (availability reserved, awaiting card confirmation) and `expired` (payment not completed within the timeout window). The complete booking lifecycle becomes: `pending_payment` → `confirmed` (payment succeeded) | `expired` (timeout/payment failed) → `completed` | `cancelled` | `no_show`. Only `confirmed` bookings are eligible for cancellation/refund. Both `pending_payment` and `confirmed` bookings count toward tour capacity.

### Key Entities

- **Payment** (`payments` table): Represents a mutable Stripe transaction linked to a booking. Tracks real-time payment state from Stripe. Key attributes: amount (in cents), currency, status (pending, succeeded, failed, refunded, disputed), Stripe payment intent ID, Stripe refund ID (if applicable), booking ID, type (charge/refund), card last four digits, card brand, created timestamp, updated timestamp. This table is updated as Stripe webhooks arrive.
- **FinancialLedgerEntry** (`financial_ledger_entries` table): An immutable, append-only record of every financial event. Each state change in `payments` produces a new ledger entry. Key attributes: entry type (debit/credit), amount, currency, booking ID, payment ID (FK to payments), actor (system/admin), description, created timestamp. This table has no `updated_at` column — rows are never modified or deleted.
- **WebhookEvent** (`stripe_webhook_events` table): A record of every incoming Stripe webhook, used for idempotency and security audit. Key attributes: Stripe event ID (unique), event type, processing status (received, processed, skipped, failed), payload hash, created timestamp.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of confirmed bookings have a corresponding successful payment capture — zero orphan bookings without payment.
- **SC-002**: Refunds are initiated within 5 seconds of eligible booking cancellation and reflected in the financial ledger within 1 minute of Stripe confirmation.
- **SC-003**: Webhook processing achieves 100% idempotency — replaying the same Stripe event 10 times produces exactly 1 ledger entry.
- **SC-004**: Financial ledger maintains 100% immutability — no application code path allows update or deletion of existing entries.
- **SC-005**: Travelers see payment confirmation (amount, card last-four, date) in their booking details within 10 seconds of payment capture.
- **SC-006**: Partners see accurate financial summaries reflecting all charges and refunds for their tours, updated within 5 minutes of the latest transaction.
- **SC-007**: The webhook endpoint processes events within 2 seconds and returns a 200 response to avoid Stripe retry storms.
- **SC-008**: Zero exposure of raw Stripe identifiers or full card numbers to travelers or partners in any API response.
- **SC-009**: All payment failures and refund failures trigger admin alerts within 30 seconds via the existing notification pipeline.
- **SC-010**: System handles Stripe unavailability by returning a retriable error without data corruption — zero orphan bookings or orphan charges.

## Assumptions

- The platform operates in EUR currency only for Phase 1. Multi-currency support is out of scope.
- Stripe is the sole payment gateway for Phase 1. Gateway extensibility is designed for but not implemented.
- Automated partner payouts (Stripe Connect transfers) are out of scope for Phase 1 per constitution. Partners see revenue visibility only.
- The Stripe customer object is created per-traveler on their first booking and reused for subsequent bookings.
- Card-on-file and saved payment method management are out of scope for Phase 1 — travelers enter payment details per booking.
- The booking domain (spec 007) is the sole producer of payment requests. No other domain initiates charges or refunds.
- Stripe test mode is used in development/staging; production keys are isolated via environment variables.
- The existing admin notification pipeline (spec 007 FR-028) is reused for payment failure alerts — no new notification infrastructure is needed.
- Dispute resolution is a manual admin process in Phase 1. Automated dispute evidence submission is out of scope.
- Partial refunds are supported by the ledger but not exposed to travelers in Phase 1 — only full refunds are automated on cancellation.
