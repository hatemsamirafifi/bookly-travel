# Data Model: Public Search & Discovery

**Feature**: 006-public-search-discovery
**Date**: 2026-05-06

## Entities

### Tour (Searchable)

Represents a tour indexed for public search and discovery. This is a projection of the Tour entity from spec 003, enriched with pricing/availability data from spec 004 and review aggregates from spec 009.

**Search index fields** (Meilisearch `tours` index):

| Field | Type | Searchable | Filterable | Sortable | Source |
|-------|------|------------|------------|----------|--------|
| `id` | integer | — | — | — | `tours.id` |
| `title_en` | string | yes | — | — | `tour_translations.title` (locale=en) |
| `title_es` | string | yes | — | — | `tour_translations.title` (locale=es) |
| `title_it` | string | yes | — | — | `tour_translations.title` (locale=it) |
| `description_en` | string | yes | — | — | `tour_translations.description` (locale=en) |
| `description_es` | string | yes | — | — | `tour_translations.description` (locale=es) |
| `description_it` | string | yes | — | — | `tour_translations.description` (locale=it) |
| `highlights_en` | string | yes | — | — | `tour_translations.highlights` (locale=en) |
| `highlights_es` | string | yes | — | — | `tour_translations.highlights` (locale=es) |
| `highlights_it` | string | yes | — | — | `tour_translations.highlights` (locale=it) |
| `slug` | string | — | — | — | `tours.slug` (shared across locales) |
| `location` | string | yes | yes | — | `tours.location` |
| `location_slug` | string | — | yes | — | Normalized location slug |
| `category_name` | string | yes | — | — | `categories.name` |
| `category_slug` | string | — | yes | — | `categories.slug` |
| `price_amount` | integer | — | yes | yes | `pricing.base_price` (lowest available) |
| `price_currency` | string | — | yes | — | `pricing.currency` |
| `duration_minutes` | integer | — | yes | — | `tours.duration_minutes` |
| `duration_label` | string | — | — | — | Human-readable (e.g., "3 hours", "Full day") |
| `average_rating` | float | — | — | yes | Aggregated from reviews |
| `review_count` | integer | — | — | yes | Count of published reviews |
| `cover_image_url` | string | — | — | — | URL to cover image (R2/CDN) |
| `image_urls` | [string] | — | — | — | All image URLs |
| `group_size_min` | integer | — | — | — | `tours.group_size_min` |
| `group_size_max` | integer | — | — | — | `tours.group_size_max` |
| `available_dates` | [date] | — | yes | — | Upcoming dates with availability |
| `languages` | [string] | — | yes | — | Languages the tour is offered in |
| `status` | string | — | yes | — | Always `published` in index |
| `created_at` | datetime | — | — | yes | `tours.created_at` |
| `updated_at` | datetime | — | — | — | `tours.updated_at` |
| `published_at` | datetime | — | — | — | `tours.published_at` (null if never published) |

**Locale-aware search behavior**:
The `locale` query parameter is NOT a filterable index field. Instead, `SearchToursAction` uses Meilisearch's `attributesToSearchOn` (v1.3+) to restrict text matching to the current locale's fields at query time:
- `locale=en` → searches `title_en`, `description_en`, `highlights_en`, `location`, `category_name`
- `locale=es` → searches `title_es`, `description_es`, `highlights_es`, `location`, `category_name`
- `locale=it` → searches `title_it`, `description_it`, `highlights_it`, `location`, `category_name`

Shared fields (`location`, `category_name`) are always included since they are language-independent.

**Validation rules** (applied before indexing):
- `status` MUST equal `published`
- `price_amount` MUST be > 0 and non-null
- `available_dates` MUST have at least one future date
- All language fields MUST be non-empty for at least the default language (EN)
- `cover_image_url` MUST be non-null

**Database-only fields** (not in search index, used by `GetTourDetailAction`):
- `published_at`: nullable timestamp, set when tour first transitions to `published` status, never cleared. Used to distinguish 404 (never published) from 410 (was published, now archived) on the tour detail endpoint.

**Lifecycle**:
```
Tour created/updated → dispatch IndexTourAction job → Meilisearch upsert
Tour unpublished → dispatch RemoveFromIndexAction job → Meilisearch delete
Tour archived → dispatch RemoveFromIndexAction job → Meilisearch delete
```

### Category

Represents a tour category used for filtering and discovery browsing.

**Attributes**:

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Primary key |
| `name_en` | string | Display name (English) |
| `name_es` | string | Display name (Spanish) |
| `name_it` | string | Display name (Italian) |
| `slug` | string | URL-safe identifier |
| `description_en` | string | Category description (English) |
| `description_es` | string | Category description (Spanish) |
| `description_it` | string | Category description (Italian) |
| `image_url` | string | Optional category image |
| `tour_count` | integer | Denormalized count of published tours in category |
| `display_order` | integer | Ordering for homepage display |
| `is_active` | boolean | Whether category is shown on site |

**Relationships**:
- Has many Tours (through `tours.category_id`)

### Destination / Location

Represents a geographic area or city where tours operate.

**Attributes**:

| Field | Type | Description |
|-------|------|-------------|
| `slug` | string | URL-safe normalized location name |
| `name_en` | string | Display name (English) |
| `name_es` | string | Display name (Spanish) |
| `name_it` | string | Display name (Italian) |
| `country_en` | string | Country name (English) |
| `country_es` | string | Country name (Spanish) |
| `country_it` | string | Country name (Italian) |
| `image_url` | string | Destination hero image |
| `tour_count` | integer | Denormalized count of published tours at destination |
| `is_featured` | boolean | Whether highlighted on homepage |

**Note**: In Phase 1, destinations are derived from `tours.location` values rather than a separate managed entity. A normalized `location_slug` is computed from the location string. This may evolve into a managed entity in future phases.

### Search Query State

Not persisted — represents the in-flight state of a traveler's search session, reflected in URL parameters.

**URL parameters**:

| Parameter | Type | Example |
|-----------|------|---------|
| `q` | string | `wine+tasting` |
| `category` | string | `adventure` |
| `location` | string | `rome` |
| `price_min` | integer | `0` |
| `price_max` | integer | `200` |
| `duration` | string | `half-day`, `full-day`, `multi-day` |
| `date` | date | `2026-06-15` |
| `sort` | string | `price_asc`, `price_desc`, `rating`, `newest` |
| `page` | integer | `1` |

### Rate Limit Entry

Runtime state stored in Redis, not persisted.

**Redis key pattern**: `rate_limit:{endpoint}:{user_identifier}:{window_timestamp}`

**Attributes**:
- `attempts`: integer — remaining requests in window
- `available_at`: timestamp — when the window resets
- TTL: auto-expires after the rate limit window

## State Transitions

### Tour Search Visibility

```
                       partner creates tour
                              │
                              ▼
                    ┌─── draft ──────┐
                    │                │
                    ▼                ▼
             pending_review      archived (hidden)
                    │
              admin approves
                    │
                    ▼
               published ◄────────── admin unpublishes
                    │
                    │  (sets published_at if null)
                    │  (has pricing + availability)
                    ▼
            ┌─ VISIBLE in search ─┐
            │                     │
            │  availability runs  │  partner removes
            │  out / tour date    │  all availability
            │  passes             │
            ▼                     ▼
       hidden from search    hidden from search
       (detail: "unavail")   (detail: "unavail")
```
