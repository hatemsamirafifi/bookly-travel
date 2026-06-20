# API Contract: Partner Reviews

**Base URL**: `/api/partner` (prefix set in route group)
**Auth**: Bearer Token + `partner` middleware (PartnerRoleMiddleware)
**Middleware**: `auth:sanctum`, `partner` (sets `partner_id` on request, returns 404 for cross-partner)

---

## 1. List Reviews

**Endpoint**: `GET /api/partner/reviews`
**Throttle**: `booking.get`

**Query Parameters**:
- `tour_id` (optional): integer — filter reviews for a specific tour
- `rating` (optional): integer, min 1, max 5
- `has_response` (optional): boolean — filter reviews with/without partner response
- `date_from` (optional): date, format `Y-m-d`
- `date_to` (optional): date, format `Y-m-d`
- `sort` (optional): `'newest' | 'rating_asc' | 'rating_desc'` (default: `newest`)
- `per_page` (optional): integer, min 1, max 100 (default 10)

**Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "tour": {
        "id": 5,
        "title": "Colosseum Tour",
        "slug": "colosseum-tour"
      },
      "traveler_name": "Jane Doe",
      "rating": 5,
      "text": "Amazing experience! Our guide was incredibly knowledgeable.",
      "submitted_at": "2026-05-28T14:30:00Z",
      "booking_reference": "BOK-ABC123",
      "response": null
    },
    {
      "id": 2,
      "tour": {
        "id": 5,
        "title": "Colosseum Tour",
        "slug": "colosseum-tour"
      },
      "traveler_name": "John Smith",
      "rating": 4,
      "text": "Great tour, slightly rushed at the end.",
      "submitted_at": "2026-05-29T09:15:00Z",
      "booking_reference": "BOK-DEF456",
      "response": {
        "id": 1,
        "text": "Thank you for your feedback, John! We'll work on the pacing.",
        "created_at": "2026-05-30T10:00:00Z",
        "updated_at": "2026-05-30T10:00:00Z"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 10,
    "total": 15
  }
}
```

**Note**: Only reviews for tours belonging to the authenticated partner are returned.

---

## 2. Create Review Response

**Endpoint**: `POST /api/partner/reviews/{id}/responses`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if the review's tour does not belong to authenticated partner.

**Payload** (validated via `StoreReviewResponseRequest`):
```json
{
  "response_text": "Thank you for the feedback! We're glad you enjoyed the tour."
}
```

**Validation**:
- `response_text`: required, string, min 10, max 1000

**Response (201 Created)**:
```json
{
  "id": 3,
  "review_id": 1,
  "text": "Thank you for the feedback! We're glad you enjoyed the tour.",
  "created_at": "2026-06-03T10:00:00Z",
  "updated_at": "2026-06-03T10:00:00Z"
}
```

**Error (404)**: Review not found or does not belong to partner.
**Error (422)**: Validation error — response_text missing or too short/long.

---

## 3. Update Review Response

**Endpoint**: `PUT /api/partner/reviews/{id}/responses`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if the review's tour does not belong to authenticated partner.

**Payload** (validated via `StoreReviewResponseRequest`):
```json
{
  "response_text": "Thank you for the wonderful feedback! We hope to see you again."
}
```

**Validation**:
- `response_text`: required, string, min 10, max 1000

**Response (200 OK)**:
```json
{
  "id": 3,
  "review_id": 1,
  "text": "Thank you for the wonderful feedback! We hope to see you again.",
  "created_at": "2026-06-03T10:00:00Z",
  "updated_at": "2026-06-03T10:30:00Z"
}
```

**Error (404)**: Review not found, no existing response, or does not belong to partner.
**Error (422)**: Validation error — response_text missing or too short/long.