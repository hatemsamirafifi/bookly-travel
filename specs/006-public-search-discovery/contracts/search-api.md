# Search API Contract

**Feature**: 006-public-search-discovery
**Endpoint**: `GET /api/public/search/tours`

## Request

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `q` | string | no | — | Free-text search query (matched against title, description, location, category, highlights) |
| `locale` | string | yes | — | Language filter: `en`, `es`, or `it` |
| `category` | string | no | — | Category slug filter |
| `location` | string | no | — | Location slug filter |
| `price_min` | integer | no | 0 | Minimum price in smallest currency unit (cents) |
| `price_max` | integer | no | — | Maximum price in smallest currency unit (cents) |
| `duration` | string | no | — | Duration filter: `half-day` (≤4h), `full-day` (4-8h), `multi-day` (>8h) |
| `date` | date | no | — | ISO 8601 date (YYYY-MM-DD); filters to tours available on this date |
| `sort` | string | no | `relevance` | `relevance`, `price_asc`, `price_desc`, `rating`, `newest` |
| `page` | integer | no | 1 | Page number, 1-based |

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |
| `Accept-Language` | Used as fallback if `locale` param missing |

## Input Validation & Sanitization

All query parameters are untrusted input. The following rules are enforced at the API boundary (`SearchController`):

| Parameter | Validation Rules |
|-----------|------------------|
| `q` | Max 255 chars. Strip Meilisearch filter syntax operators (`=`, `!=`, `>`, `>=`, `<`, `<=`, `TO`, `AND`, `OR`, `NOT`, `IN`, `EXISTS`, `IS NULL`, `IS EMPTY`) when they appear as standalone tokens. HTML special characters (`<`, `>`, `&`, `"`, `'`) are entity-encoded in the response if the query is echoed back. |
| `locale` | Must be one of: `en`, `es`, `it`. Reject with 400 otherwise. |
| `category` | Must match pattern `^[a-z0-9-]+$` (slug format). Max 100 chars. |
| `location` | Must match pattern `^[a-z0-9-]+$` (slug format). Max 100 chars. |
| `price_min` | Non-negative integer. Max: 10,000,000 (€100,000 in cents). |
| `price_max` | Positive integer. Max: 10,000,000. Must be ≥ `price_min` if both provided. |
| `duration` | Must be one of: `half-day`, `full-day`, `multi-day`. |
| `date` | ISO 8601 date format (YYYY-MM-DD). Must be today or future. Max 1 year ahead. |
| `sort` | Must be one of: `relevance`, `price_asc`, `price_desc`, `rating`, `newest`. |
| `page` | Positive integer. Min: 1. Max: 1000 (prevents deep-offset abuse). |

**Frontend rendering**: When displaying "Results for: {query}" or similar reflected content, the frontend MUST use text interpolation (not `dangerouslySetInnerHTML` or `v-html`) to prevent XSS. The query is rendered as text content, never as HTML.

## Response

### 200 OK

```json
{
  "data": [
    {
      "id": 42,
      "slug": "tuscany-wine-tasting",
      "title": "Tuscany Wine Tasting Experience",
      "location": "Florence, Italy",
      "category": "Food & Wine",
      "duration_label": "5 hours",
      "price": {
        "amount": 8900,
        "currency": "EUR",
        "formatted": "€89.00"
      },
      "rating": {
        "average": 4.7,
        "count": 124
      },
      "cover_image_url": "https://cdn.bookly.com/tours/42/cover.jpg",
      "group_size": {
        "min": 2,
        "max": 12
      },
      "next_available_date": "2026-06-15"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 12,
    "total": 53
  },
  "filters": {
    "categories": [
      {"slug": "food-wine", "name": "Food & Wine", "count": 15},
      {"slug": "adventure", "name": "Adventure", "count": 8}
    ],
    "locations": [
      {"slug": "florence", "name": "Florence", "count": 20},
      {"slug": "rome", "name": "Rome", "count": 18}
    ],
    "price_range": {
      "min": 2500,
      "max": 35000
    },
    "durations": [
      {"value": "half-day", "label": "Half Day (≤4h)", "count": 10},
      {"value": "full-day", "label": "Full Day (4-8h)", "count": 30},
      {"value": "multi-day", "label": "Multi Day (>8h)", "count": 13}
    ]
  }
}
```

### 400 Bad Request

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "locale": ["The locale field is required."],
    "price_min": ["The price min must be a positive integer."]
  }
}
```

### 429 Too Many Requests

```json
{
  "message": "Too many search requests. Please wait and try again shortly.",
  "retry_after": 45
}
```

**Headers**: `X-RateLimit-Limit: 60`, `X-RateLimit-Remaining: 0`, `Retry-After: 45`

### 503 Service Unavailable

```json
{
  "message": "Search is temporarily unavailable. Please try again shortly."
}
```

## Behavior Notes

- Results are always filtered to `status = published`, with valid pricing and at least one future available date
- When `sort=relevance`, Meilisearch's built-in ranking rules apply (typo tolerance, word proximity, attribute priority)
- The `filters` object in the response reflects available filter options given the current result set (dynamic facet counts)
- Empty search (no `q`, no filters) returns all published available tours in default sort order
- The `locale` parameter controls which language fields are searched (via Meilisearch `attributesToSearchOn`), not a filter on indexed data. A search for "vino" with `locale=es` only matches against Spanish title/description/highlights fields plus shared fields (location, category)
- Filter option labels (`categories[].name`, `locations[].name`, `durations[].label`) are returned in the language specified by `locale`
