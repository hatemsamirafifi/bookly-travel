# Feature Specification: Tour Booking

**Feature Branch**: `007-tour-booking`  
**Created**: 2026-05-09  
**Status**: In Progress  
**Input**: User description: "Phase 7 — Tour booking flow with instant confirmation, real-time availability validation, and booking lifecycle management"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Book a Tour with Instant Confirmation (Priority: P1)

A traveler has found a tour they want through search or discovery (spec 006) and clicks "Book Now" on the tour detail page. They select a date, specify the number of participants (within the tour's group size limits), review the total price, and confirm the booking. The system validates real-time availability, captures the price at confirmation time, creates the booking, and returns an immediate confirmation with a booking reference — no waiting, no manual approval, no "request to book."

**Why this priority**: Booking is the revenue-generating action of the platform. Without booking, the marketplace is a catalog with no commerce. This is the critical path that converts browsing travelers into paying customers.

**Independent Test**: Can be fully tested by submitting a booking request with valid tour slug, date, and participant count, and verifying an immediate confirmation response with booking ID, status `confirmed`, pricing breakdown, and a booking reference number.

**Acceptance Scenarios**:

1. **Given** a published tour with availability on June 15 and pricing at €89/person, **When** a traveler books 2 participants for June 15, **Then** the system validates availability in real-time, creates a booking with status `confirmed`, returns a booking reference, and deducts 2 spots from June 15 availability.
2. **Given** a traveler attempts to book 4 participants for a tour with only 2 remaining spots on that date, **When** the booking request is submitted, **Then** the system rejects the booking with a clear message ("Only 2 spots remaining for this date") and does not create a partial booking.
3. **Given** a traveler submits a booking, **When** the system processes it, **Then** the price is captured at confirmation time (not from the search index) and the response includes a pricing breakdown showing base price x participants = total.
4. **Given** a booking was confirmed at €89/person, **When** the partner changes the price to €99 after confirmation, **Then** the existing booking's price remains unchanged at €89.
5. **Given** a traveler submits a booking, **When** the same exact booking payload is submitted again (network retry), **Then** the system recognizes the idempotency key and returns the existing confirmed booking rather than creating a duplicate.

---

### User Story 2 - View and Manage My Bookings (Priority: P2)

A traveler logs into Bookly and views their list of bookings — past and upcoming. Each booking shows the tour name, date, number of participants, total paid, and current status. The traveler can view full booking details, download a booking confirmation, and cancel eligible bookings (those far enough in advance per the tour's cancellation policy).

**Why this priority**: Travelers need confidence and control over their purchases. Booking visibility reduces support inquiries ("Did my booking go through?") and cancellation capability builds trust. This is a standalone traveler-facing feature.

**Independent Test**: Can be fully tested by authenticating as a traveler, calling the bookings list endpoint, verifying only that traveler's bookings appear, and testing cancellation eligibility rules.

**Acceptance Scenarios**:

1. **Given** a traveler has made 3 bookings (2 upcoming, 1 past), **When** they view their bookings list, **Then** all 3 bookings appear ordered by date descending, each showing tour name, date, participants, status, and total amount.
2. **Given** a traveler views a specific booking, **When** the detail loads, **Then** all booking information is displayed: tour name, date, participants, individual pricing, total, cancellation policy, booking reference, status, and meeting point.
3. **Given** a booking eligible for cancellation (more than 24 hours before tour start), **When** the traveler cancels, **Then** the booking status transitions to `cancelled`, the spots are released back to availability, and the refund process is triggered (handled by spec 008).
4. **Given** a booking within the cancellation window (less than 24 hours before tour start), **When** the traveler attempts to cancel, **Then** the system rejects the cancellation or informs the traveler that cancellation is not eligible per the tour's policy.
5. **Given** a traveler views another traveler's booking URL directly, **When** the request is processed, **Then** the system returns a 403 Forbidden — travelers can only access their own bookings.

---

### User Story 3 - Booking Lifecycle for Partners (Priority: P3)

A partner views bookings made for their tours. They can see upcoming bookings with traveler counts per date, mark travelers as "checked in" or "no-show" after the tour date, and manage booking status transitions. Partners see aggregated booking counts per tour/date to plan operations.

**Why this priority**: Partners need operational visibility to run their tours. Check-in/no-show marking enables review eligibility (Constitution VI) and accurate attendance tracking. This is partner-facing and independent of the traveler booking flow.

**Independent Test**: Can be fully tested by authenticating as a partner, viewing bookings for their tours, and verifying status transitions (confirmed → completed, confirmed → no_show) with appropriate permission checks.

**Acceptance Scenarios**:

1. **Given** a partner has 15 bookings across 3 tours, **When** they view their bookings dashboard, **Then** only bookings for their tours are visible, grouped by tour and date.
2. **Given** a booking is `confirmed` and the tour date has passed, **When** the partner marks the traveler as "attended," **Then** the booking transitions to `completed` and the traveler becomes eligible to submit a review (per Constitution VI).
3. **Given** a partner attempts to view bookings for a tour owned by another partner, **When** the request is processed, **Then** the system returns a 403 Forbidden.
4. **Given** a partner marks a booking as `no_show`, **When** the transition completes, **Then** an audit log entry is created capturing the actor, action, booking ID, and timestamp.

---

### User Story 4 - Booking Audit Trail & Status Tracking (Priority: P4)

Every booking status change is recorded in an immutable audit log. Administrators can view the full audit trail for any booking — who created it, when it was confirmed, any cancellations (including who initiated them and why), and lifecycle transitions. Financial events tied to the booking (charges, refunds — from spec 008) are also linked in the audit view.

**Why this priority**: Audit trails are a constitution requirement (Audit Logging & Operational Governance) and are essential for dispute resolution, financial reconciliation, and operational transparency. This can be built independently of the other stories but underpins all of them.

**Independent Test**: Can be fully tested by creating a booking, changing its status, and verifying audit log entries exist with correct actor, action, target resource, timestamp, and before/after state.

**Acceptance Scenarios**:

1. **Given** a booking goes through the lifecycle confirmed → completed, **When** an admin views the booking audit trail, **Then** every status transition is logged with actor (traveler/partner/admin/system), action, timestamp, and before/after state.
2. **Given** a booking is cancelled by the traveler, **When** viewing the audit trail, **Then** the cancellation entry includes who cancelled, when, and the cancellation reason.
3. **Given** an admin filters audit logs by partner or date range, **When** the filter applies, **Then** only matching audit entries are returned with pagination support.
4. **Given** a financial event (charge, refund) links to a booking, **When** viewing that booking's audit trail, **Then** the financial events are visible alongside status transitions (read-only from booking domain — the financial records are owned by spec 008).

---

### Edge Cases

- What happens when two travelers simultaneously attempt to book the last remaining spot on a date? The system MUST use atomic availability checks — the first booking succeeds, the second receives a "No availability" rejection. No overbooking is permitted.
- What happens when a booking request arrives with a date that was available moments ago but is now sold out? Real-time availability validation rejects the booking with a specific message: "This date is no longer available. Please select another date."
- What happens when the price changes between the tour detail page load and the booking confirmation? The system captures the price at confirmation time. If the price differs from what was displayed, the traveler is informed of the current price before confirming. The booking always uses the price at confirmation time.
- What happens when the network causes a booking request to be retried but the first attempt actually succeeded? The idempotency key ensures the retry returns the existing confirmed booking — no duplicate charge, no duplicate booking.
- What happens when a traveler submits a booking without being authenticated? The system requires authentication for all booking operations. Unauthenticated requests receive a 401 Unauthorized, and the traveler is redirected to sign in before completing the booking.
- What happens when a traveler books with a participant count outside the tour's min/max group size? The system validates the count against tour constraints and rejects out-of-range values with a message indicating the allowed range.
- What happens when a tour is unpublished or archived after a booking is confirmed for a future date? Existing confirmed bookings remain valid. The traveler can still view and manage their booking. The tour detail page for new visitors returns a 404 or 410 (per spec 006).
- What happens when a traveler's account is deleted or banned after making bookings? Existing bookings are preserved and linked to a deactivated account identifier for audit and financial record integrity. The traveler cannot make new bookings.
- What happens when a booking cancellation triggers a refund but the refund fails? The booking cancellation succeeds and status is set to `cancelled`. The refund failure is logged, and an admin notification is raised for manual resolution. The booking is marked as "cancelled — refund pending."
- What happens when the system receives a booking for a past date? The system rejects it with a validation error: "Cannot book a tour for a past date."
- What happens when the total price calculation overflows or produces an unreasonable value? Input validation on participant count and price ensures totals stay within expected bounds. Suspiciously large participant counts are rejected at validation.
- What happens when a traveler exceeds the booking rate limit? The system responds with 429 Too Many Requests, a message indicating the limit has been reached ("Too many booking attempts. Please wait and try again."), and a Retry-After header indicating when they can retry.
- How is data anonymization handled for past bookings? Ninety days after the tour date, the traveler's personal identifiers (name, email, phone) are replaced with a system-generated anonymous token. The booking reference, financial ledger entry, tour, date, participant count, and status are preserved for audit and operational history. Anonymization is irreversible and logged as an audit event.
- What happens when the confirmation email fails to send (bounced, SMTP error, etc.)? The booking remains confirmed — email delivery is non-transactional. The failed email job is logged and retried per the queue's retry policy (Constitution: retry-safe idempotent jobs). After max retries, the failure is surfaced to admin operators for manual review. The traveler can always access their booking confirmation from "My Bookings."

## Clarifications

### Session 2026-05-09

- Q: Should the booking flow (forms, confirmation page, error messages, notifications) be localized in EN/ES/IT consistent with the public discovery experience? → A: Full localization — booking UI, confirmations, validation errors, and notifications in EN/ES/IT matching the traveler's active locale.
- Q: Should the booking creation endpoint have rate limits? → A: Strict rate limit — 10 booking attempts per minute per IP (unauthenticated) / per user (authenticated), with 429 response and Retry-After header.
- Q: Who generates the idempotency key — client or server? → A: Client-generated — the frontend generates a UUID per booking attempt and sends it as an `Idempotency-Key` header; server validates uniqueness and rejects duplicates with the existing booking response.
- Q: What is the booking data retention policy? → A: Tiered retention — financial ledger entries retained for 7 years (immutable); personal data (traveler name, contact) anonymized 90 days after the tour date; booking metadata (tour, date, participants count) retained indefinitely in de-identified form.
- Q: What notification channels should deliver booking confirmations? → A: Email only — localized booking confirmation email sent via queued job with booking reference, tour details, date, participants, price, cancellation policy, and meeting point.

## Requirements *(mandatory)*

### Functional Requirements

**Booking Creation & Confirmation**

- **FR-001**: System MUST validate availability in real-time (not from search index) before creating a booking, and reject bookings where the requested participant count exceeds remaining availability on that date. *(See also FR-023 for the atomicity implementation requirement.)*
- **FR-002**: System MUST capture the tour price at the moment of booking confirmation — if the price changed since the traveler loaded the tour detail page, the confirmation-time price applies.
- **FR-003**: System MUST require a client-generated UUID as an `Idempotency-Key` header on every booking request. The server MUST store the key with the booking and, on receiving a duplicate key, return the existing confirmed booking response (200 OK) rather than creating a duplicate or returning an error.
- **FR-004**: System MUST return a confirmed booking response synchronously — no "request to book," waitlist, or manual approval flow. The response includes: booking ID, booking reference (human-readable), status (`confirmed`), tour name, date, participant count, pricing breakdown, and cancellation policy summary.
- **FR-005**: System MUST validate that the participant count falls within the tour's defined minimum and maximum group size before confirming.
- **FR-006**: System MUST require authentication for all booking creation requests. Unauthenticated requests receive a 401 and are directed to sign in.
- **FR-007**: System MUST reject bookings for past dates with a validation error.
- **FR-008**: System MUST reject bookings for tours that are not in `published` status (draft, pending_review, rejected, archived) at the time of booking.
- **FR-026**: System MUST dispatch a localized booking confirmation email via a queued job (Redis) upon successful booking creation. The email MUST include: booking reference, tour name, date, participant count, pricing breakdown, cancellation policy summary, and meeting point. Email delivery failure MUST NOT affect the confirmed status of the booking — the booking remains confirmed regardless.
- **FR-027**: System MUST surface a price-change confirmation step to the traveler when the price at confirmation time differs from the price displayed on the tour detail page at the time of page load. The step MUST clearly state the current price and require explicit re-confirmation before the booking is created; the original request MUST NOT be silently confirmed at the new price.
- **FR-028**: When a queued email delivery job has exhausted all configured retry attempts and failed, the system MUST emit an admin-visible alert (log entry at ERROR severity + operator notification via configured channel) so that the undelivered confirmation can be actioned for manual resolution. This MUST NOT block or revert the confirmed booking.
- **FR-009**: System MUST serve the booking flow UI (forms, confirmation page, validation errors, empty states, and action buttons) in the traveler's active locale (EN/ES/IT), consistent with the localized discovery experience defined in spec 006.
- **FR-010**: System MUST enforce a strict rate limit of 10 booking creation attempts per minute per unique identifier (IP for unauthenticated, user ID for authenticated). When exceeded, the system MUST respond with 429 Too Many Requests, a user-friendly message, and a Retry-After header.

**Booking Lifecycle & Status**

- **FR-011**: System MUST track every booking through a defined lifecycle with statuses: `confirmed`, `completed`, `cancelled`, and `no_show`.
- **FR-012**: System MUST allow travelers to cancel a `confirmed` booking only when the cancellation request falls outside the tour's defined cancellation window (e.g., more than 24 hours before tour start). Cancellations within the window are rejected unless the tour policy permits it.
- **FR-013**: System MUST automatically release availability spots when a booking is cancelled, making those spots available for new bookings on that date.
- **FR-014**: System MUST allow partners to transition their own tours' bookings from `confirmed` to `completed` or `no_show` after the tour date has passed.

**Traveler Booking Management**

- **FR-015**: System MUST provide each traveler with a list of their own bookings, ordered by tour date descending, including past and upcoming bookings.
- **FR-016**: System MUST provide each traveler with full detail for any single booking they own, including: tour name, date, participants, per-person and total pricing, status, booking reference, cancellation policy, meeting point, and available actions (view, cancel if eligible).
- **FR-017**: System MUST enforce that travelers can only access their own bookings. Access to another traveler's booking returns a 403 Forbidden.

**Partner Booking Visibility**

- **FR-018**: System MUST provide each partner with a list of bookings for their own tours, grouped by tour and date.
- **FR-019**: System MUST enforce that partners can only view bookings for tours they own. Access to bookings for another partner's tours returns a 403 Forbidden.

**Audit Trail**

- **FR-020**: System MUST create an immutable audit log entry for every booking status transition, capturing: actor (traveler/partner/admin/system), action (created/confirmed/completed/cancelled/no_show), target booking ID, timestamp, and before/after state.
- **FR-021**: System MUST provide administrators with a filterable, paginated view of all booking audit log entries.
- **FR-022**: System MUST link audit log entries to the associated booking, enabling a chronological view of every booking's full lifecycle.

**Atomicity & Data Integrity**

- **FR-023**: System MUST use atomic operations for availability checks to prevent overbooking when multiple travelers attempt to book the same remaining spots concurrently. *(Implements the atomicity mandate referenced by FR-001; uses `SELECT FOR UPDATE` row-level locking via AvailabilityService.)*
- **FR-024**: System MUST maintain referential integrity between bookings and tours — if a tour is archived, existing bookings remain valid and accessible.
- **FR-025**: System MUST apply tiered data retention: financial ledger entries (charges, refunds, pricing) retained immutably for 7 years; personal data (traveler name, contact details) anonymized 90 days after tour completion; de-identified booking metadata (tour ID, date, participant count, status) retained indefinitely for analytics and operational history.
- **FR-029**: System MUST implement a scheduled background job (artisan command or queued job, scheduled daily) that anonymizes personal identifiers (traveler name, email, phone) on bookings where the tour date is more than 90 days in the past, replacing them with a system-generated anonymous token. Anonymization MUST be irreversible, idempotent (re-running on already-anonymized records produces no side effects), and MUST create a `data_anonymized` audit log entry for each affected booking.

**Performance & Accessibility**

- **FR-030**: Booking and my-bookings pages MUST achieve a Lighthouse Performance score ≥ 90, measured against a production-equivalent build. Optimization strategies MUST include: lazy-loaded images, code splitting for the booking flow bundle, and the font loading strategy established in spec 006.
- **FR-031**: Booking flow UI and my-bookings pages MUST comply with WCAG 2.1 Level AA. Compliance MUST be verified by an automated audit and manual keyboard-navigation check covering: focus trap in the booking form, tab-order through ParticipantSelector and Confirm button, screen-reader labels on the participant stepper, color contrast on all status badges, and visible focus indicators throughout.

### Key Entities

- **Booking**: The core entity representing a traveler's reservation. Key attributes: ID, booking reference (human-readable, unique), traveler ID, tour ID, tour date, participant count, price per person (captured at confirmation), total price, currency, status, idempotency key, cancellation policy snapshot, and timestamps. Statuses: `confirmed`, `completed`, `cancelled`, `no_show`.
- **Booking Audit Entry**: An immutable record of a status transition or significant event on a booking. Attributes: actor type (traveler/partner/admin/system), actor ID, action, booking ID, before state, after state, metadata (e.g., cancellation reason), and timestamp.
- **Availability Snapshot**: Represents the available spots for a tour on a specific date. Derived from tour capacity minus confirmed booking participants for that date. Used for real-time validation during booking creation.
- **Booking Cancellation Policy**: Derived from the tour's defined cancellation policy at the time of booking. Captured as a snapshot on the booking so policy changes don't retroactively affect existing bookings.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can complete a booking from confirmation click to confirmed response in under 5 seconds.
- **SC-002**: Zero overbookings occur — the system never confirms more participants than available spots for a tour date, verified through concurrency testing.
- **SC-003**: 99.9% of idempotent retries correctly return the existing booking without creating duplicates or triggering duplicate financial events.
- **SC-004**: 100% of booking status transitions produce a corresponding audit log entry.
- **SC-005**: Travelers can view their booking list in under 1 second and individual booking details in under 1 second.
- **SC-006**: 95% of eligible cancellation attempts succeed on the first try, with availability correctly restored.
- **SC-007**: Partners can view bookings for their tours grouped by tour and date in under 2 seconds for up to 500 bookings.
- **SC-008**: Administrators can retrieve the full audit trail for any booking in under 2 seconds.
- **SC-009**: The booking flow is fully functional in all three supported languages (EN/ES/IT) with no missing translations, broken locale-specific URLs, or hardcoded English text.
- **SC-010**: Rate-limited users receive a clear 429 response with Retry-After header within 100ms of exceeding the limit; legitimate travelers never encounter rate limits during normal booking flow.

## Assumptions

- **Spec 006 dependency**: The booking flow builds directly on the localized routing (`/[locale]/`), i18n infrastructure, font/CSS baseline, and tour detail page CTA defined in spec 006. Spec 006 MUST be implemented before the booking frontend pages are deployed. Booking UI components inherit the Tailwind CSS design system established in spec 006.
- Tour availability, pricing, and cancellation policies are managed by partners through the workflows defined in specs 003 and 004 and are queryable in real-time at booking time.
- Authentication is provided by Laravel Sanctum (specs 003/004). The booking flow requires a valid authenticated session.
- Payment processing (charges, refunds, Stripe integration) is handled separately by spec 008. The booking domain triggers payment events and reacts to payment outcomes but does not own the payment logic.
- The "Book Now" CTA on the tour detail page (spec 006) links to the booking flow defined here. The detail page passes tour slug and selected date to the booking flow.
- The cancellation window is defined per tour by partners (spec 004). If no cancellation policy is set, a default platform policy applies (free cancellation up to 24 hours before tour start).
- Tour group size limits (min/max) are defined per tour (spec 003) and enforced at booking time.
- Review eligibility (Constitution VI) depends on booking status `completed` — the reviews domain (spec 010) reads booking status to gate review submission.
- The system is designed for Phase 1 scale (5,000–10,000 tours, 200–500 concurrent travelers) with atomic availability checks but avoids distributed locking complexity unless scale testing demands it.
- A human-readable booking reference is generated per booking using a short, unique format suitable for customer-facing communication (e.g., "BKO-XXXXXX").
- Partner-facing booking management is part of the partner dashboard (spec 002) and consumes the same backend API as other partner operations.
- Localization of the booking UI follows the same locale-prefixed URL pattern (`/en/`, `/es/`, `/it/`) and i18n infrastructure established in spec 006. Booking notification emails are also localized.
