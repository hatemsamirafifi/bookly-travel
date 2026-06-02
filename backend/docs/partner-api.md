# Partner API Documentation

**Base URL**: `/api/partner`  
**Authentication**: Laravel Sanctum (Bearer token)  
**Authorization**: Requires `role: partner`  
**Rate Limiting**: Throttled per endpoint group

---

## Overview

The Partner API provides endpoints for tour operators (partners) to manage their tours, bookings, reviews, analytics, profile, and notifications within the Bookly marketplace.

All endpoints require authentication via Sanctum token and the `partner` role. Cross-partner access returns HTTP 404 (not 403) to prevent information leakage.

---

## Authentication

All requests must include a valid Sanctum token:

```
Authorization: Bearer {token}
```

Partners authenticate through the standard login endpoint and receive a token. The `partner` role is verified by `PartnerRoleMiddleware`.

---

## Tours

### List Tours

```
GET /api/partner/tours
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `draft`, `pending_review`, `published`, `rejected`, `archived` |
| `per_page` | integer | Items per page (default: 15, max: 100) |

**Response** `200`:

```json
{
  "data": [
    {
      "id": 1,
      "title": "Hidden Gems of Rome",
      "status": "published",
      "destination": "Rome, Italy",
      "price_from": 45.00,
      "currency": "EUR",
      "created_at": "2026-06-01T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "total": 42
  }
}
```

### Get Tour Detail

```
GET /api/partner/tours/{id}
```

Returns tour with media, pricing tiers, availability rules, and exceptions.

**Response** `200`: Full tour object with relations.  
**Response** `404`: Tour not found or not owned by partner.

### Create Tour

```
POST /api/partner/tours
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | yes | Max 120 chars, unique per partner |
| `description` | string | yes | Min 100, max 5000 chars |
| `category` | string | yes | Max 50 chars |
| `destination` | string | yes | Max 255 chars |
| `duration_value` | integer | yes | Min 1 |
| `duration_unit` | string | yes | `hour` or `day` |
| `difficulty_level` | string | yes | `easy`, `moderate`, or `challenging` |
| `meeting_point` | string | no | Max 500 chars |
| `itinerary` | array | no | Structured itinerary steps |
| `inclusions` | array | no | What's included |
| `cover_image_url` | string | no | Valid URL, max 2048 chars |
| `price_from` | number | no | Min 0 |
| `currency` | string | no | 3-char ISO 4217 |
| `pricing_tiers` | array | no | Array of tier objects |
| `availability_rules` | array | no | Array of rule objects |
| `availability_exceptions` | array | no | Array of exception objects |

**Response** `201`: Created tour object.

### Update Tour

```
PUT /api/partner/tours/{id}
```

Same fields as Create but all use `sometimes` (optional). Published tours create a draft revision instead of direct edit.

**Response** `200`: Updated tour object.

### Archive Tour

```
DELETE /api/partner/tours/{id}
```

Soft-deletes (archives) the tour. Existing bookings are not affected.

**Response** `200`: `{ "message": "Tour archived." }`

### Save Draft

```
POST /api/partner/tours/{id}/drafts/save
```

Auto-save endpoint for tour edits. Rate-limited to 1 request per 15 seconds per partner.

**Request Body**: `{ "payload": { ... } }`

**Response** `200`: Draft object.

### Get Latest Draft

```
GET /api/partner/tours/{id}/drafts/latest
```

**Response** `200`: Latest draft object.  
**Response** `404`: No draft found.

---

## Uploads

### Get Signed Upload URL

```
POST /api/partner/uploads/signed-url
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file_type` | string | yes | `image/jpeg` or `image/png` |
| `file_size` | integer | yes | Max 5,242,880 bytes (5MB) |

**Response** `200`:

```json
{
  "signed_url": "https://r2.bookly.test/uploads/xxx.jpg?sig=...",
  "public_url": "https://cdn.bookly.test/uploads/xxx.jpg",
  "expires_at": "2026-06-03T11:00:00Z"
}
```

---

## Bookings

### List Bookings

```
GET /api/partner/bookings
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | `confirmed`, `completed`, `cancelled`, `cancellation_requested` |
| `tour_id` | integer | Filter by tour |
| `date_from` | date | Filter from date (Y-m-d) |
| `date_to` | date | Filter to date (Y-m-d) |
| `search` | string | Search by booking reference or traveler name |
| `per_page` | integer | Items per page |

### Get Booking Detail

```
GET /api/partner/bookings/{reference}
```

**Response** `200`: Booking object with traveler and tour details.

### Update Booking Status

```
PATCH /api/partner/bookings/{reference}/status
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | yes | `completed` or `cancellation_requested` |
| `cancellation_reason` | string | conditional | Required when status is `cancellation_requested`, max 2000 chars |

**Business Rules**:
- `completed`: Only allowed when `tour_date <= today` and current status is `confirmed`
- `cancellation_requested`: Only allowed when current status is `confirmed`

### Request Cancellation

```
POST /api/partner/bookings/{reference}/cancellation-request
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | yes | Max 2000 chars |
| `evidence` | array | no | Array of URLs to supporting documents |

---

## Reviews

### List Reviews

```
GET /api/partner/reviews
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `tour_id` | integer | Filter by tour |
| `rating` | integer | Filter by rating (1-5) |
| `has_response` | boolean | Filter by response status |
| `date_from` | date | Filter from date |
| `date_to` | date | Filter to date |
| `sort` | string | `newest`, `rating_asc`, `rating_desc` |

### Create Review Response

```
POST /api/partner/reviews/{id}/responses
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `response_text` | string | yes | Max 1000 chars |

One response per review. Returns 409 if response already exists.

### Update Review Response

```
PUT /api/partner/reviews/{id}/responses
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `response_text` | string | yes | Max 1000 chars |

---

## Analytics

### Get Analytics

```
GET /api/partner/analytics
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `from` | date | Start date (Y-m-d) |
| `to` | date | End date (Y-m-d) |
| `tour_id` | integer | Filter by tour |

**Response** `200`:

```json
{
  "total_bookings": 42,
  "total_revenue": 1890.00,
  "average_rating": 4.7,
  "conversion_rate": 12.5,
  "period_start": "2026-05-04",
  "period_end": "2026-06-03",
  "chart_data": [
    { "date": "2026-06-01", "bookings": 3, "revenue": 135.00 }
  ]
}
```

---

## Profile & Settings

### Get Profile

```
GET /api/partner/profile
```

Returns profile and settings together.

### Update Profile

```
PUT /api/partner/profile
```

**Request Body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `company_name` | string | yes | Max 255 chars |
| `business_description` | string | no | Max 5000 chars |
| `logo_url` | string | no | Valid URL, max 2048 chars |
| `contact_email` | string | yes | Valid email, max 255 chars |
| `contact_phone` | string | no | Max 50 chars |
| `website` | string | no | Valid URL, max 2048 chars |
| `business_address` | object | no | `{street, city, country, postal_code}` |
| `tax_id` | string | no | Max 100 chars |
| `payout_holder_name` | string | no | Max 255 chars |
| `payout_bank_name` | string | no | Max 255 chars |
| `payout_account_number` | string | no | Max 100 chars (stored encrypted) |
| `payout_iban` | string | no | Max 34 chars (validated with IBAN checksum) |
| `payout_swift_bic` | string | no | Max 11 chars (stored encrypted) |
| `payout_country` | string | no | 2-char ISO 3166-1 alpha-2 |

**Note**: `payout_account_number`, `payout_iban`, and `payout_swift_bic` are encrypted at rest. Read responses return masked values (e.g., `****1234`).

### Get Settings

```
GET /api/partner/settings
```

### Update Settings

```
PUT /api/partner/settings
```

**Request Body**:

| Field | Type | Description |
|-------|------|-------------|
| `notify_new_booking` | boolean | Default: true |
| `notify_cancellation` | boolean | Default: true |
| `notify_daily_summary` | boolean | Default: true |
| `notify_review_received` | boolean | Default: true |
| `notify_tour_status_change` | boolean | Default: true |
| `locale` | string | `en`, `es`, `it` |

---

## Notifications

### List Notifications

```
GET /api/partner/notifications
```

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| `per_page` | integer | Items per page (default: 20) |
| `unread_only` | boolean | Filter unread only |

**Response includes `unread_count` in meta**.

### Mark as Read

```
POST /api/partner/notifications/{id}/read
```

### Mark All as Read

```
POST /api/partner/notifications/read-all
```

---

## WebSocket Events

Real-time events are broadcast on `private-partner.{partner_id}` channel via Laravel Echo/Reverb.

| Event | Payload |
|-------|---------|
| `NewBooking` | `{booking_id, reference, tour_title}` |
| `TourApproved` | `{tour_id, tour_title}` |
| `TourRejected` | `{tour_id, tour_title, reason?}` |
| `BookingCancelled` | `{booking_id, reference, reason}` |
| `ReviewReceived` | `{review_id, tour_title, rating}` |
| `PaymentStatusChanged` | `{booking_id, reference, status}` |
| `DailySummaryReady` | `{new_bookings_count, revenue, new_reviews_count}` |

---

## Error Responses

### 401 Unauthorized

```json
{ "message": "Unauthenticated." }
```

### 403 Forbidden

```json
{ "message": "You do not have the partner role." }
```

### 404 Not Found

```json
{ "message": "No query results for model [App\\Domains\\Partner\\Models\\Tour]" }
```

Cross-partner access returns 404 (not 403) to prevent information leakage.

### 422 Validation Error

```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

### 429 Too Many Requests

```json
{ "message": "Too many requests. Please try again later." }
```

---

## Rate Limits

| Endpoint Group | Limit |
|----------------|-------|
| Uploads | Throttled by `booking.create` |
| Tours, Bookings, Reviews, Analytics, Profile, Notifications | Throttled by `booking.get` |

---

*Last updated: 2026-06-03*