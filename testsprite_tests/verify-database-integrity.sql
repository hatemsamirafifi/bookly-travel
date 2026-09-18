-- ============================================================================
-- Database Integrity Invariants — DBI-001 through DBI-020
-- ============================================================================
-- PostgreSQL 16. Each query returns exactly two columns: check_id and
-- violations (COUNT(*)). Every invariant must report 0 violations for the
-- database to be considered healthy. The companion verify-database-integrity.py
-- runner fails closed (exit 1) on any violation or psql error.
-- ============================================================================

-- DBI-001: Every booking must carry a traveler or a guest identity.
SELECT 'DBI-001' AS check_id, COUNT(*) AS violations
FROM bookings b
WHERE b.traveler_id IS NULL AND b.guest_identity_id IS NULL;

-- DBI-002: Every booking must reference an existing tour.
SELECT 'DBI-002' AS check_id, COUNT(*) AS violations
FROM bookings b
LEFT JOIN tours t ON t.id = b.tour_id
WHERE t.id IS NULL;

-- DBI-003: Stripe payment intent IDs must be unique across payments.
SELECT 'DBI-003' AS check_id, COUNT(*) AS violations
FROM (
    SELECT stripe_payment_intent_id
    FROM payments
    WHERE stripe_payment_intent_id IS NOT NULL
    GROUP BY stripe_payment_intent_id
    HAVING COUNT(*) > 1
) d;

-- DBI-004: A booking may have at most one charge payment row.
SELECT 'DBI-004' AS check_id, COUNT(*) AS violations
FROM (
    SELECT booking_id
    FROM payments
    WHERE type = 'charge'
    GROUP BY booking_id
    HAVING COUNT(*) > 1
) d;

-- DBI-005: Idempotency keys must be unique across bookings.
SELECT 'DBI-005' AS check_id, COUNT(*) AS violations
FROM (
    SELECT idempotency_key
    FROM bookings
    WHERE idempotency_key IS NOT NULL
    GROUP BY idempotency_key
    HAVING COUNT(*) > 1
) d;

-- DBI-006: At most one ledger entry per booking and entry type.
SELECT 'DBI-006' AS check_id, COUNT(*) AS violations
FROM (
    SELECT booking_id, entry_type
    FROM financial_ledger_entries
    GROUP BY booking_id, entry_type
    HAVING COUNT(*) > 1
) d;

-- DBI-007: Booking status values must come from the defined enum set.
SELECT 'DBI-007' AS check_id, COUNT(*) AS violations
FROM bookings b
WHERE b.status NOT IN (
    'pending_payment', 'confirmed', 'completed',
    'cancelled', 'no_show', 'expired', 'cancellation_requested'
);

-- DBI-008: Payment status values must come from the defined enum set.
SELECT 'DBI-008' AS check_id, COUNT(*) AS violations
FROM payments p
WHERE p.status NOT IN ('pending', 'succeeded', 'refunded', 'failed', 'disputed');

-- DBI-009: Reviews may only exist on completed bookings.
SELECT 'DBI-009' AS check_id, COUNT(*) AS violations
FROM reviews r
JOIN bookings b ON b.id = r.booking_id
WHERE b.status != 'completed';

-- DBI-010: A traveler may review a given tour at most once.
SELECT 'DBI-010' AS check_id, COUNT(*) AS violations
FROM (
    SELECT traveler_id, tour_id
    FROM reviews
    GROUP BY traveler_id, tour_id
    HAVING COUNT(*) > 1
) d;

-- DBI-011: Review responses must come from the tour-owning partner.
SELECT 'DBI-011' AS check_id, COUNT(*) AS violations
FROM review_responses rr
JOIN reviews r ON r.id = rr.review_id
JOIN tours t ON t.id = r.tour_id
WHERE rr.partner_id != t.partner_id;

-- DBI-012: Every tour must have an owning partner.
SELECT 'DBI-012' AS check_id, COUNT(*) AS violations
FROM tours t
LEFT JOIN partners p ON p.id = t.partner_id
WHERE p.id IS NULL;

-- DBI-003/004/005/006/010/016 note: duplicates are aggregated in subqueries
-- so the reported count is the number of violating groups, not rows.

-- DBI-013: Every confirmed booking must have a payment_confirmed audit log.
SELECT 'DBI-013' AS check_id, COUNT(*) AS violations
FROM bookings b
WHERE b.status = 'confirmed'
  AND NOT EXISTS (
      SELECT 1
      FROM booking_audit_logs bal
      WHERE bal.booking_id = b.id
        AND bal.action = 'payment_confirmed'
  );

-- DBI-014: Every paid booking must have a ledger debit entry.
SELECT 'DBI-014' AS check_id, COUNT(*) AS violations
FROM bookings b
WHERE EXISTS (
        SELECT 1
        FROM payments p
        WHERE p.booking_id = b.id
          AND p.status = 'succeeded'
      )
  AND NOT EXISTS (
      SELECT 1
      FROM financial_ledger_entries fle
      WHERE fle.booking_id = b.id
        AND fle.entry_type = 'debit'
  );

-- DBI-015: Every published tour must have at least one availability rule.
SELECT 'DBI-015' AS check_id, COUNT(*) AS violations
FROM tours t
WHERE t.status = 'published'
  AND NOT EXISTS (
      SELECT 1
      FROM availability_rules ar
      WHERE ar.tour_id = t.id
  );

-- DBI-016: Blog post slugs must be unique.
SELECT 'DBI-016' AS check_id, COUNT(*) AS violations
FROM (
    SELECT slug
    FROM blog_posts
    GROUP BY slug
    HAVING COUNT(*) > 1
) d;

-- DBI-017: Tour translations must reference an existing tour.
SELECT 'DBI-017' AS check_id, COUNT(*) AS violations
FROM tour_translations tt
LEFT JOIN tours t ON t.id = tt.tour_id
WHERE t.id IS NULL;

-- DBI-018: Every published tour must have a category.
SELECT 'DBI-018' AS check_id, COUNT(*) AS violations
FROM tours t
WHERE t.status = 'published'
  AND t.category_id IS NULL;

-- DBI-019: Bookings with a traveler_id must reference an existing user.
SELECT 'DBI-019' AS check_id, COUNT(*) AS violations
FROM bookings b
WHERE b.traveler_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM users u
      WHERE u.id = b.traveler_id
  );

-- DBI-020: Pricing tiers must reference an existing tour.
SELECT 'DBI-020' AS check_id, COUNT(*) AS violations
FROM pricing_tiers pt
LEFT JOIN tours t ON t.id = pt.tour_id
WHERE t.id IS NULL;