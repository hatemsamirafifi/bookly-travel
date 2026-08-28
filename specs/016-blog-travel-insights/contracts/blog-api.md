# API Contract: Blog Endpoints

**Feature**: 016-blog-travel-insights | **Base**: `{API_URL}/api/public`

All public blog endpoints. No authentication required for read operations (except preview, which
requires a signed token). All endpoints require a `?locale=` query parameter validated against
supported locales (`en`, `es`, `it`), returning 422 on missing/invalid.

Rate limit: 120 requests/minute per IP for listing + detail; 10/minute for sitemap (reuses existing
`sitemap` limiter).

---

## GET /blog

Paginated list of published blog posts, optionally filtered by category.

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `locale` | `string` | — | **Required**. Content language (`en`, `es`, `it`) |
| `category` | `string` | — | Filter by blog category slug |
| `page` | `number` | `1` | Pagination page |
| `per_page` | `number` | `12` | Results per page |

### Response (200)

```json
{
  "data": [
    {
      "id": 1,
      "slug": "best-street-food-in-rome",
      "title": "Best Street Food in Rome",
      "excerpt": "A guide to Rome's best street food spots...",
      "cover_image_url": "https://r2.example.com/blog/rome-street-food.jpg",
      "cover_image_blur": "data:image/webp;base64,...",
      "published_at": "2026-08-20T10:00:00Z",
      "is_featured": true,
      "reading_time": 5,
      "translation_warning": null,
      "category": {
        "slug": "food-drink",
        "name": "Food & Drink"
      },
      "author": {
        "display_name": "Maria Rossi",
        "avatar_url": "https://r2.example.com/avatars/maria.jpg"
      }
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

### Behavior

- Only posts where `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())` are included.
- Ordered by `published_at DESC` (newest-first).
- Featured posts are included in the list AND flagged via `is_featured` (the frontend renders a hero section from featured posts).
- If `?category={slug}` is provided, only posts in that category are returned. If the category slug does not exist, returns an empty data array (not an error).
- If the requested locale's content is missing for a post, the title and excerpt fall back to English and `translation_warning: 'partial_translation'` is set on that item.

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 422 | `{ "message": "Validation error", "errors": { "locale": [...] } }` | Missing or invalid `locale` |
| 422 | `{ "message": "Validation error", "errors": { "per_page": [...] } }` | Invalid `per_page` (non-integer or < 1) |
| 429 | `{ "message": "Too Many Requests" }` + `Retry-After: N` header | Rate limited (120/min) |

### Cache

- Backend: `Cache::tags(['blog','blog_list'])->remember('bookly:blog:list:{locale}:{hash}', 300)` — 5 minutes, keyed by locale + query params hash. Stored under tags so `InvalidateBlogCacheJob` can flush it (see ADR 0014).
- Frontend: `revalidate: 300` (Next.js data cache).

---

## GET /blog/{slug}

Single blog post detail with SEO block, author, category, related tours, and related posts.

### Path Parameters

| Param | Type | Description |
|-------|------|-------------|
| `slug` | `string` | Blog post URL slug (`^[a-z0-9-]+$`) |

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `locale` | `string` | — | **Required**. Content language |

### Response (200)

```json
{
  "data": {
    "id": 1,
    "slug": "best-street-food-in-rome",
    "title": "Best Street Food in Rome",
    "body": "<p>A guide to Rome's best street food spots...</p>",
    "excerpt": "A guide to Rome's best street food spots...",
    "cover_image_url": "https://r2.example.com/blog/rome-street-food.jpg",
    "cover_image_blur": "data:image/webp;base64,...",
    "published_at": "2026-08-20T10:00:00Z",
    "updated_at": "2026-08-21T14:30:00Z",
    "is_featured": true,
    "reading_time": 5,
    "translation_warning": null,
    "category": {
      "slug": "food-drink",
      "name": "Food & Drink"
    },
    "author": {
      "display_name": "Maria Rossi",
      "bio": "Food writer based in Rome.",
      "avatar_url": "https://r2.example.com/avatars/maria.jpg"
    },
    "seo": {
      "meta_title": "Best Street Food in Rome | Bookly Travel Insights",
      "meta_description": "A guide to Rome's best street food spots...",
      "canonical_url": "https://bookly.com/en/blog/best-street-food-in-rome",
      "hreflang": {
        "en": "https://bookly.com/en/blog/best-street-food-in-rome",
        "es": "https://bookly.com/es/blog/best-street-food-in-rome",
        "it": "https://bookly.com/it/blog/best-street-food-in-rome"
      }
    },
    "related_tours": [
      {
        "id": 42,
        "slug": "rome-food-walk",
        "title": "Rome Food Walk",
        "location": "Rome, Italy",
        "category": { "slug": "food-drink", "name": "Food & Drink" },
        "duration_label": "3 hours",
        "price": { "amount": 49, "currency": "EUR", "formatted": "€49" },
        "rating": { "average": 4.7, "count": 128 },
        "cover_image_url": "https://r2.example.com/tours/rome-food-walk.jpg",
        "group_size": { "min": 1, "max": 12 },
        "next_available_date": "2026-08-25"
      }
    ],
    "related_posts": [
      {
        "id": 7,
        "slug": "rome-coffee-culture",
        "title": "Rome Coffee Culture",
        "excerpt": "Exploring Rome's coffee traditions...",
        "cover_image_url": "https://r2.example.com/blog/rome-coffee.jpg",
        "cover_image_blur": null,
        "published_at": "2026-08-18T08:00:00Z",
        "is_featured": false,
        "reading_time": 4,
        "translation_warning": null,
        "category": { "slug": "food-drink", "name": "Food & Drink" },
        "author": { "display_name": "Maria Rossi", "avatar_url": null }
      }
    ]
  }
}
```

### Behavior

- **Visibility**: Only posts where `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())` are returned.
- **404 vs 410**:
  - Non-existent slug → **404** `{"message": "Blog post not found."}`
  - `status = 'draft'` → **404**
  - `status = 'published'` + `scheduled_at > now()` → **404**
  - `status = 'archived'` + `published_at IS NULL` → **404**
  - `status = 'archived'` + `published_at IS NOT NULL` → **410** `{"message": "This blog post is no longer available."}`
- **Translation fallback**: If the requested locale's content is missing, English content is returned with `translation_warning: 'partial_translation'`.
- **`reading_time`**: Integer minutes ≥1, computed from body word count: `max(1, ceil(str_word_count(strip_tags($body)) / 200))`.
- **`seo.canonical_url`**: Self-canonical per locale (e.g. `/es/blog/foo` → canonical `/es/blog/foo`).
- **`seo.hreflang`**: All 3 locale URLs for this post.
- **`related_tours`**: Up to 6 published tours ordered by `sort_order`, transformed via `TourCardTransformer`. Archived tours omitted silently.
- **`related_posts`**: Up to 3 published posts, same-category first (newest-first, excluding current), backfilled from other categories (newest-first) if fewer than 3 same-category posts exist.

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 404 | `{ "message": "Blog post not found." }` | Non-existent, draft, scheduled-future, or archived-never-published |
| 410 | `{ "message": "This blog post is no longer available." }` | Archived-previously-published |
| 422 | `{ "message": "Validation error", "errors": { "locale": [...] } }` | Missing or invalid `locale` |
| 429 | `{ "message": "Too Many Requests" }` + `Retry-After: N` header | Rate limited (120/min) |

### Cache

- Backend: **uncached** (fresh content on every request, matching tour detail precedent).
- Frontend: **uncached** (no `revalidate`).

---

## GET /blog/category/{slug}

Category detail with paginated posts in that category.

### Path Parameters

| Param | Type | Description |
|-------|------|-------------|
| `slug` | `string` | Blog category slug |

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `locale` | `string` | — | **Required**. Content language |
| `page` | `number` | `1` | Pagination page |
| `per_page` | `number` | `12` | Results per page |

### Response (200)

```json
{
  "data": {
    "slug": "food-drink",
    "name": "Food & Drink",
    "description": "Guides and stories about food and drink travel experiences."
  },
  "posts": {
    "data": [ /* BlogPostCard[] */ ],
    "meta": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 12,
      "total": 28
    }
  }
}
```

### Behavior

- Returns the category (even if `is_active = false` — inactive categories remain reachable by direct URL per spec edge case).
- Posts are `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())`, ordered `published_at DESC`.
- If the category slug does not exist → **404**.

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 404 | `{ "message": "Category not found." }` | Non-existent category slug |
| 422 | `{ "message": "Validation error", "errors": { "locale": [...] } }` | Missing or invalid `locale` |
| 429 | `{ "message": "Too Many Requests" }` + `Retry-After: N` header | Rate limited (120/min) |

### Cache

- Backend: `Cache::tags(['blog','blog_categories'])->remember('bookly:blog:category:{slug}:{locale}:{hash}', 300)` — 5 minutes. Stored under tags so `InvalidateBlogCacheJob` can flush it (see ADR 0014).
- Frontend: `revalidate: 300`.

---

## GET /blog/{slug}/preview

Preview an unpublished post via a signed, time-limited token.

### Path Parameters

| Param | Type | Description |
|-------|------|-------------|
| `slug` | `string` | Blog post URL slug |

### Query Parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `token` | `string` | — | **Required**. Signed HMAC token bound to `slug + expires_at` |
| `locale` | `string` | — | **Required**. Content language |

### Response (200)

Returns the full `BlogPostDetail` shape (same as `GET /blog/{slug}`) regardless of post status.

The response includes an additional flag:

```json
{
  "data": {
    // ... all BlogPostDetail fields ...
    "is_preview": true
  }
}
```

### Behavior

- **Token verification** (stateless HMAC):
  1. Decode token → `{ slug, expires_at, hmac }`.
  2. Recompute `hmac_sha256(slug + "|" + expires_at, APP_PREVIEW_KEY)`.
  3. Compare with `hash_equals()`. Mismatch → **403**.
  4. If `expires_at < now()` → **403**.
  5. If token's bound slug ≠ requested `{slug}` → **403** (rebinding prevention).
- Returns the post regardless of status (`draft`, `published`, `archived`).
- `seo` block is omitted (preview is `noindex`).
- `related_tours` and `related_posts` are still computed (to preview the full page).
- Changing a post's slug invalidates outstanding tokens (slug no longer matches).

### Errors

| Status | Body | Condition |
|--------|------|-----------|
| 403 | `{ "message": "Preview link expired." }` | Token invalid, expired, or slug-rebinding attempt |
| 404 | `{ "message": "Blog post not found." }` | Post slug does not exist at all |
| 422 | `{ "message": "Validation error", "errors": { "locale": [...] } }` | Missing or invalid `locale` |
| 429 | `{ "message": "Too Many Requests" }` + `Retry-After: N` header | Rate limited (120/min) |

### Cache

- Backend: **uncached** (never cached).
- Frontend: **uncached** + `robots: { index: false, follow: false }`.
- Sitemap: **excluded**.

---

## Sitemap Extension

The existing `GET /sitemap.xml` (served by `SitemapController`) is extended to include:

1. **Blog posts**: All published posts (`status = 'published' AND scheduled_at <= now()`), streamed
   via `chunkById(500)`, with hreflang alternates for all 3 locales:
   ```xml
   <url>
     <loc>https://bookly.com/en/blog/{slug}</loc>
     <xhtml:link rel="alternate" hreflang="en" href="https://bookly.com/en/blog/{slug}"/>
     <xhtml:link rel="alternate" hreflang="es" href="https://bookly.com/es/blog/{slug}"/>
     <xhtml:link rel="alternate" hreflang="it" href="https://bookly.com/it/blog/{slug}"/>
     <changefreq>weekly</changefreq>
     <priority>0.8</priority>
   </url>
   ```

2. **Blog categories**: All active categories:
   ```xml
   <url>
     <loc>https://bookly.com/en/blog/category/{slug}</loc>
     <changefreq>weekly</changefreq>
     <priority>0.6</priority>
   </url>
   ```

3. **Blog index page**: `https://bookly.com/{locale}/blog` for each locale.

**Cache**: Reuses existing `bookly:sitemap:xml` Redis key (3600s). Regeneration dispatched on
publish/archive/scheduled-transition via queued job that flushes the cache key.

**Rate limit**: Reuses existing `sitemap` limiter (10/min).