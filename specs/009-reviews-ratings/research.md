# Research: Reviews & Ratings

**Feature**: 009-reviews-ratings | **Date**: 2026-05-13

## 1. Booking "Completed" Status Transition

**Decision**: Implement a `CompleteBookingJob` scheduled task that runs hourly, querying bookings where `status = 'confirmed'` AND `tour_date < now()`. If no-show/cancellation logic already exists, the completion check runs after those gates.

**Rationale**: The booking status `completed` does not yet have an automated transition from `confirmed` after the tour date passes. This is a dependency for reviews. Rather than building a full booking lifecycle engine, a simple scheduled job covers the MVP case.

**Alternatives considered**:
- Real-time status transition via webhook/callback — over-engineered for v1; adds complexity without proportional value.
- Manual admin marking of completed bookings — does not scale and introduces admin bottleneck.
- Database trigger — violates constitution's "no direct DB access" and business logic in services principles.

## 2. Aggregate Rating Calculation Strategy

**Decision**: Store denormalized aggregate values (`average_rating`, `review_count`) directly on the `tours` table (or a `tour_rating_summary` cache). Recalculate on every review write (submit, edit, hide, reinstate) rather than computing on read.

**Rationale**: Tour detail pages are high-traffic reads. Computing averages across all reviews on every page load does not scale. Write-triggered recalculation means O(1) read performance. The number of writes (reviews submitted/edited/modified) is orders of magnitude lower than reads (tour detail page views).

**Alternatives considered**:
- Compute on read with caching (Redis) — adds cache invalidation complexity; still requires computation on cache miss.
- Materialized view — PostgreSQL supports but Laravel migrations would need raw SQL; breaks ORM portability.
- Real-time computation without caching — fails SC-002 performance target at scale.

## 3. Profanity Filter Implementation

**Decision**: Use a static keyword list (JSON file, ~200-300 common profanities across en/es/it) loaded into a `ProfanityFilterService`. Match against word boundaries, case-insensitive. Flag (don't block) matched reviews.

**Rationale**: Simple, dependency-free, works across all three supported languages, and matches the post-moderation model. No third-party API calls or AI/ML infrastructure needed. The keyword list can be extended without code changes.

**Alternatives considered**:
- Third-party API (e.g., WebPurify, Sightengine) — adds latency, cost, and external dependency for a non-critical path.
- ML-based content moderation — extreme over-engineering for MVP; requires training data, model hosting, ongoing maintenance.
- Skip filtering entirely — would require admins to read every review manually, missing the spec requirement for automated flagging.

## 4. Rate Limiting Implementation

**Decision**: Use Laravel's built-in rate limiter (cache driver backed by Redis) with a key of `review-submit:{traveler_id}` and a limit of 10 per hour.

**Rationale**: Laravel's `RateLimiter` facade integrates natively with Redis and is already used elsewhere in the project. No new package needed. The 10/hr limit is generous enough for legitimate use (traveler returning from a trip reviewing multiple tours) while blocking automated abuse.

**Alternatives considered**:
- Custom throttle middleware — reinvents what Laravel already provides.
- IP-based rate limiting — shared IPs (universities, offices) would hit limits unfairly.
- No rate limiting — violates security principle; opens door to spam despite payment verification.

## 5. Review Edit Window & Immutability

**Decision**: Track `edited_at` on the reviews table. Edits are permitted when `edited_at IS NULL OR edited_at > now() - 48 hours`. Actual edits update the review row in-place; audit trail records the pre-edit state via the `review_audit_trails` table. Deletion is prevented at the model level (Eloquent `deleting` event returns false).

**Rationale**: The 48-hour edit window is enforced in application logic, not at the database level. This avoids complex triggers while ensuring consistency. Audit trail preserves edit history for transparency. Model-level deletion prevention mirrors the existing financial ledger immutability pattern from spec 008.

**Alternatives considered**:
- Immutable reviews (no edits) — harsh UX; typos and minor corrections should be possible.
- Append-only edits (new row per edit) — complicates queries, pagination, and aggregate calculations.
- Soft deletes — conflicts with the explicit "no deletion" requirement.

## 6. Review Display: Default Sort Order

**Decision**: Default sort is most recent first (by `created_at DESC`). No secondary sort option exposed in v1.

**Rationale**: Recency is the most intuitive default for review listings. It surfaces fresh experiences and prevents old reviews from dominating. Additional sort options (highest rated, lowest rated) can be added in a future iteration without schema changes.

**Alternatives considered**:
- Highest rated first — creates positive bias; travelers should see balanced recent reviews.
- "Most helpful" (upvote-based) — requires a voting system which is out of scope for v1.

## 7. Partner Response to Reviews (Constitution VI)

**Decision**: Defer partner responses to a future iteration. The constitution permits partner responses, but the spec assumptions explicitly scope it out of v1. The data model supports it (a `review_responses` table can be added later without breaking changes).

**Rationale**: Partner responses add complexity to both backend (new endpoints, notifications) and frontend (threaded UI). It's better to ship review submission and display first, then add responses based on real usage data.

**Alternatives considered**: None — this is a scope deferral, not a design choice.
