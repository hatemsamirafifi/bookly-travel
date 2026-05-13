# Payment API Contract

**Feature**: 008-payment-processing  
**Date**: 2026-05-11  
**Base Path**: `/api`

---

## POST /api/public/bookings (MODIFIED from spec 007)

Creates a booking with availability reservation and returns a Stripe `client_secret` for frontend payment confirmation.

### Request

```
POST /api/public/bookings
Authorization: Bearer {token}
Idempotency-Key: {uuid}
Content-Type: application/json
```

```json
{
  "tour_slug": "tuscany-wine-tasting",
  "tour_date": "2026-08-15",
  "participant_count": 2,
  "locale": "en",
  "page_load_price": 8900
}
```

### Response — 201 Created

```json
{
  "data": {
    "reference": "BKO-A3K9X2",
    "tour": { "slug": "tuscany-wine-tasting", "title": "Tuscany Wine Tasting", "location": "Tuscany, Italy" },
    "tour_date": "2026-08-15",
    "participant_count": 2,
    "pricing": {
      "price_per_person": { "amount": 8900, "currency": "EUR", "formatted": "€89.00" },
      "total": { "amount": 17800, "currency": "EUR", "formatted": "€178.00" }
    },
    "status": "pending_payment",
    "created_at": "2026-05-11T10:30:00+00:00"
  },
  "payment": {
    "client_secret": "pi_3abc123_secret_xyz789",
    "stripe_publishable_key": "pk_test_..."
  },
  "price_changed": false
}
```

### Response — 409 Conflict (sold out)

```json
{
  "message": "Only 1 spots remaining for this date."
}
```

### Response — 503 Service Unavailable (Stripe down)

```json
{
  "message": "Payment service temporarily unavailable. Please try again.",
  "retry_after": 30
}
```

---

## POST /api/public/webhooks/stripe

Receives and processes Stripe webhook events. This endpoint is unauthenticated but validated via Stripe webhook signature.

### Request

```
POST /api/public/webhooks/stripe
Stripe-Signature: t=1234567890,v1=abc123...
Content-Type: application/json
```

Raw Stripe event payload (not parsed as JSON before signature verification).

### Response — 200 OK

```json
{
  "received": true
}
```

### Response — 400 Bad Request (invalid signature)

```json
{
  "message": "Invalid webhook signature."
}
```

### Supported Event Types

| Event Type | Action |
|------------|--------|
| `payment_intent.succeeded` | Transition booking `pending_payment` → `confirmed`, create debit ledger entry, dispatch confirmation email |
| `payment_intent.payment_failed` | Transition booking `pending_payment` → `expired`, release availability |
| `charge.refunded` | Update payment status to `refunded`, create credit ledger entry |
| `charge.dispute.created` | Update payment status to `disputed`, alert admin |
| `charge.dispute.closed` | Record outcome; require admin review to update payment/booking state |

---

## GET /api/public/my-bookings/{reference} (MODIFIED from spec 007)

Returns booking details including payment information visible to the traveler.

### Response — 200 OK (payment fields added)

```json
{
  "data": {
    "reference": "BKO-A3K9X2",
    "tour": { "slug": "tuscany-wine-tasting", "title": "Tuscany Wine Tasting" },
    "tour_date": "2026-08-15",
    "participant_count": 2,
    "pricing": {
      "price_per_person": { "amount": 8900, "currency": "EUR", "formatted": "€89.00" },
      "total": { "amount": 17800, "currency": "EUR", "formatted": "€178.00" }
    },
    "status": "confirmed",
    "payment": {
      "status": "succeeded",
      "amount": { "amount": 17800, "currency": "EUR", "formatted": "€178.00" },
      "card_last_four": "4242",
      "card_brand": "visa",
      "paid_at": "2026-05-11T10:30:15+00:00"
    },
    "refund": null,
    "can_cancel": true,
    "created_at": "2026-05-11T10:30:00+00:00"
  }
}
```

---

## GET /api/partner/financial-summary

Returns financial summary for the authenticated partner's tours.

### Request

```
GET /api/partner/financial-summary?tour_slug=tuscany-wine-tasting&date_from=2026-01-01&date_to=2026-12-31
Authorization: Bearer {partner_token}
```

### Response — 200 OK

```json
{
  "data": {
    "total_revenue": { "amount": 890000, "currency": "EUR", "formatted": "€8,900.00" },
    "total_refunds": { "amount": 17800, "currency": "EUR", "formatted": "€178.00" },
    "net_revenue": { "amount": 872200, "currency": "EUR", "formatted": "€8,722.00" },
    "booking_count": 52,
    "refund_count": 1,
    "average_booking_value": { "amount": 17115, "currency": "EUR", "formatted": "€171.15" }
  },
  "meta": {
    "date_from": "2026-01-01",
    "date_to": "2026-12-31",
    "tour_slug": "tuscany-wine-tasting"
  }
}
```

---

## GET /api/admin/financial-ledger

Returns paginated financial ledger entries for admin audit.

### Request

```
GET /api/admin/financial-ledger?booking_reference=BKO-A3K9X2&entry_type=debit&date_from=2026-01-01&page=1
Authorization: Bearer {admin_token}
```

### Response — 200 OK

```json
{
  "data": [
    {
      "id": 1,
      "booking_reference": "BKO-A3K9X2",
      "payment_id": 1,
      "entry_type": "debit",
      "amount": { "amount": 17800, "currency": "EUR", "formatted": "€178.00" },
      "actor": "system",
      "description": "Payment captured for booking BKO-A3K9X2",
      "created_at": "2026-05-11T10:30:15+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```
