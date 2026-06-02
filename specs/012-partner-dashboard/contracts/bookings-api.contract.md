# API Contract: Partner Bookings

**Base URL**: `/api/partner` (prefix set in route group)
**Auth**: Bearer Token + `partner` middleware (PartnerRoleMiddleware)
**Middleware**: `auth:sanctum`, `partner` (sets `partner_id` on request, returns 404 for cross-partner)

---

## 1. List Bookings

**Endpoint**: `GET /api/partner/bookings`
**Throttle**: `booking.get`

**Query Parameters**:
- `status` (optional): `'confirmed' | 'completed' | 'cancelled' | 'cancellation_requested'`
- `tour_id` (optional): integer — filters to a specific tour
- `date_from` (optional): date, format `Y-m-d`
- `date_to` (optional): date, format `Y-m-d`
- `search` (optional): string, max 100 — searches by booking reference
- `per_page` (optional): integer, min 1, max 100 (default 20)

**Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "reference": "BOK-ABC123",
      "status": "confirmed",
      "tour": {
        "id": 5,
        "title": "Colosseum Tour",
        "slug": "colosseum-tour",
        "cover_image_url": "https://cdn.bookly.test/uploads/abc.jpg"
      },
      "traveler": {
        "id": 10,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "phone": "+1234567890"
      },
      "booking_date": "2026-06-01",
      "tour_date": "2026-06-15",
      "tour_time": "09:00",
      "participants": [
        {
          "tier_id": 1,
          "tier_name": "Standard",
          "count": 2,
          "price_per_person": 45.00
        }
      ],
      "total_participants": 2,
      "total_amount": 90.00,
      "currency": "EUR",
      "special_requests": "Wheelchair access needed",
      "payment_status": "paid",
      "created_at": "2026-06-01T10:00:00Z",
      "updated_at": "2026-06-01T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

---

## 2. Get Booking Detail

**Endpoint**: `GET /api/partner/bookings/{reference}`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if booking's tour does not belong to authenticated partner.

**Response (200 OK)**: Full `PartnerBooking` object (same structure as list item).

**Error (404)**: Booking not found or does not belong to partner.

---

## 3. Update Booking Status

**Endpoint**: `PATCH /api/partner/bookings/{reference}/status`
**Throttle**: `booking.get`

Used by the `PartnerBookingController` to transition a booking to `completed` or `cancellation_requested`.

**Payload**:
```json
{
  "status": "completed"
}
```

or

```json
{
  "status": "cancellation_requested",
  "cancellation_reason": "Guide is unavailable",
  "evidence": ["https://cdn.bookly.test/evidence1.jpg"]
}
```

**Validation** (via `UpdateBookingStatusRequest`):
- `status`: required, must be `'completed'` or `'cancellation_requested'`
- `cancellation_reason`: required when status is `cancellation_requested`, string, max 2000
- `evidence`: nullable when status is `cancellation_requested`, array of URL strings

**Business Rules**:
- Status can only be updated if the booking is currently `confirmed`.
- Transitioning to `completed` requires the `tour_date` to be in the past or today.

**Response (200 OK)**:
```json
{
  "message": "Status updated."
}
```

**Error (404)**: Booking not found or does not belong to partner.
**Error (409 Conflict)**: Booking is not in `confirmed` status, or tour_date is in the future.

---

## 4. Request Cancellation

**Endpoint**: `POST /api/partner/bookings/{reference}/cancellation-request`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if booking's tour does not belong to authenticated partner.

Standalone endpoint for requesting booking cancellation with reason and optional evidence.

**Payload**:
```json
{
  "reason": "Guide is sick",
  "evidence": ["https://cdn.bookly.test/evidence1.jpg"]
}
```

**Validation**:
- `reason`: required, string, max 2000
- `evidence`: nullable, array of URL strings

**Response (200 OK)**:
```json
{
  "message": "Cancellation request submitted.",
  "reference": "BOK-ABC123"
}
```

**Error (404)**: Booking not found or does not belong to partner.
**Error (409 Conflict)**: Booking is not in a cancellable state.