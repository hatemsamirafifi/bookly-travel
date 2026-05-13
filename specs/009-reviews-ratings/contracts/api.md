# API Contracts: Reviews & Ratings

**Feature**: 009-reviews-ratings | **Date**: 2026-05-13

## Public Endpoints

### GET /api/tours/{tour:slug}/reviews

Public listing of reviews for a tour.

**Auth**: None
**Query Params**: `page` (int, default 1), `per_page` (int, default 5, max 20)

**Response 200**:
```json
{
  "data": [
    {
      "id": 42,
      "reviewer_name": "Marco",
      "rating": 4,
      "comment": "Amazing boat tour along the Amalfi coast. Guide was knowledgeable and friendly!",
      "locale": "en",
      "edited": false,
      "created_at": "2026-05-12T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 5,
    "total": 10
  }
}
```

**Notes**:
- Only `visible` reviews are returned. `flagged` reviews are included (they are visible publicly).
- `reviewer_name` is first name only. If account deleted, shows "Anonymous Traveler".
- `edited` is derived from `edited_at IS NOT NULL`.
- Reviews sorted by `created_at DESC`.

### POST /api/reviews

Submit a new review.

**Auth**: Sanctum (traveler)
**Rate Limit**: 10 per hour per traveler

**Request**:
```json
{
  "booking_reference": "BK-R7XK29",
  "rating": 4,
  "comment": "Amazing boat tour along the Amalfi coast!",
  "locale": "en"
}
```

**Response 201**:
```json
{
  "data": {
    "id": 42,
    "booking_reference": "BK-R7XK29",
    "rating": 4,
    "comment": "Amazing boat tour along the Amalfi coast!",
    "status": "visible",
    "flagged": false,
    "created_at": "2026-05-12T14:30:00Z"
  }
}
```

**Errors**:
- 401 Unauthorized — not logged in
- 403 Forbidden — booking not completed, outside 30-day window, or already reviewed
- 422 Unprocessable — validation failure (invalid rating, comment too long)
- 429 Too Many Requests — rate limit exceeded

**Note**: If profanity filter matches, `status` is still "visible" but `flagged` is true. The review appears publicly immediately.

### PUT /api/reviews/{review:id}

Edit an existing review (within 48-hour window).

**Auth**: Sanctum (traveler, must own the review)

**Request**:
```json
{
  "rating": 5,
  "comment": "Updated: Absolutely incredible experience!"
}
```

**Response 200**:
```json
{
  "data": {
    "id": 42,
    "rating": 5,
    "comment": "Updated: Absolutely incredible experience!",
    "status": "visible",
    "edited": true,
    "created_at": "2026-05-12T14:30:00Z",
    "updated_at": "2026-05-12T20:15:00Z"
  }
}
```

**Errors**:
- 401 Unauthorized — not logged in
- 403 Forbidden — not the review owner, or 48-hour edit window has closed

---

## Partner Endpoints

### GET /api/partner/reviews

List reviews for the authenticated partner's tours.

**Auth**: Sanctum (partner)
**Query Params**: `tour_id` (optional, filter by tour), `sort` (optional, `rating` or `created_at`, default `created_at`)

**Response 200**:
```json
{
  "data": [
    {
      "id": 42,
      "tour_slug": "amalfi-boat-tour",
      "reviewer_name": "Marco",
      "rating": 4,
      "comment": "Amazing boat tour!",
      "status": "visible",
      "created_at": "2026-05-12T14:30:00Z"
    }
  ],
  "meta": {
    "tour_summaries": [
      { "tour_slug": "amalfi-boat-tour", "average_rating": 4.2, "review_count": 10 }
    ]
  }
}
```

**Errors**:
- 401 Unauthorized — not logged in
- 403 Forbidden — not a partner

---

## Admin Endpoints

### GET /api/admin/reviews

List all reviews across the platform for moderation.

**Auth**: Sanctum (admin)
**Query Params**: `status` (visible/hidden/flagged), `tour_id`, `date_from`, `date_to`, `page`

**Response 200**:
```json
{
  "data": [
    {
      "id": 42,
      "tour_slug": "amalfi-boat-tour",
      "reviewer_name": "Marco",
      "rating": 4,
      "comment": "Amazing boat tour!",
      "status": "flagged",
      "flagged_reason": "profanity_match",
      "audit_trail": [
        {
          "action": "submit",
          "actor": "traveler",
          "created_at": "2026-05-12T14:30:00Z"
        },
        {
          "action": "submit",
          "actor": "system",
          "reason": "auto-flagged by profanity filter",
          "created_at": "2026-05-12T14:30:01Z"
        }
      ],
      "created_at": "2026-05-12T14:30:00Z"
    }
  ],
  "meta": { "current_page": 1, "total": 3 }
}
```

### POST /api/admin/reviews/{review:id}/hide

Hide a review from public display.

**Auth**: Sanctum (admin)

**Request**:
```json
{
  "reason": "Contains promotional link"
}
```

**Response 200**:
```json
{
  "data": {
    "id": 42,
    "status": "hidden",
    "updated_at": "2026-05-12T16:00:00Z"
  }
}
```

### POST /api/admin/reviews/{review:id}/reinstate

Reinstate a previously hidden review.

**Auth**: Sanctum (admin)

**Request**:
```json
{
  "reason": "Hidden in error — promotional link was to official tourism site"
}
```

**Response 200**: Same shape as hide response; status becomes `visible`.
