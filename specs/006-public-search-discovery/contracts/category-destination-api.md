# Category & Destination API Contract

**Feature**: 006-public-search-discovery

## Category Listing

**Endpoint**: `GET /api/public/categories`

Returns all active categories with tour counts.

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `locale` | string | yes | — | Language for category names: `en`, `es`, `it` |

### 200 OK

```json
{
  "data": [
    {
      "slug": "food-wine",
      "name": "Food & Wine",
      "description": "Taste local flavors and visit renowned wineries",
      "image_url": "https://cdn.bookly.com/categories/food-wine.jpg",
      "tour_count": 245
    }
  ]
}
```

**Behavior Note**: The `name` and `description` fields are returned in the language specified by the `locale` parameter. If a translation is missing for the requested locale, English is used as fallback.

---

## Category Tours

**Endpoint**: `GET /api/public/categories/{slug}/tours`

Returns paginated published tours within a category. Accepts the same filter/sort/pagination parameters as the search endpoint (excluding `category`, which is fixed by the path).

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `slug` | string | Category slug |

### Query Parameters

Same as [Search API](search-api.md) except `category` is determined by the URL path and must not be passed.

### 200 OK

Same response structure as search results, with `category` pre-filtered.

### 404 Not Found

```json
{
  "message": "Category not found."
}
```

---

## Destination Listing

**Endpoint**: `GET /api/public/destinations`

Returns featured destinations with tour counts.

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `locale` | string | yes | — | Language context |
| `featured_only` | boolean | no | false | Return only featured destinations |

### 200 OK

```json
{
  "data": [
    {
      "slug": "florence",
      "name": "Florence",
      "country": "Italy",
      "image_url": "https://cdn.bookly.com/destinations/florence.jpg",
      "tour_count": 87
    }
  ]
}
```

---

## Destination Tours

**Endpoint**: `GET /api/public/destinations/{slug}/tours`

Returns paginated published tours at a destination. Accepts same parameters as category tours, with `location` pre-filtered by path.

### 404 Not Found

```json
{
  "message": "Destination not found."
}
```

---

## Homepage Data

**Endpoint**: `GET /api/public/homepage`

Aggregated data for homepage rendering.

### Query Parameters

| Parameter | Type | Required | Default |
|-----------|------|----------|---------|
| `locale` | string | yes | — |

### 200 OK

```json
{
  "data": {
    "featured_tours": [
      {
        "id": 42,
        "slug": "tuscany-wine-tasting",
        "title": "Tuscany Wine Tasting Experience",
        "location": "Florence, Italy",
        "duration_label": "5 hours",
        "price": {"amount": 8900, "currency": "EUR", "formatted": "€89.00"},
        "rating": {"average": 4.7, "count": 124},
        "cover_image_url": "https://cdn.bookly.com/tours/42/cover.jpg"
      }
    ],
    "popular_categories": [
      {"slug": "food-wine", "name": "Food & Wine", "image_url": "...", "tour_count": 245}
    ],
    "featured_destinations": [
      {"slug": "florence", "name": "Florence", "country": "Italy", "image_url": "...", "tour_count": 87}
    ]
  },
  "meta": {
    "seo": {
      "meta_title": "Bookly — Discover & Book Amazing Tours",
      "meta_description": "Find and instantly book the best tours in Italy, Spain, and beyond. Wine tasting, adventure, culture, and more."
    }
  }
}
```

### 400 Bad Request

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "locale": ["The locale field is required."]
  }
}
```

### 429 Too Many Requests

```json
{
  "message": "Too many requests. Please wait and try again shortly.",
  "retry_after": 45
}
```

**Headers**: `X-RateLimit-Limit: 120`, `X-RateLimit-Remaining: 0`, `Retry-After: 45`

### 503 Service Unavailable

```json
{
  "message": "Homepage data is temporarily unavailable. Please try again shortly."
}
```
