# Tasks: Public Search & Discovery

**Input**: Design documents from `/specs/006-public-search-discovery/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Included — search and discovery are critical public paths per plan.md testing structure.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `backend/` at repository root
- **Frontend**: `frontend/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization — Meilisearch, Scout, and environment configuration

- [x] T001 Add Meilisearch service to `docker-compose.yml` with v1.10 image, port 7700, master key env var, and named volume
- [x] T002 [P] Add `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` to `backend/.env` and `backend/.env.example`
- [x] T003 Install Laravel Scout and Meilisearch PHP client via `composer require laravel/scout meilisearch/meilisearch-php` in backend
- [x] T004 [P] Publish Scout configuration via `php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"` in backend

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 Create Search domain directory structure: `backend/app/Domains/Search/Actions/`, `backend/app/Domains/Search/Services/`, `backend/app/Domains/Search/Controllers/Public/`
- [x] T006 Add `Searchable` trait and `toSearchableArray()` method to `backend/app/Models/Tour.php` with all fields defined in data-model.md (language-specific titles, descriptions, highlights, plus slug, location, category, price, duration, rating, images, dates)
- [x] T007 Add `shouldBeSearchable()` method to `backend/app/Models/Tour.php` returning true only when status is `published`, valid pricing exists, and upcoming availability exists
- [x] T008 [P] Create `backend/app/Http/Middleware/RateLimitSearchMiddleware.php` skeleton with `RateLimiter` facade, per-endpoint configuration array, and per-IP tracking returning 429 with `Retry-After` header and JSON error body
- [x] T009 [P] Create frontend i18n directory `frontend/src/i18n/` with empty placeholder files `en.json`, `es.json`, `it.json`
- [x] T010 Create Next.js `[locale]` layout at `frontend/src/app/[locale]/layout.tsx` with locale validation (`en`/`es`/`it`), `<html lang={locale}>`, and providers wrapper
- [x] T011 [P] Create locale middleware at `frontend/src/middleware.ts` that parses `Accept-Language` header, redirects root `/` to `/{detected-locale}`, and validates `[locale]` param
- [x] T012 [P] Create shared API client at `frontend/src/lib/api/client.ts` with base URL from env, `Accept: application/json` header, error handling, and type-safe fetch wrapper
- [x] T013 Create base TypeScript types at `frontend/src/lib/api/types.ts` for `Tour`, `TourCard`, `Category`, `Destination`, `SearchParams`, `SearchResponse`, `TourDetailResponse`, `HomepageData`, `PricingMeta`
- [x] T014 Register public API route group in `backend/routes/api.php` with prefix `public` and rate limit middleware applied

**Checkpoint**: Foundation ready — Meilisearch configured, Tour searchable, frontend i18n routing active. User story implementation can now begin.

---

## Phase 3: User Story 1 - Search for Tours by Keyword (Priority: P1) 🎯 MVP

**Goal**: Traveler types a keyword into the search bar and receives a paginated list of matching published tours with tour card information (cover image, title, price, rating, location, duration)

**Independent Test**: Enter a search query via the API or frontend and verify only published, priced, available tours matching the query appear in paginated results with correct card data

### Implementation for User Story 1

- [x] T015 [P] [US1] Configure Meilisearch index settings for `tours` index in `backend/app/Domains/Search/Actions/ConfigureSearchIndexAction.php` — searchableAttributes, filterableAttributes (status, category_slug, location_slug, price_amount), sortableAttributes (price_amount, average_rating, created_at), rankingRules
- [x] T016 [P] [US1] Create `backend/app/Domains/Search/Actions/SearchToursAction.php` accepting `SearchParams` DTO (query, locale, page, perPage) and calling `Tour::search()` with Meilisearch filters for `status=published`, locale language match, and pagination
- [x] T017 [US1] Create `backend/app/Domains/Search/Controllers/Public/SearchController.php` with `search` method that validates `locale` (required), `q`, `page` params, invokes `SearchToursAction`, and returns JSON response matching search-api.md contract structure including `data[]` (TourCard shape) and `meta` (pagination)
- [x] T018 [US1] Add search route `GET /api/public/search/tours` to `backend/routes/api.php` pointing to `SearchController@search` with rate limit middleware (60 req/min)
- [x] T019 [P] [US1] Create `frontend/src/lib/api/search.ts` with `searchTours(params: SearchParams)` function calling `/api/public/search/tours` via the shared API client
- [x] T020 [P] [US1] Create `frontend/src/components/search/SearchBar.tsx` with text input, submit-on-enter, and client-side navigation to `/search?q=...`
- [x] T021 [P] [US1] Create `frontend/src/components/search/TourCard.tsx` displaying cover image, title, location, duration label, formatted price, average rating stars, review count — linking to `/tours/{slug}`
- [x] T022 [US1] Create `frontend/src/components/search/SearchResults.tsx` rendering a grid of `TourCard` components from search data, with loading skeleton and empty state ("No tours found — try broadening your search or browse categories")
- [x] T023 [P] [US1] Create `frontend/src/components/search/Pagination.tsx` with Previous/Next buttons, current page indicator, disabled states at boundaries, preserving query params
- [x] T024 [US1] Create search results page at `frontend/src/app/[locale]/search/page.tsx` as SSR page reading `q`, `page` from `searchParams`, calling `searchTours()`, rendering `<SearchBar>` + `<SearchResults>` + `<Pagination>`, with `generateMetadata()` for SEO title/meta
- [x] T025 [P] [US1] Create backend feature test `backend/tests/Feature/Search/SearchToursTest.php` covering: valid search returns matching tours, excludes non-published tours, excludes tours without pricing/availability, empty query returns all available, empty results shows empty state, pagination boundaries
- [x] T026 [P] [US1] Create frontend E2E test `frontend/tests/e2e/search.spec.ts` covering: search bar input navigates to results, results display tour cards, clicking card navigates to detail, pagination controls work, empty state renders

**Checkpoint**: User Story 1 fully functional — keyword search with paginated tour card results, independently testable via API and frontend. 🎯 MVP ready.

---

## Phase 4: User Story 2 - Filter and Sort Search Results (Priority: P2)

**Goal**: Traveler refines search results using filters (location, category, price range, duration, date) and changes sort order (relevance, price, rating, newest) — filters and sort state reflected in URL

**Independent Test**: Apply individual and combined filters on search results, verify filtered results match criteria, change sort order and verify re-ordering, verify URL reflects all active parameters

### Implementation for User Story 2

- [x] T027 [US2] Extend `backend/app/Domains/Search/Actions/SearchToursAction.php` to accept and apply filter parameters: `category` slug, `location` slug, `price_min`/`price_max` ranges, `duration` (half-day/full-day/multi-day mapping to minute ranges), and `date` (filter to tours with availability on date)
- [x] T028 [US2] Extend `backend/app/Domains/Search/Actions/SearchToursAction.php` to accept `sort` parameter and map to Meilisearch sort: `price_asc` → `price_amount:asc`, `price_desc` → `price_amount:desc`, `rating` → `average_rating:desc`, `newest` → `created_at:desc`, default `relevance` (no explicit sort)
- [x] T029 [US2] Extend `backend/app/Domains/Search/Controllers/Public/SearchController.php` to validate new filter and sort query parameters and pass them to action
- [x] T030 [US2] Add dynamic facet aggregation to `SearchToursAction` returning available `categories[]`, `locations[]`, `price_range`, `durations[]` in the `filters` response object matching search-api.md contract
- [x] T031 [P] [US2] Create `frontend/src/lib/hooks/useFilters.ts` hook managing filter state, URL searchParams sync (read on mount, update on change), and deriving active filter count
- [x] T032 [P] [US2] Create `frontend/src/components/search/FilterPanel.tsx` with collapsible filter sections: Category (checkboxes from facet data), Location (checkboxes), Price Range (min/max inputs), Duration (radio group), Date (date input) — each dispatching URL updates via `useFilters`
- [x] T033 [P] [US2] Create `frontend/src/components/search/SortDropdown.tsx` with options: Relevance, Price: Low to High, Price: High to Low, Top Rated, Newest — updating `sort` URL param
- [x] T034 [US2] Update `frontend/src/app/[locale]/search/page.tsx` to render `<FilterPanel>` in sidebar and `<SortDropdown>` above results, pass all searchParams to API, and handle combined filter+sort URL state
- [x] T035 [P] [US2] Add filter/sort test cases to `backend/tests/Feature/Search/SearchToursTest.php`: category filter excludes other categories, price range filter bounds results, date filter excludes sold-out dates, combined filters, sort order verification, URL state reflection
- [x] T036 [P] [US2] Add filter/sort test cases to `frontend/tests/e2e/search.spec.ts`: applying a filter updates URL and results, clearing filters restores full results, sort dropdown changes result order, combined filters work

**Checkpoint**: Filter and sort fully functional on top of search — refinable, shareable URLs. US1 + US2 both operational.

---

## Phase 5: User Story 3 - View Tour Details (Priority: P3)

**Goal**: Traveler clicks a tour card and lands on a full tour detail page with image gallery, description, highlights, inclusions/exclusions, meeting point, cancellation policy, pricing, availability calendar, group size, reviews, and a prominent "Book Now" CTA

**Independent Test**: Navigate directly to a tour detail URL, verify all content sections render correctly, availability calendar shows bookable dates, reviews display, and Book Now CTA is present for available tours

### Implementation for User Story 3

- [x] T037 [P] [US3] Create `backend/app/Domains/Search/Actions/GetTourDetailAction.php` accepting slug and locale, querying Tour with translations for locale (fallback to EN), pricing, availability (next 30 days), reviews aggregate, and returning full tour detail DTO including SEO metadata (canonical, hreflang)
- [x] T038 [US3] Create `backend/app/Domains/Search/Controllers/Public/TourDetailController.php` with `show` method validating `slug` (path) and `locale` (query), invoking `GetTourDetailAction`, returning JSON matching tour-detail-api.md contract — 404 for missing/unpublished, 410 for archived
- [x] T039 [US3] Add tour detail route `GET /api/public/tours/{slug}` to `backend/routes/api.php` pointing to `TourDetailController@show` with rate limit middleware (120 req/min)
- [x] T040 [P] [US3] Create `frontend/src/lib/api/tours.ts` with `getTourDetail(slug: string, locale: string)` function calling `/api/public/tours/{slug}`
- [x] T041 [P] [US3] Create `frontend/src/components/tour/ImageGallery.tsx` with cover image first, thumbnail navigation, lightbox/fullscreen on click, keyboard arrow navigation, alt text on all images
- [x] T042 [P] [US3] Create `frontend/src/components/tour/AvailabilityCalendar.tsx` highlighting available dates, showing "Select a date" prompt, disabling past dates, and exposing selected date to parent
- [x] T043 [P] [US3] Create `frontend/src/components/tour/ReviewList.tsx` showing aggregate rating (stars + number), rating distribution bars (5★–1★), and individual reviews (avatar, name, date, rating, comment) with pagination for many reviews
- [x] T044 [P] [US3] Create `frontend/src/components/tour/BookingCTA.tsx` showing price display, participant count selector (min/max group size constrained), date confirmation, and "Book Now" button linking to booking flow — or "Currently Unavailable" state when no availability
- [x] T045 [US3] Create `frontend/src/components/tour/TourDetail.tsx` composing all sections: `<ImageGallery>`, title/rating/location header, description, highlights list, inclusions/exclusions lists, meeting point with map link, cancellation policy, `<AvailabilityCalendar>`, `<ReviewList>`, `<BookingCTA>`
- [x] T046 [US3] Create tour detail page at `frontend/src/app/[locale]/tours/[slug]/page.tsx` as ISR page (`revalidate: 300`) with `generateStaticParams` returning top published tour slugs, calling `getTourDetail()`, rendering `<TourDetail>`, and `generateMetadata()` with tour-specific title/description/OG/canonical/hreflang
- [x] T047 [P] [US3] Create backend feature test `backend/tests/Feature/Search/TourDetailTest.php` covering: valid slug returns full detail, 404 for draft/rejected tour, 404 for nonexistent slug, content returned in correct locale, availability data is real-time, reviews included
- [x] T048 [P] [US3] Create frontend E2E test `frontend/tests/e2e/tour-detail.spec.ts` covering: page loads with all sections, image gallery navigation, availability calendar interaction, "Currently Unavailable" state, 404 page for bad slug

**Checkpoint**: Tour detail page fully functional — comprehensive tour information with booking path. US1 + US2 + US3 operational.

---

## Phase 6: User Story 4 - Browse Homepage and Discover Tours (Priority: P4)

**Goal**: Traveler lands on the homepage and discovers tours through curated sections (featured tours, popular categories, top destinations), plus dedicated category and destination landing pages

**Independent Test**: Load homepage, verify featured tours/categories/destinations render and link correctly. Navigate to category and destination pages, verify filtered tour listings appear

### Implementation for User Story 4

- [x] T049 [P] [US4] Create `backend/app/Domains/Search/Actions/GetHomepageDataAction.php` querying featured tours (ordered by rating or admin-curated flag), active categories with tour counts, and featured destinations — returning aggregated JSON per category-destination-api.md
- [x] T050 [P] [US4] Create `backend/app/Domains/Search/Actions/GetCategoryToursAction.php` accepting category slug + standard search/filter/sort params, returning paginated tours within that category
- [x] T051 [P] [US4] Create `backend/app/Domains/Search/Actions/GetDestinationToursAction.php` accepting location slug + standard search/filter/sort params, returning paginated tours at that destination
- [x] T052 [US4] Create `backend/app/Domains/Search/Controllers/Public/HomepageController.php` with `index` method — validates `locale`, invokes `GetHomepageDataAction`, returns homepage JSON with tours, categories, destinations, and SEO metadata
- [x] T053 [US4] Create `backend/app/Domains/Search/Controllers/Public/CategoryController.php` with `index` (list all categories) and `tours` (paginated tours for a category slug) methods
- [x] T054 [US4] Create `backend/app/Domains/Search/Controllers/Public/DestinationController.php` with `index` (list featured destinations) and `tours` (paginated tours for a location slug) methods
- [x] T055 [US4] Add routes to `backend/routes/api.php`: `GET /api/public/homepage`, `GET /api/public/categories`, `GET /api/public/categories/{slug}/tours`, `GET /api/public/destinations`, `GET /api/public/destinations/{slug}/tours` — all with rate limit (120 req/min)
- [x] T056 [P] [US4] Create `frontend/src/lib/api/categories.ts` with `getCategories()`, `getCategoryTours()` functions and `frontend/src/lib/api/destinations.ts` with `getDestinations()`, `getDestinationTours()` functions
- [x] T057 [P] [US4] Create `frontend/src/components/home/HeroSection.tsx` with hero image, headline in current locale, and prominent search bar
- [x] T058 [P] [US4] Create `frontend/src/components/home/FeaturedTours.tsx` rendering a horizontal scrollable row of `TourCard` components for featured tours, with "View all" link to search
- [x] T059 [P] [US4] Create `frontend/src/components/home/CategoryGrid.tsx` rendering category cards (image, name, tour count) linking to `/categories/{slug}`
- [x] T060 [P] [US4] Create `frontend/src/components/home/DestinationShowcase.tsx` rendering destination cards (image, name, country, tour count) linking to `/destinations/{slug}`
- [x] T061 [US4] Create homepage at `frontend/src/app/[locale]/page.tsx` as ISR page (`revalidate: 300`), calling homepage API, composing `<HeroSection>`, `<FeaturedTours>`, `<CategoryGrid>`, `<DestinationShowcase>`
- [x] T062 [US4] Create category page at `frontend/src/app/[locale]/categories/[slug]/page.tsx` as ISR page rendering category header + tour grid using shared search components, with `generateMetadata()` for category-specific SEO
- [x] T063 [US4] Create destination page at `frontend/src/app/[locale]/destinations/[slug]/page.tsx` as ISR page rendering destination header + tour grid, with `generateMetadata()` for destination-specific SEO
- [x] T064 [P] [US4] Create backend feature test `backend/tests/Feature/Search/CategoryTest.php` covering: category listing returns active categories, category tours filtered correctly, 404 for invalid slug, pagination works within category context
- [x] T065 [P] [US4] Add homepage/destination test cases to `backend/tests/Feature/Search/TourDetailTest.php` or create `backend/tests/Feature/Search/HomepageTest.php` covering homepage data structure (tours, categories, destinations present)

**Checkpoint**: Homepage and discovery surfaces fully functional. US1–US4 all operational.

---

## Phase 7: User Story 5 - Multi-Language Browsing Experience (Priority: P5)

**Goal**: All public pages served under locale-prefixed URLs (`/en/`, `/es/`, `/it/`) with UI chrome, tour content, metadata, and search all in the selected language. Search engines can crawl and index each language variant independently

**Independent Test**: Access site in each language via locale URL, verify all UI text and tour content renders in that language, verify hreflang and canonical tags are correct per language

### Implementation for User Story 5

- [x] T066 [P] [US5] Populate `frontend/src/i18n/en.json` with all English UI strings: navigation labels, search placeholder, filter labels (category, location, price range, duration, date), sort options, empty states, error messages, rate limit message, pagination labels, booking CTA text, currency formatting
- [x] T067 [P] [US5] Populate `frontend/src/i18n/es.json` with all Spanish translations of strings from en.json
- [x] T068 [P] [US5] Populate `frontend/src/i18n/it.json` with all Italian translations of strings from en.json
- [x] T069 [US5] Create `frontend/src/components/layout/LocaleSwitcher.tsx` showing current locale flag/label with dropdown to switch, navigating to same page in selected locale (preserving path and query params)
- [x] T070 [US5] Update `frontend/src/app/[locale]/layout.tsx` to add `<LocaleSwitcher>` in header and load i18n messages for the current locale
- [x] T071 [US5] Add `generateMetadata()` locale-awareness across all pages ensuring `alternates.languages` with `en/es/it` URLs, `metadataBase` with correct locale prefix, and `openGraph.locale` set per language per FR-024/FR-025
- [x] T072 [US5] Update `backend/app/Domains/Search/Controllers/Public/SearchController.php` and `TourDetailController.php` to support locale-based content fallback: if requested locale lacks translation, return English content with `translation_warning: partial` flag in JSON per edge case spec
- [x] T073 [US5] Create backend middleware `backend/app/Http/Middleware/SetLocaleFromRequest.php` that reads `locale` query param or `Accept-Language` header and sets app locale for response formatting (dates, numbers, currency)
- [x] T074 [P] [US5] Create frontend E2E test `frontend/tests/e2e/i18n.spec.ts` covering: each locale loads correct UI text, locale switcher works, hreflang tags present on tour detail, canonical is self-referencing, switching languages preserves page context

**Checkpoint**: Full multi-language experience — all 3 locales independently crawlable, consistent UX, proper SEO signals. All user stories complete.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: SEO completeness, performance, accessibility, and final integration validation

- [x] T075 [P] Create `backend/app/Domains/Search/Controllers/Public/SitemapController.php` with `index` method generating XML sitemap listing homepage, all published tour detail pages, category pages, and destination pages — each with `xhtml:link` hreflang alternates per seo-contracts.md
- [x] T076 [P] Add sitemap route `GET /api/public/sitemap.xml` to `backend/routes/api.php` and `GET /robots.txt` returning text file referencing sitemap URL per seo-contracts.md
- [x] T077 [P] Add JSON-LD structured data components: `frontend/src/components/seo/StructuredData.tsx` with TouristTripSchema, ItemListSchema, OrganizationSchema — each rendering `<script type="application/ld+json">` per seo-contracts.md
- [x] T078 Integrate structured data components into their respective pages: OrganizationSchema on homepage, TouristTripSchema on tour detail
- [x] T079 [P] Create `frontend/src/components/layout/Header.tsx` with Bookly logo, main navigation (Home, Categories, Destinations), `<LocaleSwitcher>`, and mobile hamburger menu
- [x] T080 [P] Create `frontend/src/components/layout/Footer.tsx` with footer links, copyright, social placeholders, and locale-specific content
- [x] T081 Update `frontend/src/app/[locale]/layout.tsx` to include `<Header>` and `<Footer>` wrapping all pages
- [ ] T082 Run Lighthouse audit on homepage, search results, and tour detail pages — optimize to achieve score ≥ 90 on Performance per SC-004: image optimization (WebP, lazy loading, responsive sizes), font loading strategy, bundle size analysis, CDN caching headers
- [ ] T083 Run WCAG 2.1 AA accessibility audit across all page types per FR-035: verify color contrast ratios, keyboard navigation (tab order, focus indicators, skip-to-content), screen reader labels (aria-labels, roles, alt text), form input labeling — fix all AA-level violations
- [x] T084 [P] Add `backend/app/Domains/Search/Actions/IndexTourAction.php` and `backend/app/Domains/Search/Actions/RemoveFromIndexAction.php` — queued jobs for async index updates per FR-036, dispatched via Tour model events (created, updated, deleted) with 5-minute processing window
- [x] T085 [P] Wire Tour model observers/listeners in `backend/app/Providers/EventServiceProvider.php` to dispatch `IndexTourAction` on Tour saved (when shouldBeSearchable) and `RemoveFromIndexAction` on Tour deleted/unpublished
- [ ] T086 Validate full quickstart.md flow end-to-end: docker compose up, scout:import, API curl test, frontend dev server, all page types render
- [x] T087 [P] Create backend feature test `backend/tests/Feature/Search/RateLimitTest.php` verifying 429 response after exceeding per-endpoint limits, Retry-After header present, rate limit resets after window expiry
- [x] T088 [P] Create backend feature test `backend/tests/Feature/Search/SitemapTest.php` verifying sitemap XML structure, hreflang alternates present per URL, only published tours listed, valid XML schema

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **User Stories (Phases 3–7)**: All depend on Foundational phase completion
  - US1 (P1): Can start immediately after Phase 2 — No dependencies on other stories
  - US2 (P2): Can start after Phase 2 — Builds on US1 search components but independently testable
  - US3 (P3): Can start after Phase 2 — Uses shared types/client but independently testable
  - US4 (P4): Can start after Phase 2 — Uses shared components but independently testable
  - US5 (P5): Builds on all prior stories for locale coverage, but i18n infrastructure is independent
- **Polish (Phase 8)**: Depends on all desired user stories being complete

### Within Each User Story

- Backend actions before controllers
- Controllers before routes (or simultaneous)
- API client functions before frontend components
- Shared components before page composition
- Page implementation before tests run against it

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- **Once Foundational phase completes, US1, US2, US3, US4 can all start in parallel** (different files, different concerns)
- Within each story: all [P] tasks can run in parallel
- US5 can start in parallel with US1–US4 for i18n file population (T066–T068)

---

## Parallel Example: User Story 1

```bash
# Launch all [P] tasks for US1 together:
Task: "Configure Meilisearch index settings in backend/app/Domains/Search/Actions/ConfigureSearchIndexAction.php"
Task: "Create SearchToursAction in backend/app/Domains/Search/Actions/SearchToursAction.php"
Task: "Create search API client at frontend/src/lib/api/search.ts"
Task: "Create SearchBar component at frontend/src/components/search/SearchBar.tsx"
Task: "Create TourCard component at frontend/src/components/search/TourCard.tsx"
Task: "Create Pagination component at frontend/src/components/search/Pagination.tsx"

# Then sequential:
Task: "Create SearchController (depends on SearchToursAction)"
Task: "Create SearchResults component (depends on TourCard)"
Task: "Create /search page (depends on SearchResults, Pagination, SearchBar)"

# Tests in parallel:
Task: "Backend SearchToursTest + Frontend search E2E"
```

---

## Parallel Example: Multi-Story Parallelism (After Foundational)

```bash
# Developer A: US1 - Search
Task: "Phase 3 tasks T015–T024"

# Developer B: US2 - Filters & Sort (can start same day)
Task: "Phase 4 tasks T027–T034"

# Developer C: US3 - Tour Detail (completely independent files)
Task: "Phase 5 tasks T037–T046"

# Developer D: US4 - Homepage & Discovery (completely independent)
Task: "Phase 6 tasks T049–T063"

# All merge independently after Foundational checkpoint
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T014) — ⚠️ CRITICAL
3. Complete Phase 3: User Story 1 (T015–T024)
4. **STOP and VALIDATE**: Test keyword search independently via API and frontend
5. Run tests (T025–T026)
6. Deploy/demo — working search MVP

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 (Keyword Search) → Test independently → **MVP deploy**
3. Add US2 (Filters & Sort) → Test independently → Deploy (refinable search)
4. Add US3 (Tour Detail) → Test independently → Deploy (full browsing flow)
5. Add US4 (Homepage & Discovery) → Test independently → Deploy (complete discovery)
6. Add US5 (Multi-Language) → Test independently → Deploy (localized experience)
7. Polish (SEO, a11y, perf) → Final deploy
8. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers after Phase 2:

- Developer A: US1 → US5 (search + i18n integration)
- Developer B: US3 → US4 (detail pages + discovery)
- Developer C: US2 (filters/sort can merge into US1 when done)

---

## Notes

- [P] tasks = different files, no dependencies — safe to run concurrently
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- All tours must have English content at minimum (default fallback)
- Meilisearch index must be rebuilt (`scout:import`) after model/searchable changes
- Rate limits are per-IP for unauthenticated users; consider session-based for authenticated users
