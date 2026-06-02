# Contract: Bookings API

**Consumer**: Next.js frontend (authenticated traveler pages)  
**Provider**: Laravel API (`/api/traveler/*`)  
**Authentication**: Laravel Sanctum cookie-based session

---

## 1. List My Bookings

```
GET /api/traveler/bookings
```

### Query Parameters
| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| status    | string | No       | Filter: `confirmed`, `completed`, `cancelled` |
| page      | number | No       | Pagination (default: 1) |
| per_page  | number | No       | Default: 10 |

### Success Response (200)
```json
{
  "data": [
    {
      "id": "uuid",
      "reference": "BOK-2026-ABC123",
      "status": "confirmed",
      "tour": {
        "id": "uuid",
        "name": "Rome Colosseum Underground Tour",
        "cover_image": "https://cdn.example.com/tours/123.jpg",
        "slug": "rome-colosseum-underground-tour",
        "location": "Rome, Italy",
        "duration": "3 hours"
      },
      "booking_date": "2026-05-20T10:30:00Z",
      "tour_date": "2026-06-15",
      "tour_time": "09:00",
      "participants": 2,
      "price_per_person": 7500,
      "total_amount": 15000,
      "created_at": "2026-05-20T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 422  | Invalid query parameter |

---

## 2. Get Booking Detail

```
GET /api/traveler/bookings/{reference}
```

### Success Response (200)
```json
{
  "data": {
    "id": "uuid",
    "reference": "BOK-2026-ABC123",
    "status": "confirmed",
    "tour": {
      "id": "uuid",
      "name": "Rome Colosseum Underground Tour",
      "cover_image": "https://cdn.example.com/tours/123.jpg",
      "slug": "rome-colosseum-underground-tour",
      "location": "Rome, Italy",
      "duration": "3 hours",
      "meeting_point": "Via dei Fori Imperiali, 1",
      "inclusions": ["Skip-the-line ticket", "Expert guide", "Headsets"]
    },
    "booking_date": "2026-05-20T10:30:00Z",
    "tour_date": "2026-06-15",
    "tour_time": "09:00",
    "participants": 2,
    "price_per_person": 7500,
    "total_amount": 15000,
    "special_requests": "Wheelchair accessible route please",
    "cancellation_date": null,
    "created_at": "2026-05-20T10:30:00Z",
    "payment": {
      "status": "paid",
      "amount": 15000,
      "transaction_date": "2026-05-20T10:31:00Z",
      "method": {
        "type": "card",
        "last4": "4242",
        "brand": "visa"
      }
    }
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 404  | Booking not found or does not belong to traveler |

---

## 3. Cancel Booking

```
POST /api/traveler/bookings/{reference}/cancel
```

### Request Body
```json
{
  "reason": "Change of plans"
}
```

### Success Response (200)
```json
{
  "data": {
    "reference": "BOK-2026-ABC123",
    "status": "cancelled",
    "cancellation_date": "2026-05-21T14:00:00Z"
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 403  | Cancellation not allowed (already cancelled / tour in past / within 24h policy) |
| 404  | Booking not found |
| 422  | Validation error (missing reason if required) |

---

## 4. Booking Status Timeline (Optional Enhancement)

```
GET /api/traveler/bookings/{reference}/timeline
```

### Success Response (200)
```json
{
  "data": [
    { "status": "created", "timestamp": "2026-05-20T10:30:00Z", "label": "Booking created" },
    { "status": "payment_received", "timestamp": "2026-05-20T10:31:00Z", "label": "Payment confirmed" },
    { "status": "confirmed", "timestamp": "2026-05-20T10:32:00Z", "label": "Booking confirmed" }
  ]
}
```

*Note: If backend does not expose a dedicated timeline endpoint, the frontend should synthesize the timeline from booking fields (`created_at`, `status`, `cancellation_date`).*
