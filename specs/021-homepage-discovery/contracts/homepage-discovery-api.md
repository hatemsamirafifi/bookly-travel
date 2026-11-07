# API Contract: Public Homepage & Discovery API (Spec 021)

**Status**: Active Contract Specification  
**Base URL**: `/api/public`  
**Governing Principles**: Preserves existing public API routes; adds `curated_rails` to the homepage response envelope without breaking existing clients.

---

## 1. Public Homepage Endpoint

### `GET /api/public/homepage`

Retrieves the aggregated, localized discovery dataset for the Bookly homepage.

#### Query Parameters
| Parameter | Type | Required | Description |
|:----------|:-----|:---------|:------------|
| `locale`  | string | Yes | Language code (`en`, `es`, `it`). Defaults to `en` if omitted. |

#### Success Response (`200 OK`)
```json
{
  "data": {
    "featured_tours": [
      {
        "id": 101,
        "slug": "colosseum-underground-vip-tour",
        "title": "Colosseum Underground & Ancient Rome VIP Tour",
        "location": "Rome, Italy",
        "category": "Historical Tours",
        "duration_label": "3.5 hours",
        "price": {
          "amount": 8900,
          "currency": "EUR",
          "formatted": "€89"
        },
        "rating": {
          "average": 4.92,
          "count": 128
        },
        "cover_image_url": "https://storage.bookly.test/tours/colosseum-cover.jpg",
        "group_size": {
          "min": 1,
          "max": 12
        },
        "next_available_date": "2026-11-01",
        "badge": "featured"
      }
    ],
    "popular_categories": [
      {
        "slug": "walking-tours",
        "name": "Walking Tours",
        "description": "Explore city centers on foot with local guides",
        "image_url": "https://storage.bookly.test/categories/walking.jpg",
        "tour_count": 24
      }
    ],
    "featured_destinations": [
      {
        "slug": "rome",
        "name": "Rome",
        "country": "Italy",
        "image_url": "https://storage.bookly.test/destinations/rome.jpg",
        "tour_count": 42
      }
    ],
    "curated_rails": [
      {
        "id": "family-friendly",
        "slug": "family-friendly",
        "title": "Top Family Experiences",
        "tours": [
          {
            "id": 105,
            "slug": "gladiator-school-for-kids",
            "title": "Hands-on Gladiator School for Kids & Families",
            "location": "Rome, Italy",
            "category": "Family Friendly",
            "duration_label": "2 hours",
            "price": {
              "amount": 5500,
              "currency": "EUR",
              "formatted": "€55"
            },
            "rating": {
              "average": 4.95,
              "count": 76
            },
            "cover_image_url": "https://storage.bookly.test/tours/gladiator.jpg",
            "group_size": {
              "min": 2,
              "max": 15
            },
            "next_available_date": "2026-11-02",
            "badge": "best-seller"
          }
        ]
      }
    ]
  },
  "meta": {
    "seo": {
      "meta_title": "Bookly - Discover & Book Amazing Tours",
      "meta_description": "Discover and instantly book the best tours worldwide. Browse thousands of experiences across top destinations."
    }
  }
}
```

#### Error Responses
- `422 Unprocessable Entity`: Invalid locale parameter provided (`{ "message": "The selected locale is invalid." }`).
- `500 Internal Server Error`: Database or cache failure (`{ "message": "An error occurred while retrieving homepage data." }`).

---

## 2. Public Search Endpoint

### `GET /api/public/search`

Executes full-text tour discovery with filtering, sorting, and pagination.

#### Query Parameters
| Parameter | Type | Required | Description |
|:----------|:-----|:---------|:------------|
| `locale` | string | Yes | Language code (`en`, `es`, `it`). |
| `q` | string | No | Search query term. |
| `category` | string | No | Category slug filter. |
| `destination` | string | No | Destination slug filter. |
| `min_price` | integer | No | Minimum price filter in currency cents. |
| `max_price` | integer | No | Maximum price filter in currency cents. |
| `rating` | integer | No | Minimum average rating (e.g., 4 for $\ge 4.0$). |
| `duration` | string | No | Duration bucket (`under_2h`, `2h_to_4h`, `4h_to_8h`, `full_day`, `multi_day`). |
| `sort_by` | string | No | Sorting order: `popularity`, `price_asc`, `price_desc`, `rating`. |
| `page` | integer | No | Page number (default: 1). |
| `per_page` | integer | No | Results per page (default: 12, max: 48). |

#### Success Response (`200 OK`)
```json
{
  "data": [
    {
      "id": 101,
      "slug": "colosseum-underground-vip-tour",
      "title": "Colosseum Underground & Ancient Rome VIP Tour",
      "location": "Rome, Italy",
      "category": "Historical Tours",
      "duration_label": "3.5 hours",
      "price": {
        "amount": 8900,
        "currency": "EUR",
        "formatted": "€89"
      },
      "rating": {
        "average": 4.92,
        "count": 128
      },
      "cover_image_url": "https://storage.bookly.test/tours/colosseum-cover.jpg",
      "group_size": { "min": 1, "max": 12 },
      "next_available_date": "2026-11-01"
    }
  ],
  "meta": {
    "total": 42,
    "current_page": 1,
    "last_page": 4,
    "per_page": 12
  }
}
```

---

## 3. Guest Wishlist Sync Endpoint

### `POST /api/public/traveler/wishlist/merge`

Merges an array of tour IDs collected in client `localStorage` into the authenticated traveler's account wishlist upon login or registration.

#### Headers
- `Authorization: Bearer <sanctum_token>`
- `Content-Type: application/json`

#### Request Body
```json
{
  "tour_ids": [101, 105, 204]
}
```

#### Success Response (`200 OK`)
```json
{
  "status": "success",
  "merged_count": 3,
  "total_wishlist_count": 5
}
```
*Note: Duplicate entries are deduplicated safely without error.*
