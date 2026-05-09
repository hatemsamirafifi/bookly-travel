# Partner Booking Management API Contract

**Feature**: 007-tour-booking
**Base Path**: `/api/partner/bookings`

## List Partner's Bookings

**Endpoint**: `GET /api/partner/bookings`

Returns bookings for tours owned by the authenticated partner, grouped by tour and date.

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |
| `Authorization` | `Bearer {token}` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `tour_slug` | string | no | — | Filter to a specific tour |
| `status` | string | no | — | Filter: `confirmed`, `completed`, `cancelled`, `no_show` |
| `date_from` | date | no | today | Earliest tour date |
| `date_to` | date | no | — | Latest tour date |
| `page` | integer | no | 1 | Page number |

### Response

#### 200 OK

```json
{
  "data": [
    {
      "reference": "BKO-A3XK9M",
      "traveler_name": "Marco Rossi",
      "tour": {
        "slug": "tuscany-wine-tasting",
        "title": "Tuscany Wine Tasting Experience"
      },
      "tour_date": "2026-06-15",
      "participant_count": 2,
      "total_price": {"amount": 17800, "currency": "EUR", "formatted": "€178.00"},
      "status": "confirmed",
      "created_at": "2026-05-09T14:30:00Z"
    }
  ],
  "aggregates": {
    "total_bookings": 15,
    "by_status": {"confirmed": 10, "completed": 3, "cancelled": 2, "no_show": 0},
    "by_tour": {
      "tuscany-wine-tasting": {"confirmed": 5, "total": 7},
      "florence-walking-tour": {"confirmed": 5, "total": 8}
    }
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 15
  }
}
```

#### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

---

## Update Booking Status

**Endpoint**: `PATCH /api/partner/bookings/{reference}/status`

Transition a booking status. Partners may only transition bookings for their own tours, and only after the tour date has passed.

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `reference` | string | Booking reference |

### Request Body

```json
{
  "status": "completed"
}
```

| Field | Type | Required | Allowed Values |
|-------|------|----------|---------------|
| `status` | string | yes | `completed`, `no_show` |

### Response

#### 200 OK

```json
{
  "data": {
    "reference": "BKO-A3XK9M",
    "status": "completed",
    "previous_status": "confirmed",
    "transitioned_at": "2026-06-15T18:00:00Z"
  }
}
```

#### 403 Forbidden

```json
{
  "message": "You do not own this tour."
}
```

#### 409 Conflict

```json
{
  "message": "Status can only be updated after the tour date has passed."
}
```

```json
{
  "message": "Booking is already completed."
}
```

#### 422 Unprocessable Entity

```json
{
  "message": "Invalid status transition. Valid transitions from 'cancelled': none."
}
```

## Behavior Notes

- Partners can only transition `confirmed` → `completed` or `confirmed` → `no_show`.
- Transitions are only allowed after the tour date (UTC).
- Each transition creates an immutable audit log entry.
- `completed` status enables the traveler to submit a review (Constitution VI).
- Partners cannot transition their own bookings to `cancelled` — only travelers (self-cancel) or admins can cancel.
