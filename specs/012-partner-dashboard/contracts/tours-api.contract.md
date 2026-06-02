# API Contract: Partner Tours & Drafts

**Base URL**: `/api/partner` (prefix set in route group)
**Auth**: Bearer Token + `partner` middleware (PartnerRoleMiddleware)
**Middleware**: `auth:sanctum`, `partner` (sets `partner_id` on request, returns 404 for cross-partner)

---

## 1. List Tours

**Endpoint**: `GET /api/partner/tours`
**Throttle**: `booking.get`

**Query Parameters**:
- `status` (optional): `'draft' | 'pending_review' | 'published' | 'rejected' | 'archived'`
- `per_page` (optional): integer, min 1, max 100 (default 12)

**Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "title": "Colosseum Tour",
      "status": "published",
      "category": "history",
      "destination": "Rome, Italy",
      "media": [],
      "pricing_tiers": [],
      "availability_rules": [],
      "availability_exceptions": []
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 12,
    "total": 56
  }
}
```

---

## 2. Get Tour Detail

**Endpoint**: `GET /api/partner/tours/{id}`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if tour does not belong to authenticated partner.

**Response (200 OK)**:
```json
{
  "id": 1,
  "title": "Colosseum Tour",
  "status": "published",
  "category": "history",
  "destination": "Rome, Italy",
  "duration_value": 3,
  "duration_unit": "hour",
  "difficulty_level": "easy",
  "itinerary": [],
  "inclusions": [],
  "meeting_point": "Piazza del Colosseo",
  "cover_image_url": "https://cdn.bookly.test/uploads/abc.jpg",
  "price_from": 45.00,
  "currency": "EUR",
  "media": [],
  "pricing_tiers": [],
  "availability_rules": [],
  "availability_exceptions": [],
  "created_at": "2026-06-01T10:00:00Z",
  "updated_at": "2026-06-02T15:30:00Z"
}
```

**Error (404)**: Tour not found or does not belong to partner.

---

## 3. Create Tour

**Endpoint**: `POST /api/partner/tours`
**Throttle**: `booking.get` (rate-limited)

**Payload**:
```json
{
  "title": "New Tour",
  "description": "A wonderful experience through the ancient city...",
  "category": "history",
  "destination": "Rome, Italy",
  "duration_value": 3,
  "duration_unit": "hour",
  "difficulty_level": "easy",
  "itinerary": ["Meet at entrance", "Tour the ruins", "Free time"],
  "inclusions": ["Guide", "Headphones", "Water"],
  "meeting_point": "Piazza del Colosseo 1",
  "cover_image_url": "https://cdn.bookly.test/uploads/abc.jpg",
  "price_from": 45.00,
  "currency": "EUR",
  "pricing_tiers": [
    { "name": "Standard", "price": 45, "currency": "EUR", "min_participants": 1, "max_participants": 10 }
  ],
  "availability_rules": [
    { "rule_type": "weekly", "days_of_week": [1, 3, 5], "start_time": "09:00", "capacity": 20 }
  ],
  "availability_exceptions": [
    { "exception_type": "blackout", "date": "2026-12-25", "note": "Christmas" }
  ]
}
```

**Validation Rules**:
- `title`: required, string, max 120
- `description`: required, string, min 100, max 5000
- `category`: required, string, max 50
- `destination`: required, string, max 255
- `duration_value`: required, integer, min 1
- `duration_unit`: required, `'hour' | 'day'`
- `difficulty_level`: required, `'easy' | 'moderate' | 'challenging'`
- `itinerary`, `inclusions`: nullable, array
- `meeting_point`: nullable, string, max 500
- `cover_image_url`: nullable, URL, max 2048
- `price_from`: nullable, numeric, min 0
- `currency`: nullable, string, size 3
- `pricing_tiers`, `availability_rules`, `availability_exceptions`: nullable, array

**Response (201 Created)**:
```json
{
  "id": 2,
  "title": "New Tour",
  "status": "draft",
  "created_at": "2026-06-03T10:00:00Z"
}
```

---

## 4. Update Tour

**Endpoint**: `PUT /api/partner/tours/{id}`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if tour does not belong to authenticated partner.

**Payload**: All fields from Create Tour, but with `sometimes` instead of `required` (partial updates).

**Response (200 OK)**: Updated Tour object.

**Error (404)**: Tour not found or does not belong to partner.

---

## 5. Archive Tour

**Endpoint**: `DELETE /api/partner/tours/{id}`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if tour does not belong to authenticated partner.

**Response (200 OK)**:
```json
{
  "message": "Tour archived."
}
```

**Error (404)**: Tour not found or does not belong to partner.

---

## 6. Save Draft

**Endpoint**: `POST /api/partner/tours/{id}/drafts/save`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if tour does not belong to authenticated partner.

Saves a draft snapshot for an existing tour. Used for revision editing of published tours.

**Payload**:
```json
{
  "payload": {
    "title": "Updated Title",
    "description": "Updated description...",
    "pricing_tiers": []
  }
}
```

**Validation**: `payload` is required, must be array. The payload is stored as JSONB.

**Response (200 OK)**:
```json
{
  "id": 5,
  "tour_id": 1,
  "partner_id": 1,
  "payload": {
    "title": "Updated Title",
    "description": "Updated description..."
  },
  "created_at": "2026-06-03T10:00:00Z",
  "updated_at": "2026-06-03T10:00:00Z"
}
```

**Error (404)**: Tour not found or does not belong to partner.

---

## 7. Get Latest Draft

**Endpoint**: `GET /api/partner/tours/{id}/drafts/latest`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if tour does not belong to authenticated partner.

**Response (200 OK)**: Latest TourDraft object.

**Response (404)**:
```json
{
  "message": "No draft found."
}
```

---

## 8. Get Signed Upload URL

**Endpoint**: `POST /api/partner/uploads/signed-url`
**Throttle**: `booking.create` (stricter rate limit)
**Auth**: Bearer Token + `partner` middleware

**Payload**:
```json
{
  "file_type": "image/jpeg",
  "file_size": 2097152
}
```

**Validation**:
- `file_type`: required, `'image/jpeg' | 'image/png'`
- `file_size`: required, integer, max 5242880 (5 MB)

**Response (200 OK)**:
```json
{
  "signed_url": "https://r2.bookly.test/uploads/{uuid}.jpg?sig=...&expires=...",
  "public_url": "https://cdn.bookly.test/uploads/{uuid}.jpg",
  "expires_at": "2026-06-03T10:15:00Z"
}
```