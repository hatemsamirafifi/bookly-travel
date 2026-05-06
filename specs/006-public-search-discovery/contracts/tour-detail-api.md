# Tour Detail API Contract

**Feature**: 006-public-search-discovery
**Endpoint**: `GET /api/public/tours/{slug}`

## Request

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `slug` | string | Tour URL slug (shared across all locales) |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `locale` | string | yes | — | Language for content: `en`, `es`, or `it` |

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |

## Response

### 200 OK

```json
{
  "data": {
    "id": 42,
    "slug": "tuscany-wine-tasting",
    "title": "Tuscany Wine Tasting Experience",
    "description": "Explore the rolling hills of Tuscany...",
    "highlights": ["Visit 3 family-owned wineries", "Traditional Tuscan lunch"],
    "inclusions": ["Wine tasting at 3 wineries", "Lunch", "Transport from Florence"],
    "exclusions": ["Personal expenses", "Gratuities"],
    "location": "Florence, Italy",
    "meeting_point": "Piazza della Repubblica, Florence",
    "category": {"slug": "food-wine", "name": "Food & Wine"},
    "duration": {
      "minutes": 300,
      "label": "5 hours"
    },
    "languages": ["en", "es", "it"],
    "group_size": {"min": 2, "max": 12},
    "cancellation_policy": "Free cancellation up to 24 hours before the tour start time.",
    "images": [
      {"url": "https://cdn.bookly.com/tours/42/cover.jpg", "is_cover": true, "alt": "Tuscany vineyard panorama"},
      {"url": "https://cdn.bookly.com/tours/42/img2.jpg", "is_cover": false, "alt": "Wine tasting cellar"}
    ],
    "pricing": {
      "base_price": {"amount": 8900, "currency": "EUR", "formatted": "€89.00"},
      "tiered_pricing": null
    },
    "availability": {
      "next_available_date": "2026-06-15",
      "available_dates": ["2026-06-15", "2026-06-16", "2026-06-17"]
    },
    "reviews": {
      "average_rating": 4.7,
      "count": 124,
      "distribution": {"5": 80, "4": 30, "3": 10, "2": 3, "1": 1}
    },
    "seo": {
      "meta_title": "Tuscany Wine Tasting Experience | Bookly",
      "meta_description": "Explore Tuscany's finest wineries. Visit 3 family-owned estates with expert guides. Book instantly.",
      "canonical_url": "https://bookly.com/en/tours/tuscany-wine-tasting",
      "hreflang": {
        "en": "https://bookly.com/en/tours/tuscany-wine-tasting",
        "es": "https://bookly.com/es/tours/tuscany-wine-tasting",
        "it": "https://bookly.com/it/tours/tuscany-wine-tasting"
      }
    }
  }
}
```

### 404 Not Found

```json
{
  "message": "Tour not found."
}
```

Returned when:
- Slug does not match any tour
- Tour exists but is in `draft`, `pending_review`, or `rejected` status
- Tour is `archived`

### 410 Gone

```json
{
  "message": "This tour is no longer available."
}
```

Returned when:
- Tour was previously published but is now archived (bookmarked/stale links)

## Behavior Notes

- Content fields (`title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`) are returned in the language specified by `locale`
- If the requested locale lacks translations, fall back to English content with `"translation_warning": "partial_translation"` flag
- Availability is checked in real-time against the pricing/availability data (not just the search index)
- `available_dates` shows next 30 days of availability; additional dates can be paginated via a separate endpoint
