# Traveler Booking Management API Contract

**Feature**: 007-tour-booking
**Base Path**: `/api/public/my-bookings`

## List My Bookings

**Endpoint**: `GET /api/public/my-bookings`

Returns the authenticated traveler's bookings, ordered by tour date descending.

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |
| `Authorization` | `Bearer {token}` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | no | 1 | Page number |
| `status` | string | no | — | Filter: `confirmed`, `completed`, `cancelled`, `no_show` |

### Response

#### 200 OK

```json
{
  "data": [
    {
      "reference": "BKO-A3XK9M",
      "tour": {
        "slug": "tuscany-wine-tasting",
        "title": "Tuscany Wine Tasting Experience",
        "location": "Florence, Italy",
        "cover_image_url": "https://cdn.bookly.com/tours/42/cover.jpg"
      },
      "tour_date": "2026-06-15",
      "participant_count": 2,
      "total_price": {"amount": 17800, "currency": "EUR", "formatted": "€178.00"},
      "status": "confirmed",
      "created_at": "2026-05-09T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
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

## Get Booking Detail

**Endpoint**: `GET /api/public/my-bookings/{reference}`

Returns full detail for a single booking owned by the authenticated traveler.

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `reference` | string | Booking reference (e.g., `BKO-A3XK9M`) |

### Response

#### 200 OK

```json
{
  "data": {
    "reference": "BKO-A3XK9M",
    "tour": {
      "slug": "tuscany-wine-tasting",
      "title": "Tuscany Wine Tasting Experience",
      "location": "Florence, Italy",
      "meeting_point": "Piazza della Repubblica, Florence",
      "duration": {"minutes": 300, "label": "5 hours"},
      "languages": ["en", "es", "it"]
    },
    "tour_date": "2026-06-15",
    "participant_count": 2,
    "pricing": {
      "price_per_person": {"amount": 8900, "currency": "EUR", "formatted": "€89.00"},
      "total": {"amount": 17800, "currency": "EUR", "formatted": "€178.00"}
    },
    "status": "confirmed",
    "cancellation_policy": "Free cancellation up to 24 hours before the tour start time.",
    "cancellation_window_hours": 24,
    "can_cancel": true,
    "created_at": "2026-05-09T14:30:00Z"
  }
}
```

#### 403 Forbidden

```json
{
  "message": "You do not have access to this booking."
}
```

#### 404 Not Found

```json
{
  "message": "Booking not found."
}
```

---

## Cancel Booking

**Endpoint**: `POST /api/public/my-bookings/{reference}/cancel`

Cancels a confirmed booking, releases availability spots, and triggers refund (spec 008).

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `reference` | string | Booking reference |

### Response

#### 200 OK

```json
{
  "data": {
    "reference": "BKO-A3XK9M",
    "status": "cancelled",
    "cancelled_at": "2026-05-10T08:00:00Z"
  },
  "message": "Booking cancelled. Refund is being processed."
}
```

#### 409 Conflict (within cancellation window)

```json
{
  "message": "This booking cannot be cancelled — the cancellation window (24 hours before tour start) has passed."
}
```

#### 422 Unprocessable Entity (already cancelled/completed)

```json
{
  "message": "Only confirmed bookings can be cancelled."
}
```

## Behavior Notes

- `can_cancel` is true when status is `confirmed` AND the current time is before `tour_date - cancellation_window_hours`.
- Cancelled booking spots are immediately released (re-available for new bookings).
- Refund processing is delegated to spec 008; booking status is set to `cancelled` regardless of refund outcome.
- All booking detail endpoints include the cancellation policy snapshot from booking time.
