# API Contract: Booking & Payment Endpoints

**Feature**: 010-public-frontend | **Base**: `{API_URL}/api`

Booking creation supports both guest (no auth) and authenticated (Sanctum bearer token) users. Payment intent is created server-side and confirmed client-side via Stripe Elements.

---

## POST /bookings

Create a new booking with optional payment intent.

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Accept-Language` | yes | Locale for error messages (en, es, it) |
| `X-Idempotency-Key` | yes | Unique key per attempt; prevents duplicate bookings |

### Request Body

```json
{
  "tour_id": 1,
  "date": "2026-06-01",
  "time_slot": "09:00 AM",
  "participants": 2,
  "guest_name": "Jane Smith",
  "guest_email": "jane@example.com",
  "guest_phone": "+391234567890",
  "special_requests": "Vegetarian meal option",
  "locale": "en"
}
```

### Response (201) — Booking Created (guest checkout step 2 → 3 transition)

```json
{
  "data": {
    "booking_id": 42,
    "payment_intent": {
      "client_secret": "pi_xxx_secret_yyy",
      "amount": 9800,
      "currency": "eur"
    },
    "tour_title": "Colosseum Guided Tour",
    "idempotency_key": "ck_abc123"
  }
}
```

The frontend then calls `stripe.confirmCardPayment(clientSecret)` with the Stripe Elements card data.

### Response (201) — Booking Confirmed (after successful payment)

```json
{
  "data": {
    "booking": {
      "reference": "BK-20260519-0042",
      "tour_title": "Colosseum Guided Tour",
      "date": "2026-06-01",
      "time_slot": "09:00 AM",
      "participants": 2,
      "amount": { "amount": 98.00, "currency": "EUR" },
      "guest_name": "Jane Smith",
      "guest_email": "jane@example.com",
      "meeting_point": "Via dei Fori Imperiali 1, Rome",
      "created_at": "2026-05-19T14:30:00Z"
    }
  }
}
```

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 400 | `{ "message": "Only 3 spots available for this date." }` | Insufficient capacity |
| 400 | `{ "message": "This date is no longer available." }` | Slot taken between selection and booking |
| 422 | `{ "message": "Validation error", "errors": {...} }` | Invalid input |
| 429 | `{ "message": "Too many attempts. Please wait 20 seconds and try again." }` | Rate limited |
| 409 | `{ "message": "Duplicate booking detected." }` | Idempotency key reused with different payload |

---

## POST /availability/check

Verify availability before payment (called after step 1, before step 2).

### Request Body

```json
{
  "tour_id": 1,
  "date": "2026-06-01",
  "time_slot": "09:00 AM",
  "participants": 2
}
```

### Response (200)

```json
{
  "data": {
    "available": true,
    "remaining_capacity": 8,
    "price_per_person": { "amount": 49.00, "currency": "EUR" },
    "total_price": { "amount": 98.00, "currency": "EUR" }
  }
}
```

### Response (200) — Unavailable

```json
{
  "data": {
    "available": false,
    "remaining_capacity": 2,
    "message": "Only 2 spots remaining."
  }
}
```

---

## POST /bookings/{id}/confirm

Confirm payment success from client side (called after `stripe.confirmCardPayment` succeeds).

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `X-Idempotency-Key` | yes | Must match the booking creation key |

### Request Body

```json
{
  "payment_intent_id": "pi_xxx"
}
```

### Response (200)

```json
{
  "data": {
    "booking": {
      "reference": "BK-20260519-0042",
      "tour_title": "Colosseum Guided Tour",
      "date": "2026-06-01",
      "time_slot": "09:00 AM",
      "participants": 2,
      "amount": { "amount": 98.00, "currency": "EUR" },
      "guest_name": "Jane Smith",
      "guest_email": "jane@example.com",
      "meeting_point": "Via dei Fori Imperiali 1, Rome",
      "created_at": "2026-05-19T14:30:00Z"
    }
  }
}
```

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 400 | `{ "message": "Payment failed or expired." }` | Stripe payment not successful |
| 404 | `{ "message": "Booking not found." }` | Invalid booking ID |

---

## Notes for Frontend Developers

- **Idempotency key**: Generated client-side (`crypto.randomUUID()`) at checkout start. Sent with booking creation and payment confirmation. Prevents double-clicks from creating duplicate bookings.
- **Stripe flow**: Backend creates the PaymentIntent; frontend confirms with `stripe.confirmCardPayment()` using the returned `client_secret`. Never create PaymentIntents client-side.
- **Rate limiting**: 3 req/min per IP for booking/payment endpoints (Laravel throttle middleware). Display `Retry-After` header value in error messages.
- **Auth**: Authenticated users send `Authorization: Bearer {token}` with all requests. Guest users omit the header. Auth users get name/email/phone pre-filled from account; guests enter manually.
- **Checkout session persistence**: Before the API call, state is in Zustand + sessionStorage. After API response, the store is updated with `payment_intent_id` and `booking_id`.
