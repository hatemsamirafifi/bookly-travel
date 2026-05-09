# Data Model: Tour Booking

**Feature**: 007-tour-booking
**Date**: 2026-05-09

## Entities

### Booking

The core entity representing a traveler's confirmed reservation.

**Table**: `bookings`

| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| `id` | bigint (PK) | auto-increment | Internal primary key |
| `reference` | string(12) | unique, not null | Human-readable booking reference (BKO-XXXXXX) |
| `traveler_id` | bigint (FK → users.id) | not null, indexed | The traveler who made the booking |
| `tour_id` | bigint (FK → tours.id) | not null, indexed | The booked tour |
| `tour_date` | date | not null, indexed | Date of the tour experience |
| `participant_count` | integer | not null, min:1 | Number of participants |
| `price_per_person` | integer | not null, min:1 | Price per person in smallest currency unit (cents) at confirmation time |
| `total_price` | integer | not null, min:1 | `price_per_person * participant_count` |
| `currency` | string(3) | not null, default:"EUR" | ISO 4217 currency code |
| `status` | string(20) | not null, default:"confirmed" | Booking status |
| `idempotency_key` | string(36) | unique, not null | Client-generated UUID v4 |
| `cancellation_policy` | text | nullable | Snapshot of tour's cancellation policy at booking time |
| `cancellation_window_hours` | integer | nullable | Hours before tour start when cancellation is no longer allowed |
| `cancelled_at` | timestamp | nullable | When the booking was cancelled |
| `cancellation_reason` | text | nullable | Reason for cancellation |
| `confirmation_email_sent_at` | timestamp | nullable | When confirmation email was sent (prevents duplicate sends) |
| `locale` | string(2) | not null, default:"en" | Locale the booking was made in (en/es/it) |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Indexes**:
- `bookings_traveler_id_created_at_idx` on (`traveler_id`, `created_at` DESC) — traveler booking list
- `bookings_tour_id_tour_date_idx` on (`tour_id`, `tour_date`) — partner booking view, availability calculation
- `bookings_status_idx` on (`status`) — filtering by status

**Validation rules**:
- `participant_count` MUST be ≥ 1 and within tour's `group_size_min`–`group_size_max`
- `tour_date` MUST be a future date (≥ today in the tour's timezone)
- `price_per_person` MUST be > 0
- `idempotency_key` MUST be unique across all bookings
- `tour_id` MUST reference a tour with status `published`
- Availability MUST exist: `confirmed` bookings for this tour + date MUST NOT exceed tour capacity (atomic check)

**Statuses**:
- `confirmed` — booking created, traveler committed, payment captured (by spec 008)
- `completed` — traveler attended the tour (marked by partner post-tour)
- `cancelled` — booking cancelled (by traveler before window, or by admin)
- `no_show` — traveler did not attend (marked by partner post-tour)

### BookingAuditLog

Immutable record of every booking status transition or significant event.

**Table**: `booking_audit_logs`

| Field | Type | Constraints | Description |
|-------|------|------------|-------------|
| `id` | bigint (PK) | auto-increment | |
| `booking_id` | bigint (FK → bookings.id) | not null, indexed | Target booking |
| `actor_type` | string(20) | not null | traveler, partner, admin, system |
| `actor_id` | bigint | nullable | ID of the actor (null for system) |
| `action` | string(30) | not null | created, confirmed, completed, cancelled, no_show, anonymized |
| `before_state` | string(20) | nullable | Previous status (null for creation) |
| `after_state` | string(20) | not null | New status |
| `metadata` | jsonb | nullable | Additional context (cancellation reason, anonymization token, etc.) |
| `created_at` | timestamp | not null, indexed | When the event occurred |

**Indexes**:
- `booking_audit_logs_booking_id_created_at_idx` on (`booking_id`, `created_at`) — chronological audit trail
- `booking_audit_logs_actor_type_actor_id_idx` on (`actor_type`, `actor_id`) — filtering by actor

**Immutability**: Application code MUST NOT execute UPDATE or DELETE on this table. Write-once only.

### Availability (Derived)

Not a persisted entity — computed at booking time.

```sql
-- Remaining spots for a tour on a specific date:
-- tour_capacity - SUM(participant_count) WHERE tour_id = ? AND tour_date = ? AND status IN ('confirmed', 'completed')
```

The `SELECT FOR UPDATE` lock during booking creation ensures this calculation is atomic and prevents overbooking.

### Cancellation Policy Snapshot

Captured from the tour at booking time to ensure changes don't affect existing bookings.

| Field | Source | Example |
|-------|--------|---------|
| `cancellation_policy` | `tour.cancellation_policy` | "Free cancellation up to 24 hours before the tour start time." |
| `cancellation_window_hours` | `tour.cancellation_window_hours` | 24 |

## State Transitions

```
                    traveler creates booking
                              │
                              ▼
                        ┌ confirmed ──────────────────────┐
                        │                                  │
                        │ traveler cancels                 │
                        │ (before window)                  │
                        │                                  │
                        ▼                                  │
                     cancelled                             │
               (spots released)                            │
                                                           │
                        ┌ confirmed ──────────────────────┐│
                        │                                  ││
                        │ tour date passes                 ││
                        │ partner marks attendance         ││
                        │                                  ││
                        ▼                                  ▼▼
                   ┌ completed ──────┐              ┌ no_show ──────┐
                   │                 │              │               │
                   │ traveler can    │              │ no review     │
                   │ submit review   │              │ eligibility   │
                   │ (Constitution   │              │               │
                   │  VI)            │              │               │
                   └─────────────────┘              └───────────────┘

      ┌─ 90 days post-tour ─┐             ┌─ 90 days post-tour ─┐
      ▼                      ▼             ▼                      ▼
   completed             cancelled      completed              no_show
   (anonymized)          (anonymized)   (anonymized)           (anonymized)
```

- `confirmed` → `completed`: Partner action, only after tour date has passed
- `confirmed` → `cancelled`: Traveler action (before window) or admin action (any time)
- `confirmed` → `no_show`: Partner action, only after tour date has passed
- Anonymization: Automatic (system), 90 days after tour date, all statuses

## Relationships

```
Tour (spec 003) 1──────* Booking
User (spec 003) 1──────* Booking (as traveler)
Booking         1──────* BookingAuditLog
Payment (spec 008) 1───* Booking (linked via booking_id in payments table)
```
