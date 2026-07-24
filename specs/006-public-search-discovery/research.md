# Research: Public Search & Discovery

**Feature**: 006-public-search-discovery
**Date**: 2026-05-06

## 1. Search Engine: Meilisearch via Laravel Scout

**Decision**: Use **Meilisearch** as the Scout search driver.

**Rationale**:
- Constitution specifies "Laravel Scout (Meilisearch later)" — this is the natural time to adopt it
- Meilisearch is open-source, self-hostable, and has first-class Laravel Scout support
- Typo-tolerant, filterable, sortable, and supports multi-language indexing natively
- Significantly cheaper than Algolia at Phase 1 scale (5K–10K documents)
- Docker-based deployment aligns with the existing docker-compose infrastructure

**Alternatives considered**:
- **Algolia**: Better managed experience but per-operation pricing becomes costly at scale; overkill for MVP
- **TNTSearch**: SQLite-based, no external dependency, but lacks typo tolerance and multi-language capabilities needed for EN/ES/IT
- **Direct PostgreSQL full-text search**: Violates constitution principle of search/performance separation

## 2. Next.js Rendering Strategy

**Decision**: **SSG for tour detail pages, SSR for search results**, with Incremental Static Regeneration (ISR) for semistatic content.

**Rationale**:
- Tour detail pages benefit from SSG since tour content changes infrequently; ISR (revalidate every 5 minutes) aligns with the 5-minute index update SLA
- Search results and filtered listings MUST be SSR because they depend on dynamic query parameters that change per request
- Homepage, category pages, and destination pages use ISR with a 5-minute revalidation window
- All public pages must be server-rendered HTML (constitution SEO-First rule)

**Alternatives considered**:
- **Full SSR**: Simpler but higher server load; no static CDN caching benefit for detail pages
- **Full SSG with client-side search**: SEO penalty for search pages; violates crawlability requirement
- **Client-side rendering only**: Fails SEO requirements outright

## 3. Multi-Language Routing

**Decision**: Next.js App Router with `[locale]` dynamic segment as the root layout parameter (`app/[locale]/...`).

**Rationale**:
- Next.js App Router natively supports dynamic route segments
- `[locale]` as root segment means all routes inherit locale context
- Middleware-based locale detection reads `Accept-Language` header and redirects `/` to `/en/`, `/es/`, or `/it/`
- All internal links use locale prefix; `next-intl` or similar i18n library manages translations
- Hreflang tags generated per page via `generateMetadata()` exporting `alternates.languages`

**Alternatives considered**:
- **Separate domains per language (en.bookly.com, es.bookly.com)**: More setup, harder local dev, no SEO benefit over subdirectories
- **Query parameter (?lang=en)**: Poor SEO, no crawlable URL structure
- **Cookie-based only**: Not crawlable by search engines

## 4. Rate Limiting Strategy

**Decision**: Laravel rate limiting middleware using Redis as the rate store, applied per-endpoint with per-user tracking (IP + optional session).

**Rationale**:
- Laravel's built-in `RateLimiter` facade with Redis driver is production-proven
- Per-endpoint configuration allows higher limits for browsing (category/destination pages) and lower limits for search queries
- Per-user tracking via `request()->ip()` for unauthenticated travelers; session ID as secondary key
- Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` headers
- Rate limit exceeded returns HTTP 429 with a localized, user-friendly JSON message

**Recommended limits for Phase 1**:
| Endpoint | Limit |
|----------|-------|
| Search (POST/GET) | 60 req/min |
| Tour detail (GET) | 120 req/min |
| Category/Destination (GET) | 120 req/min |
| Homepage data | 120 req/min |
| Sitemap | 10 req/min |

**Alternatives considered**:
- **External API gateway (Cloudflare WAF rate limiting)**: Adds dependency, less granular per-endpoint control
- **No rate limiting**: Risk of search scraping and infrastructure cost spikes

## 5. Structured Data (Schema.org)

**Decision**: JSON-LD structured data using `TouristTrip`, `TouristDestination`, `Organization`, and `BreadcrumbList` schemas.

**Rationale**:
- Google recommends JSON-LD format for structured data
- `TouristTrip` schema maps naturally to Bookly tour detail pages (itinerary, price, duration, location)
- `TouristDestination` for destination landing pages
- `Organization` for the platform brand on the homepage
- `BreadcrumbList` on all navigable pages for enhanced search result display
- Implemented via Next.js `generateMetadata()` and a `<script type="application/ld+json">` block in layout

**Schemas per page type**:
| Page | Primary Schema |
|------|---------------|
| Homepage | Organization, WebSite |
| Search results | SearchResultsPage, ItemList |
| Tour detail | TouristTrip, Offer, Review, AggregateRating |
| Category landing | ItemList |
| Destination landing | TouristDestination, ItemList |

## 6. Sitemap Generation

**Decision**: Dynamic server-side sitemap generation via a dedicated Laravel endpoint, consumed by Next.js for the public-facing `/sitemap.xml`.

**Rationale**:
- Sitemap must list all locale-prefixed URLs (`/en/tours/slug`, `/es/tours/slug`, `/it/tours/slug`)
- Laravel queries published tours and generates the XML; response cached in Redis for 1 hour
- Next.js serves it at the root path or rewrites `/sitemap.xml` to the API
- `robots.txt` references the sitemap URL
- Sitemap is regenerated when tours are published/unpublished (queued job)

**Alternatives considered**:
- **Static sitemap generated at build time**: Stale between deploys; misses newly published tours
- **Next.js `generateSitemap()` at build**: Same staleness issue; 5K–10K entries exceed practical build-time limits

## 7. Caching Strategy

**Decision**: Multi-layer caching: CDN edge cache (Cloudflare), Redis application cache, and Next.js ISR cache.

**Rationale**:
- CDN caches static assets (images from R2, JS/CSS bundles) with long TTLs
- Redis caches API responses for category/destination listings (TTL: 5 minutes, matching index SLA)
- Next.js ISR revalidates tour detail pages every 5 minutes
- Search queries are NOT cached (dynamic, varied, low reuse rate)
- Rate limit counters stored in Redis with per-window expiry

**Cache layers**:
| Layer | What | TTL |
|-------|------|-----|
| Cloudflare CDN | Images, static assets | 7 days |
| Redis | Category/destination API responses, sitemap | 5 min / 1 hour |
| Next.js ISR | Tour detail pages, homepage | 5 min |
| Redis | Rate limit counters | Per window |

## 8. Meilisearch Index Design

**Decision**: Single `tours` index with language-specific searchable attributes, filterable and sortable facets.

**Index configuration**:
- **Searchable attributes**: `title_en`, `title_es`, `title_it`, `description_en`, `description_es`, `description_it`, `location`, `category_name`, `highlights_en`, `highlights_es`, `highlights_it`
- **Filterable attributes**: `status`, `category_slug`, `location_slug`, `price_amount`, `duration_minutes`, `available_dates`
- **Sortable attributes**: `price_amount`, `average_rating`, `created_at`, `review_count`
- **Ranking rules**: `words`, `typo`, `proximity`, `attribute`, `sort`, `exactness`
- **Language-specific behavior**: At query time, `attributesToSearchOn` is set to the current locale's fields only (e.g., for `locale=es`: `['title_es', 'description_es', 'highlights_es', 'location', 'category_name']`). This uses Meilisearch v1.3+ `attributesToSearchOn` feature to restrict text matching to the requested language while keeping shared fields (`location`, `category_name`) always searched. The `locale` parameter is NOT a filterable index field — it controls search scope at query time.

**Alternatives considered**:
- **Separate index per language**: Three indexes to maintain; adds complexity without benefit
- **Single language index**: Would require all searches in English only, failing FR-022
