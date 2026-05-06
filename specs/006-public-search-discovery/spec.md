# Feature Specification: Public Search & Discovery

**Feature Branch**: `006-public-search-discovery`  
**Created**: 2026-05-06  
**Status**: Draft  
**Input**: User description: "Create the public search and discovery specification for Bookly, a tours-only marketplace."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Search for Tours by Keyword (Priority: P1)

A traveler visits Bookly looking for a specific type of tour experience. They type a keyword (e.g., "wine tasting", "hiking", "Rome") into the search bar and receive a list of matching tours. Each result shows essential information: title, cover image, price, average rating, location, and duration — enough for the traveler to decide which tours to explore further.

**Why this priority**: Search is the primary discovery mechanism. Without it, travelers cannot find tours, making the marketplace non-functional. This is the minimum viable entry point.

**Independent Test**: Can be fully tested by entering search queries and verifying that only published tours matching the query appear in results, with correct tour card information displayed.

**Acceptance Scenarios**:

1. **Given** multiple published tours exist across different categories and destinations, **When** a traveler searches for "wine tasting in Tuscany", **Then** only tours whose title, description, location, or category match the search terms appear in results.
2. **Given** tours exist in draft, pending_review, and published states, **When** a traveler performs any search, **Then** only published tours with valid pricing and availability appear; draft and pending tours are excluded.
3. **Given** a traveler searches for a term that matches no tours, **When** the search completes, **Then** a clear empty state message is displayed with suggestions to broaden the search or browse categories.
4. **Given** more than one page of matching results, **When** the traveler views results, **Then** pagination controls are displayed and functional.

---

### User Story 2 - Filter and Sort Search Results (Priority: P2)

After performing a search or browsing a category, a traveler refines results using filters — location/destination, category, price range, duration, and tour date — and changes the sort order to find the most relevant tours for their needs and budget.

**Why this priority**: Filters and sorting transform raw search results into a decision-making tool. Without them, travelers must manually scan all results. This builds directly on P1 search.

**Independent Test**: Can be fully tested by applying individual and combined filters on search results and verifying filtered results match criteria, plus changing sort order and verifying re-ordered results.

**Acceptance Scenarios**:

1. **Given** search results spanning multiple price points, **When** a traveler sets a price range filter, **Then** only tours within that price range are displayed.
2. **Given** search results across multiple categories, **When** a traveler selects a category filter (e.g., "Adventure"), **Then** only tours in that category remain visible.
3. **Given** search results with varying durations, **When** a traveler selects a duration filter (e.g., "Half-day"), **Then** only tours matching that duration range are shown.
4. **Given** active filters are applied, **When** a traveler changes the sort order to "Price: Low to High", **Then** results re-order accordingly while preserving active filters.
5. **Given** active filters are applied, **When** a traveler clears all filters, **Then** full unfiltered results are restored.
6. **Given** a traveler applies a date filter, **When** results load, **Then** only tours with availability on that date are shown; sold-out tours for that date are excluded.

---

### User Story 3 - View Tour Details (Priority: P3)

A traveler clicks on a tour card from search results, a category page, or the homepage and lands on a full tour detail page. The page displays comprehensive information: image gallery, full description, highlights, inclusions, exclusions, meeting point, cancellation policy, pricing breakdown, availability calendar, group size limits, and aggregated reviews — with a clear call-to-action to book.

**Why this priority**: The detail page is where the purchase decision happens. It must provide all information a traveler needs to commit to booking. It depends on search/discovery (P1/P2) to drive traffic to it.

**Independent Test**: Can be fully tested by navigating directly to a tour detail page URL and verifying all tour information sections render correctly with data from a published tour.

**Acceptance Scenarios**:

1. **Given** a published tour with multiple images, **When** a traveler views the tour detail page, **Then** an image gallery is displayed with the cover image first and navigation between images.
2. **Given** a published tour with pricing and availability, **When** a traveler views the tour detail page, **Then** the current price is displayed, an availability calendar shows bookable dates, and a "Book Now" call-to-action is prominent.
3. **Given** a published tour with reviews, **When** a traveler views the tour detail page, **Then** the average rating, review count, and individual reviews are displayed.
4. **Given** a traveler directly accesses a URL for a tour that is unpublished, sold out indefinitely, or does not exist, **When** the page loads, **Then** a 404 or appropriate not-available page is shown with navigation back to search.

---

### User Story 4 - Browse Homepage and Discover Tours (Priority: P4)

A traveler lands on the Bookly homepage without a specific tour in mind. They browse curated sections — featured tours, popular categories, and top destinations — to discover inspiration and navigate naturally into the search experience.

**Why this priority**: The homepage is the brand's front door and primary entry point for undecided travelers. It drives engagement and SEO authority. It can function independently of search.

**Independent Test**: Can be fully tested by loading the homepage and verifying that featured tours, category links, and destination links are displayed and navigate correctly.

**Acceptance Scenarios**:

1. **Given** published tours exist on the platform, **When** a traveler loads the homepage, **Then** featured/popular tours are displayed in a curated section.
2. **Given** tour categories exist, **When** a traveler loads the homepage, **Then** category cards/links are displayed, each linking to a filtered view of tours in that category.
3. **Given** tours exist in multiple destinations, **When** a traveler loads the homepage, **Then** popular destinations are showcased and link to location-filtered search results.
4. **Given** the homepage loads, **When** measured, **Then** the page renders in a search-engine-crawlable format with proper metadata.

---

### User Story 5 - Multi-Language Browsing Experience (Priority: P5)

A traveler accesses Bookly in their preferred language — English, Spanish, or Italian. The entire public browsing experience (URLs, page content, tour information, search UI, filters, metadata) is served in the selected language. Search engines can crawl and index each language variant independently.

**Why this priority**: Multi-language support is a Phase 1 requirement and a key differentiator, but it layers on top of the core discovery features. Content must exist before it can be translated.

**Independent Test**: Can be fully tested by accessing the site in each language via its locale-prefixed URL and verifying all UI text, tour content, and metadata render in the correct language.

**Acceptance Scenarios**:

1. **Given** a tour has content in English, Spanish, and Italian, **When** a traveler accesses `/en/tours/slug`, **Then** the page displays English content; `/es/tours/slug` shows Spanish; `/it/tours/slug` shows Italian.
2. **Given** a traveler is browsing in Spanish, **When** they perform a search, **Then** search matches against Spanish-language tour content and UI labels are in Spanish.
3. **Given** a language variant page exists, **When** viewed by a search engine crawler, **Then** proper hreflang tags indicate all language variants and a canonical tag points to the correct language URL.
4. **Given** a traveler accesses a tour URL without a locale prefix, **When** the request is processed, **Then** the system redirects to the appropriate language variant based on browser preferences or defaults to English.

---

### Edge Cases

- What happens when a tour is published but has zero available dates? It is excluded from search results and its detail page shows "Currently Unavailable" with no booking CTA.
- What happens when a tour's price changes after a traveler loads the page but before they click "Book"? The booking flow captures the price at checkout time; a mismatch message informs the traveler if the price changed.
- What happens when a traveler applies conflicting filters (e.g., price range $0-50 but all tours in category cost $100+)? Empty results are shown with a message explaining no tours match the combination and suggesting filter adjustments.
- What happens when a traveler searches with special characters or excessively long queries? Input is sanitized; queries exceeding a reasonable length (255 characters) are truncated.
- What happens when the search index is being rebuilt or is temporarily unavailable? A graceful degradation message is shown ("Search is temporarily unavailable, please try again shortly") rather than an error page.
- How does the system handle tours that were bookable when indexed but become sold out before the traveler views them? Real-time availability is checked when the detail page loads and at checkout; discrepancies surface to the traveler.
- What happens when a traveler bookmarks a tour that later gets unpublished or archived? The URL returns a 410 (Gone) or redirects to search with a message that the tour is no longer available.
- How are tours without all translations handled? If a tour has content in English but not Spanish, the Spanish detail page falls back to English content with a note that translation is pending, or the tour is hidden in that language until translations are complete.
- What happens when a user exceeds rate limits on search endpoints? A friendly message is displayed indicating the limit has been reached with a suggestion to wait and try again shortly; the response includes a Retry-After indication.

## Clarifications

### Session 2026-05-06

- Q: What rate limiting should apply to public search/discovery endpoints? → A: Per-endpoint rate limits with per-user tracking, server-side rate storage, and user-friendly responses when exceeded.
- Q: What is the expected platform scale for Phase 1? → A: 5,000–10,000 tours, 30–50 categories, 200–500 concurrent travelers. Design for scalability (indexes, caching, pagination) but keep implementation simple without over-engineering.
- Q: What accessibility standard should public-facing pages target? → A: WCAG 2.1 Level AA, covering color contrast, full keyboard navigation, screen reader support, alt text for all images, and clear understandable forms.
- Q: Should tour URL slugs be translated per language or shared across all locales? → A: Shared slug across all locales, generated from the default (English) title. SEO differentiation is handled via hreflang tags, translated page content, and translated meta tags.
- Q: Within what timeframe must tour changes be reflected in search results? → A: Under 5 minutes via async queued index updates. Tour detail pages perform real-time availability checks as a safety net for any staleness within that window.

## Requirements *(mandatory)*

### Functional Requirements

**Search & Results**

- **FR-001**: System MUST provide a text search that matches traveler queries against tour title, description, location, category, and highlights.
- **FR-002**: System MUST exclude tours that are not in `published` status from all search results and discovery surfaces.
- **FR-003**: System MUST exclude published tours that lack valid pricing or have zero upcoming availability from search results.
- **FR-004**: System MUST return search results with pagination, displaying a configurable number of results per page (default: 12).
- **FR-005**: System MUST display tour listing cards containing: cover image, tour title, location, duration, starting price, average rating, and review count.
- **FR-006**: System MUST display a clear empty state when search returns no results, with suggestions to modify search criteria or browse categories.

**Filtering & Sorting**

- **FR-007**: System MUST support filtering search results by: location/destination, category, price range, duration, and available date.
- **FR-008**: System MUST support combining multiple filters simultaneously.
- **FR-009**: System MUST support sorting results by: relevance (default), price (low-to-high and high-to-low), average rating, and newest first.
- **FR-010**: System MUST allow travelers to clear all active filters with a single action.
- **FR-011**: System MUST reflect active filter and sort state in the URL so filtered views are shareable and bookmarkable.

**Tour Detail Page**

- **FR-012**: System MUST display a tour detail page with: image gallery (with cover image first), full title, full description, highlights, inclusions, exclusions, meeting point, cancellation policy, duration, group size limits, and languages offered.
- **FR-013**: System MUST display current pricing and an availability calendar on the tour detail page.
- **FR-014**: System MUST display aggregated review information (average rating, total review count, individual reviews) on the tour detail page.
- **FR-015**: System MUST show a prominent booking call-to-action on the tour detail page for tours with availability.
- **FR-016**: System MUST show a "Currently Unavailable" state on the detail page for published tours with no upcoming availability, with the booking CTA disabled or hidden.
- **FR-017**: System MUST return a 404 or appropriate not-found page for URLs corresponding to unpublished, rejected, or non-existent tours.

**Homepage & Discovery**

- **FR-018**: System MUST provide a homepage with curated sections including: featured/popular tours, category browsing, and destination discovery.
- **FR-019**: System MUST provide category landing pages that display all published tours within a selected category.
- **FR-020**: System MUST provide destination landing pages that display all published tours for a selected destination/location.

**Multi-Language**

- **FR-021**: System MUST serve all public pages under locale-prefixed URL paths (`/en/`, `/es/`, `/it/`). Tour URL slugs are shared across all locales, generated from the default-language (English) tour title, producing URLs like `/en/tours/wine-tasting` and `/es/tours/wine-tasting`.
- **FR-022**: System MUST display tour content (title, description, highlights, inclusions, exclusions) in the language matching the current locale.
- **FR-023**: System MUST serve all UI chrome (navigation, filters, buttons, labels, empty states, error messages) in the current locale language.
- **FR-024**: System MUST include proper hreflang tags on every page to indicate all available language variants.
- **FR-025**: System MUST set a self-referencing canonical URL for each language variant.

**SEO & Metadata**

- **FR-026**: System MUST render all public pages as server-rendered, crawlable HTML.
- **FR-027**: System MUST include page-specific meta title and meta description on every public page.
- **FR-028**: System MUST include Open Graph (og:title, og:description, og:image, og:url, og:type) tags on every public page.
- **FR-029**: System MUST include structured data (schema.org) markup on tour listing and tour detail pages.
- **FR-030**: System MUST generate and serve an XML sitemap listing all indexable public pages with their language variants.
- **FR-031**: System MUST generate and serve a robots.txt file referencing the sitemap.

**Performance & Responsiveness**

- **FR-032**: System MUST serve all public pages with responsive layouts that function correctly across mobile, tablet, and desktop viewports.
- **FR-033**: System MUST achieve a Lighthouse Performance score of 90 or above on all public page types.
- **FR-034**: System MUST enforce per-endpoint rate limits on public search and discovery endpoints, tracked per-user (by session or IP), and respond with a clear, friendly message when a limit is exceeded rather than a generic error.
- **FR-035**: System MUST meet WCAG 2.1 Level AA accessibility standards across all public pages, including: sufficient color contrast, full keyboard navigability, screen reader compatibility, descriptive alt text on all images, and clearly labeled form controls.
- **FR-036**: System MUST reflect tour changes (publish, unpublish, price, availability) in search results within 5 minutes of the change, using async queued index updates. Tour detail pages MUST perform a real-time availability check on load to cover any staleness within the update window.

### Key Entities

- **Tour (Public View)**: Represents a tour as displayed to travelers. Includes: title, description, highlights, inclusions, exclusions, meeting point, cancellation policy, duration, location, category, cover image, image gallery, minimum/maximum group size, languages offered, and aggregated review data. Only published tours with pricing and availability are visible.
- **Category**: A classification grouping similar tours (e.g., Adventure, Cultural, Food & Wine, Nature). Used for filtering and discovery navigation.
- **Destination / Location**: A geographic area or city where tours operate. Used for filtering and destination landing pages.
- **Tour Card**: A summarized representation of a tour in listing views. Contains the minimal information needed for a traveler to decide whether to click through to the detail page.
- **Search Query**: A traveler's text input combined with active filters, sort preference, and pagination offset. The URL reflects the query state for shareability.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can find a relevant tour within 10 seconds of arriving on the platform, measured from page load to first click on a tour card.
- **SC-002**: Search results appear in under 2 seconds for 95% of queries under normal load.
- **SC-003**: Tour detail pages load and become interactive in under 3 seconds on a standard mobile connection.
- **SC-004**: The platform achieves a Lighthouse Performance score of 90 or above on homepage, search results, and tour detail pages.
- **SC-005**: 80% of travelers who land on the homepage or a search page click through to at least one tour detail page.
- **SC-006**: All public pages are fully indexed by major search engines (Google, Bing) within 48 hours of sitemap submission.
- **SC-007**: Zero search results return tours that are unpublished, unapproved, or have no bookable availability.
- **SC-008**: Filter and sort operations produce updated results in under 1.5 seconds.
- **SC-009**: The public website renders correctly and is fully navigable in English, Spanish, and Italian with no missing translations or broken locale-specific URLs.
- **SC-010**: System supports a catalog of 5,000 to 10,000 published tours across 30 to 50 categories without degradation of search or browsing performance.
- **SC-011**: System handles 200 to 500 concurrent travelers searching and browsing simultaneously while meeting all performance targets (SC-002, SC-003, SC-008).

## Assumptions

- Tours have complete and accurate content (text, images, translations) as managed by partners and approved by admins through the workflows defined in specs 002, 003, 004, and 005.
- Availability and pricing data is maintained by partners (spec 004) and queried in real-time or near-real-time for search result filtering and detail page display.
- The search infrastructure (Laravel Scout) is configured and available; search index updates are triggered automatically when tours are created, updated, published, or archived.
- A CDN (Cloudflare) is configured for asset delivery and caching of static resources.
- Images are stored in Cloudflare R2 (spec 003) and served with appropriate caching headers.
- Language detection follows standard browser `Accept-Language` header parsing with English as the fallback default.
- The XML sitemap is regenerated or updated when tours are published, unpublished, or have significant content changes.
- Category and destination taxonomies are predefined and managed by administrators.
- The booking flow and payment processing are handled by separate features (specs 007 and 008); this spec only covers the discovery and browsing experience leading up to the booking CTA.
- The system is designed with scalability in mind (proper indexes, caching layers, efficient pagination) but avoids over-engineering; implementation complexity stays proportional to Phase 1 scale targets.
