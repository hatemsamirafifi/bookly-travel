# Implementation Plan: Public Search & Discovery

**Branch**: `006-public-search-discovery` | **Date**: 2026-05-06 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/006-public-search-discovery/spec.md`

## Summary

Deliver the public search and discovery experience for Bookly travelers — keyword search with filters and sorting, tour detail pages, homepage discovery, category and destination landing pages, all served in three languages (EN/ES/IT) with SSR/SSG rendering for SEO. The search infrastructure is backed by Laravel Scout (Meilisearch), all public pages are localized with hreflang support, and the system is rate-limited per endpoint.

## Technical Context

**Language/Version**: TypeScript 5.x (Next.js 16 frontend), PHP 8.x (Laravel backend)
**Primary Dependencies**: Next.js 16 (App Router), Laravel (API-only), Laravel Scout (Meilisearch driver), Tailwind CSS, Redis
**Storage**: PostgreSQL (primary), Redis (cache/queue/sessions), Cloudflare R2 (images), Meilisearch (search index)
**Testing**: Pest/PHPUnit (backend), Jest + Playwright (frontend)
**Target Platform**: Web (SSR/SSG public pages, Cloudflare CDN)
**Project Type**: Web application (Next.js frontend + Laravel API backend)
**Performance Goals**: Lighthouse Performance ≥ 90, search results < 2s p95, detail page < 3s, filter/sort < 1.5s
**Constraints**: WCAG 2.1 Level AA, 5-minute search index update SLA, per-endpoint rate limiting, shared tour slugs across locales
**Scale/Scope**: 5,000–10,000 tours, 30–50 categories, 200–500 concurrent travelers

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| I. Marketplace-First | ✅ PASS | Search only surfaces partner-owned tours; platform mediates all discovery |
| II. Tours-Only Discipline | ✅ PASS | Search, filters, and discovery are tours-only; no hotels/flights/transfers |
| III. Direct Booking Only | ✅ PASS | Detail page "Book Now" CTA links to instant booking (spec 007); no request-to-book |
| IV. Admin-Governed Publishing | ✅ PASS | FR-002/FR-003 exclude non-published tours and tours without pricing/availability |
| V. Platform-Controlled Commerce | ✅ PASS | Booking flows through platform (linked to specs 007/008); this spec is discovery only |
| VI. Completed-Booking Review Integrity | ✅ PASS | FR-014 shows reviews only from completed bookings |
| API-First | ✅ PASS | All data served via Laravel API consumed by Next.js; no server-rendered HTML from backend |
| Search & Performance Separation | ✅ PASS | Scout/Meilisearch abstracts search; no direct SQL for search queries |
| SEO-First | ✅ PASS | FR-026 through FR-031 mandate SSR, meta tags, OG, structured data, sitemap, robots.txt |
| Thin Controllers | ✅ PASS | API design follows service/action pattern; controllers handle request routing only |
| Queueing & Async Work | ✅ PASS | Search index updates dispatched to Redis queue (FR-036) |
| Security-First Mandate | ✅ PASS | FR-034 adds rate limiting; public endpoints are read-only |
| WCAG Accessibility | ✅ PASS | FR-035 mandates WCAG 2.1 Level AA |

**Gate Result**: ALL PASS — No violations. Proceed to Phase 0.

## Project Structure

### Documentation (this feature)

```text
specs/006-public-search-discovery/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/
│   │   └── Search/
│   │       ├── Actions/
│   │       │   ├── SearchToursAction.php
│   │       │   ├── IndexTourAction.php
│   │       │   └── RemoveFromIndexAction.php
│   │       ├── Services/
│   │       │   └── SearchService.php
│   │       └── Controllers/
│   │           └── Public/
│   │               ├── SearchController.php
│   │               ├── TourDetailController.php
│   │               ├── CategoryController.php
│   │               ├── DestinationController.php
│   │               └── SitemapController.php
│   ├── Models/
│   │   ├── Tour.php (adds Scout Searchable trait, scopes)
│   │   └── Category.php
│   └── Http/
│       └── Middleware/
│           └── RateLimitSearchMiddleware.php
├── routes/
│   └── api.php (adds public search routes)
└── tests/
    └── Feature/
        └── Search/
            ├── SearchToursTest.php
            ├── TourDetailTest.php
            ├── CategoryTest.php
            └── RateLimitTest.php

frontend/
├── src/
│   ├── app/
│   │   └── [locale]/
│   │       ├── page.tsx                    # Homepage
│   │       ├── search/
│   │       │   └── page.tsx                # Search results
│   │       ├── tours/
│   │       │   └── [slug]/
│   │       │       └── page.tsx            # Tour detail
│   │       ├── categories/
│   │       │   └── [slug]/
│   │       │       └── page.tsx            # Category landing
│   │       └── destinations/
│   │           └── [slug]/
│   │               └── page.tsx            # Destination landing
│   ├── components/
│   │   ├── search/
│   │   │   ├── SearchBar.tsx
│   │   │   ├── SearchResults.tsx
│   │   │   ├── TourCard.tsx
│   │   │   ├── FilterPanel.tsx
│   │   │   ├── SortDropdown.tsx
│   │   │   └── Pagination.tsx
│   │   ├── tour/
│   │   │   ├── TourDetail.tsx
│   │   │   ├── ImageGallery.tsx
│   │   │   ├── AvailabilityCalendar.tsx
│   │   │   ├── ReviewList.tsx
│   │   │   └── BookingCTA.tsx
│   │   ├── home/
│   │   │   ├── HeroSection.tsx
│   │   │   ├── FeaturedTours.tsx
│   │   │   ├── CategoryGrid.tsx
│   │   │   └── DestinationShowcase.tsx
│   │   └── layout/
│   │       ├── Header.tsx
│   │       ├── Footer.tsx
│   │       └── LocaleSwitcher.tsx
│   ├── lib/
│   │   ├── api/
│   │   │   ├── search.ts
│   │   │   ├── tours.ts
│   │   │   └── categories.ts
│   │   └── hooks/
│   │       ├── useSearch.ts
│   │       └── useFilters.ts
│   └── i18n/
│       ├── en.json
│       ├── es.json
│       └── it.json
└── tests/
    ├── e2e/
    │   ├── search.spec.ts
    │   └── tour-detail.spec.ts
    └── unit/
        ├── TourCard.test.tsx
        └── FilterPanel.test.tsx
```

**Structure Decision**: Web application structure with shared backend. The `Search` domain is a new modular domain in the backend. Frontend follows Next.js App Router with `[locale]` dynamic segment for multi-language routing. Search components are organized by surface (search, tour detail, homepage).

## Complexity Tracking

> No constitution violations. This section is intentionally empty.
