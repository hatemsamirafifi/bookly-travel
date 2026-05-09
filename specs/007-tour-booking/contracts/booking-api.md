# Booking API Contract

**Feature**: 007-tour-booking
**Base Path**: `/api/public/bookings`

## Create Booking

**Endpoint**: `POST /api/public/bookings`

Creates a new booking with instant confirmation.

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |
| `Authorization` | `Bearer {token}` |
| `Idempotency-Key` | UUID v4 (required) |
| `Accept-Language` | `en`, `es`, or `it` (optional, defaults to `en`) |

### Request Body

```json
{
  "tour_slug": "tuscany-wine-tasting",
  "tour_date": "2026-06-15",
  "participant_count": 2,
  "locale": "en"
}
```

| Field | Type | Required | Constraints |
|-------|------|----------|------------|
| `tour_slug` | string | yes | Must reference a published tour |
| `tour_date` | date | yes | Future date (YYYY-MM-DD) |
| `participant_count` | integer | yes | ≥ 1, within tour group_size limits |
| `locale` | string | yes | `en`, `es`, or `it` |

### Response

#### 201 Created

```json
{
  "data": {
    "id": 142,
    "reference": "BKO-A3XK9M",
    "tour": {
      "slug": "tuscany-wine-tasting",
      "title": "Tuscany Wine Tasting Experience",
      "location": "Florence, Italy"
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
    "created_at": "2026-05-09T14:30:00Z"
  }
}
```

#### 200 OK (Idempotent Retry)

Same body as 201. Returned when an `Idempotency-Key` matches an existing booking.

#### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

#### 403 Forbidden

```json
{
  "message": "Your account is not authorized to make bookings."
}
```

#### 404 Not Found

```json
{
  "message": "Tour not found."
}
```

#### 409 Conflict

```json
{
  "message": "Only 2 spots remaining for this date."
}
```

#### 422 Unprocessable Entity

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "tour_slug": ["The tour slug field is required."],
    "participant_count": ["The participant count must be at least 1."],
    "tour_date": ["Cannot book a tour for a past date."]
  }
}
```

#### 429 Too Many Requests

```json
{
  "message": "Too many booking attempts. Please wait and try again.",
  "retry_after": 52
}
```

**Headers**: `X-RateLimit-Limit: 10`, `X-RateLimit-Remaining: 0`, `Retry-After: 52`

## Behavior Notes

- Real-time availability check uses atomic `SELECT FOR UPDATE` to prevent overbooking.
- Price is captured at confirmation time from the tour's current pricing — not from the search index.
- Idempotency keys persist for the lifetime of the booking; they are never reused.
- Booking reference (`BKO-XXXXXX`) is unique and generated server-side.
- Cancellation policy and window are snapshotted from the tour at booking time.
