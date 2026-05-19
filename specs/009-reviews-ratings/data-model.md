# Data Model: Reviews & Ratings

**Feature**: 009-reviews-ratings | **Date**: 2026-05-13

## Entity Relationship Diagram

```
┌─────────────┐     ┌───────────────┐     ┌─────────────┐
│   Booking    │────<    Review     │─────│    Tour     │
│             │ 1:1 │               │ M:1 │             │
└─────────────┘     └──────┬────────┘     └─────────────┘
                           │ 1:many
                           │
                    ┌──────┴──────────────┐
                    │  ReviewAuditTrail   │
                    └─────────────────────┘
```

## Tables

### reviews

| Column | Type | Constraints | Description |
|--------|------|------------|-------------|
| id | bigint | PK, auto-increment | Unique review identifier |
| booking_id | bigint | FK → bookings.id, UNIQUE, NOT NULL | One review per booking max |
| tour_id | bigint | FK → tours.id, NOT NULL, INDEX | Tour being reviewed |
| traveler_id | bigint | FK → users.id, NOT NULL, INDEX | Traveler who wrote the review |
| rating | tinyint | NOT NULL, CHECK(1-5) | Star rating 1-5 |
| comment | text | nullable, max 2000 chars | Optional written feedback |
| status | varchar(20) | NOT NULL, DEFAULT 'visible' | visible, hidden, flagged |
| locale | varchar(5) | NOT NULL, DEFAULT 'en' | Submission locale (en, es, it) |
| edited_at | timestamp | nullable | Last edit timestamp (null = never edited) |
| created_at | timestamp | NOT NULL | Submission timestamp |
| updated_at | timestamp | NOT NULL | Last update timestamp |

**Indexes**: `(tour_id, status, created_at DESC)` for efficient public review listing queries.
**Unique**: `(booking_id)` — one review per booking.
**Note**: `updated_at` tracks all modifications (edits, status changes). Deletion is prevented at the model level — no `deleted_at` column.

### review_audit_trails

| Column | Type | Constraints | Description |
|--------|------|------------|-------------|
| id | bigint | PK, auto-increment | Unique entry identifier |
| review_id | bigint | FK → reviews.id, NOT NULL, INDEX | Which review |
| actor_type | varchar(20) | NOT NULL | 'traveler', 'admin' |
| actor_id | bigint | NOT NULL | User ID of actor |
| action | varchar(20) | NOT NULL | 'submit', 'edit', 'hide', 'reinstate' |
| old_rating | tinyint | nullable | Pre-action rating (for edits) |
| new_rating | tinyint | nullable | Post-action rating (for edits) |
| old_comment | text | nullable | Pre-action comment (for edits) |
| new_comment | text | nullable | Post-action comment (for edits) |
| reason | varchar(255) | nullable | Moderation reason (for hide/reinstate) |
| created_at | timestamp | NOT NULL | When action occurred |

**Note**: No `updated_at` — audit entries are immutable.

### Existing Tables (modified)

#### tours

| New Column | Type | Description |
|------------|------|-------------|
| average_rating | decimal(3,2) | nullable, default null | Aggregate average of visible reviews |
| review_count | int | NOT NULL, DEFAULT 0 | Count of visible reviews |

**Rationale**: Denormalized on tours table per research decision #2. Updated on review submit/edit/hide/reinstate. Null means no reviews yet (display "No reviews").

## Validation Rules

| Field | Rule |
|-------|------|
| rating | Required, integer, min:1, max:5 |
| comment | Optional, string, max:2000. Empty string is coerced to NULL before storage. |
| booking_id | Must exist, must belong to traveler, must be status="completed", tour_date must be within 30 days, must not already have a review |
| locale | Required, in:en,es,it |

## State Transitions

### Review Status

```
[submit] → visible ──[admin hides]──→ hidden ──[admin reinstates]──→ visible
                │
                └──[profanity match]──→ flagged (still visible publicly)
```

- `visible`: Displayed on tour detail page, counted in aggregate
- `hidden`: Not displayed publicly, excluded from aggregate calculations. Only admins and the review author can see it referenced on their booking detail page.
- `flagged`: Displayed publicly (same as visible), but highlighted in admin panel for review.

### Review Edit Lifecycle

```
[submit] → editable for 48 hours → immutable after 48 hours
```

- Within 48 hours: traveler can edit rating and comment. Each edit creates an audit trail entry.
- After 48 hours: edit endpoint returns 403.

## Relationships

- **Review → Booking**: belongsTo, one-to-one (unique booking_id)
- **Review → Tour**: belongsTo, many-to-one
- **Review → User (traveler)**: belongsTo
- **Review → ReviewAuditTrail**: hasMany
- **Tour → Reviews**: hasMany (visible only for public queries)
