# Research: Tour Booking

**Feature**: 007-tour-booking
**Date**: 2026-05-09

## 1. Idempotency Key Strategy

**Decision**: Client-generated UUID v4, stored in `bookings.idempotency_key` column with a unique index. Server checks for duplicate on receipt; if found, returns the existing booking (200 OK with same response body) rather than 409 Conflict.

**Rationale**: Industry standard (Stripe, AWS, Shopify). Client controls retry semantics — no extra round-trip to fetch a server-generated key. UUID v4 provides sufficient entropy to make collisions statistically impossible. Returning 200 OK on duplicate (rather than 409) follows the "same result for same request" idempotency contract.

**Alternatives considered**:
- Server-generated key: Adds a round-trip; client must store key before submitting. Rejected for UX friction.
- Content-based deduplication (hash of request body): Sensitive to whitespace/encoding changes; same logical request could produce different hashes. Rejected.

## 2. Atomic Availability Checks

**Decision**: Use PostgreSQL `SELECT ... FOR UPDATE` within a transaction for availability deduction. The flow: (1) BEGIN, (2) SELECT available spots with FOR UPDATE lock, (3) validate count, (4) INSERT booking, (5) COMMIT. On conflict, the second transaction's SELECT blocks until the first commits, then reads the updated count and rejects.

**Rationale**: Row-level locking is simpler than distributed locks and sufficient for Phase 1 scale (200–500 concurrent). Advisory locks and Redis-based locking add complexity without proportional benefit at this scale.

**Alternatives considered**:
- Redis-based distributed lock (Redlock): Overkill for Phase 1; adds Redis dependency for correctness.
- Optimistic locking with version column: Requires retry logic in app layer; FOR UPDATE is more straightforward.
- Deferred constraint check: Doesn't work — availability is a derived value, not a row constraint.

## 3. Booking Reference Format

**Decision**: Format `BKO-XXXXXX` where X is an alphanumeric character (uppercase, excluding ambiguous characters I/L/O/0/1). Generated server-side using a random 6-character string from a 28-character alphabet (28^6 = ~481M combinations). Unique constraint on `bookings.reference` with retry-on-collision.

**Rationale**: Short enough for customer communication (email subject lines, SMS), long enough to avoid collisions at Phase 1 scale. Excluding ambiguous characters reduces support inquiries. The "BKO-" prefix makes it recognizable as a Bookly booking reference.

**Alternatives considered**:
- Sequential/numeric only: Predictable (attackers could enumerate). Rejected.
- Full UUID: Too long for verbal communication and email subjects. Rejected.
- Tour code + date + number: Leaks business data; harder to guarantee uniqueness. Rejected.

## 4. Rate Limiting Implementation

**Decision**: Use Laravel's `RateLimiter` facade with Redis backend. Per-endpoint configuration: booking creation (10/min), GET endpoints (120/min). Key pattern: `rate_limit:{endpoint}:{user_id|ip}:{window_timestamp}`. Response: 429 with JSON body and `Retry-After` header.

**Rationale**: Consistent with spec 006 search rate limiting pattern. Redis auto-expires keys after the window, keeping storage bounded. Per-IP tracking for unauthenticated requests prevents abuse before auth; per-user tracking post-auth is more precise.

**Alternatives considered**:
- External API gateway rate limiting (Cloudflare, Kong): Adds infrastructure dependency. Rejected for Phase 1.
- Token bucket algorithm: More complex than fixed-window; fixed-window is sufficient for 10 req/min.

## 5. Audit Log Architecture

**Decision**: Append-only `booking_audit_logs` table. Each row is immutable (no UPDATE/DELETE from application code). Written synchronously within the same transaction as the booking status change to guarantee consistency. Indexed by `booking_id` and `created_at` for efficient chronological retrieval.

**Rationale**: Constitutional requirement (Audit Logging & Operational Governance). Synchronous write ensures no audit gap if the transaction fails. Separate table keeps audit data out of the bookings table, allowing independent retention policies.

**Alternatives considered**:
- Event sourcing with event store: Over-architected for Phase 1. Rejected.
- Soft deletes with history table trigger: PostgreSQL triggers add debugging complexity. Application-level logging is more explicit and testable.

## 6. Email Notification Queuing

**Decision**: Dispatch `SendBookingConfirmationEmail` job to Redis queue (`booking_emails` queue) after booking creation. Job is idempotent (checking a `confirmation_email_sent_at` column on the booking before sending). Email template rendered with localized content per the booking's locale.

**Rationale**: Follows Constitution's Queueing & Async Work policy. Decouples email delivery from booking API response time (SC-001: 5s). Idempotency prevents duplicate emails on job retry.

**Alternatives considered**:
- Synchronous email in request thread: Would violate SC-001 (5s booking target) with SMTP latency. Rejected.
- Third-party email service SDK queue (SendGrid, Mailgun): Adds vendor dependency. Laravel's mail queue is sufficient.

## 7. Cancellation Policy Enforcement

**Decision**: Capture `cancellation_policy` and `cancellation_window_hours` as a snapshot on the booking at creation time. Cancellation eligibility is calculated by comparing `tour_date - now()` against `cancellation_window_hours`. If the window has passed, cancellation is rejected.

**Rationale**: Snapshot prevents policy changes from retroactively affecting existing bookings. Simple comparison avoids timezone complexity — all times stored as UTC with tour date treated as a local date.

**Alternatives considered**:
- Live lookup of tour policy at cancellation time: Policy could change after booking, creating unfair outcomes. Rejected.
- Cancellation tokens/codes: Unnecessary complexity. Rejected.
