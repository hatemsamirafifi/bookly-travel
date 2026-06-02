# API Contract: Tours Endpoints

**Feature**: 010-public-frontend | **Base**: `{API_URL}/api`

All public endpoints. No authentication required for read operations.

---

## GET /tours

Search and list published tours.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `q` | `string` | — | Free-text search (title, description, location) |
| `category` | `string` | — | Filter by category slug |
| `destination` | `string` | — | Filter by destination slug |
| `min_price` | `number` | — | Minimum price per person |
| `max_price` | `number` | — | Maximum price per person |
| `min_duration` | `number` | — | Minimum duration in minutes |
| `max_duration` | `number` | — | Maximum duration in minutes |
| `date` | `string` | — | Filter by availability on date (YYYY-MM-DD) |
| `sort` | `string` | `relevance` | `relevance`, `price_asc`, `price_desc`, `rating`, `newest` |
| `page` | `number` | `1` | Pagination page |
| `per_page` | `number` | `12` | Results per page |
| `locale` | `string` | `en` | Content language |

### Response (200)

```json
{
  "data": [
    {
      "id": 1,
      "slug": "colosseum-guided-tour",
      "title": "Colosseum Guided Tour",
      "cover_image": "https://r2.example.com/tours/colosseum.jpg",
      "cover_image_blur": "data:image/webp;base64,...",
      "price_per_person": { "amount": 49.00, "currency": "EUR" },
      "average_rating": 4.7,
      "total_reviews": 128,
      "location": "Rome, Italy",
      "duration": "3 hours",
      "category": { "slug": "history-culture", "name": "History & Culture" }
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

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 422 | `{ "message": "Validation error", "errors": {...} }` | Invalid params |
| 429 | `{ "message": "Too many requests" }` | Rate limited |

---

## GET /tours/{slug}

Single tour detail.

### Path Parameters

| Param | Type | Description |
|-------|------|-------------|
| `slug` | `string` | Tour URL slug |

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `locale` | `string` | `en` | Content language |

### Response (200)

```json
{
  "data": {
    "id": 1,
    "slug": "colosseum-guided-tour",
    "title": "Colosseum Guided Tour",
    "description": "<p>Explore ancient Rome...</p>",
    "highlights": ["Skip-the-line entry", "Expert guide"],
    "inclusions": ["Guided tour", "Headset"],
    "exclusions": ["Transport to meeting point"],
    "meeting_point": "Via dei Fori Imperiali 1, Rome",
    "images": [
      { "url": "https://r2.example.com/tours/colosseum-1.jpg", "alt": "Colosseum facade", "blur_data_url": "data:image/webp;base64,..." }
    ],
    "price_per_person": { "amount": 49.00, "currency": "EUR" },
    "duration": "3 hours",
    "category": { "slug": "history-culture", "name": "History & Culture" },
    "destination": { "slug": "rome", "name": "Rome" },
    "average_rating": 4.7,
    "total_reviews": 128,
    "reviews": [
      { "id": 567, "author": "John D.", "rating": 5, "comment": "Amazing experience!", "created_at": "2026-04-15T10:30:00Z" }
    ],
    "availability": [
      { "date": "2026-06-01", "time_slot": "09:00 AM", "price_per_person": { "amount": 49.00, "currency": "EUR" }, "remaining_capacity": 8 },
      { "date": "2026-06-01", "time_slot": "02:00 PM", "price_per_person": { "amount": 59.00, "currency": "EUR" }, "remaining_capacity": 3 }
    ]
  }
}
```

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 404 | `{ "message": "Tour not found" }` | Slug doesn't exist or tour is not published |

---

## GET /tours/category/{slug}

Tours filtered by category. Same query params and response shape as `GET /tours` (minus `category` filter).

---

## GET /tours/destination/{slug}

Tours filtered by destination. Same query params and response shape as `GET /tours` (minus `destination` filter).

---

## Notes for Frontend Developers

- All responses are JSON. The API URL is configured via `NEXT_PUBLIC_API_URL`.
- Locale parameter controls content language; static text comes from `next-intl` translations, dynamic content (tour titles, descriptions) from API.
- Price amounts are in minor units from the backend (cents); frontend formats for display.
- Image URLs from R2 CDN are public and do not require authentication.
- `cover_image_blur` is a base64-encoded WebP blur placeholder for progressive loading.
