# Feature Specification: Homepage and Discovery Redesign

**Feature Branch**: `021-homepage-discovery`  
**Created**: 2026-10-29 (Africa/Cairo)  
**Spec ID / Master-plan phase**: Spec 021 / Phase 4 — Homepage and Discovery  
**Governing Constitution**: [Bookly Constitution v2.1.0](../../.specify/memory/constitution.md)  
**Baseline / Dependencies**: Branched from `claude/dreamy-lamport-8417b8` at `6fcdbb83578f1beede7bf2512d60b1ad76054f0f` (incorporating accepted Spec 020 Tour Detail Experience verification, Jest 857/857 pass, and strict Lighthouse reports). Builds upon Spec 018 (Design System and Shells), Spec 019 (Tour Content Data Model and API), and Spec 006 (Public Search and Discovery API). Phase 3 is accepted; Phase 4 is active; Specs 022–027 remain subsequent planned phases.  
**Status**: Draft — specification created and quality-validated; ready for implementation planning.  
**Input**: User description: "Spec: 021-homepage-discovery in docs/bookly-ui-redesign-spec-kit-master-plan.md", resolved through the approved [UI Redesign Master Plan, Phase 4](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-4---homepage-and-discovery).

---

## Clarifications

### Session 2026-10-29
- Q: How should the wishlist button behave for unauthenticated (guest) travelers? → A: Option A (LocalStorage for Guest Wishlist). Allow guests to add/remove tours without signing in, storing favorites in `localStorage`. Automatically merge them into the user's account wishlist upon login or registration (deduplicating entries). Preserve guest favorites locally if synchronization fails, and clear client-side state on logout so previous user favorites are not exposed.
- Q: What is the navigation behavior when clicking a tour-category chip on the homepage? → A: Option A (Direct Navigation to Search). Clicking a category chip navigates directly to `/[locale]/search?category=<slug>` with the selected category pre-applied in the filter state and matching tours displayed, preserving existing search, filtering, sorting, and pagination functionality.
- Q: How are themed and seasonal tour rails sourced for the homepage? → A: Option A (Backend-Aggregated Curated Rails). Extend `GET /api/public/homepage` to return a `curated_rails` array containing eligible themed and seasonal collections (`[{ id, title, slug, tours }]`); frontend dynamically renders each non-empty rail in sequence and omits empty sections, reusing existing tour models and business rules without separate API waterfalls, preserving the homepage SSR $< 2.0\text{s}$ target.
- Q: How are filter changes applied inside the mobile search filter drawer? → A: Option A (Staged Filters with Live Count Button). Filter selections remain staged in local drawer state while toggling to avoid premature API requests; a sticky bottom bar displays a dynamic "Show [N] Tours" confirmation button and a "Reset" action (touch targets $\ge 44\times44\text{px}$); tapping confirmation commits staged filters to the URL, refreshes results, and closes the drawer; reopening the drawer rehydrates the current active URL filter state.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Discover and Explore Curated Experiences on the Homepage (Priority: P1)

As a traveler visiting the Bookly homepage, I want an inspiring, well-structured visual showcase with search, tour-category chips, curated tour rails, popular categories, and top destinations, so that I can quickly discover travel experiences that match my taste and intent.

**Why this priority**: The homepage is the primary entry gate and brand anchor for Bookly. Providing an intuitive, curated discovery layout immediately drives user engagement and navigation into the catalog.

**Independent Test**: Load the homepage in any supported locale (`en`, `es`, `it`). Verify all available discovery sections render in the exact specified order, load fast without layout shifts, and allow immediate interaction.

**Acceptance Scenarios**:

1. **Given** complete homepage catalog data, **When** a traveler loads the homepage, **Then** the sections render in exact vertical order:
   1. “Where to?” heading and search hero.
   2. Tour-category chips for rapid exploration.
   3. Promotional Bookly value panel highlighting marketplace trust (without third-party advertisements).
   4. Interest/category cards with images and tour counts.
   5. Featured / traveler-loved horizontal tour rail.
   6. Featured destinations showcase.
   7. Conditional interest/seasonal tour rails (family, adventure, seasonal) when matching data exists.
   8. Blog inspiration articles preview.
   9. Partner acquisition invitation panel.
   10. Expanded application footer.
2. **Given** a traveler clicks a tour-category chip, **When** pressed, **Then** they navigate directly to `/[locale]/search?category=<slug>` with the selected category pre-applied, matching tours displayed, and full search, filtering, sorting, and pagination controls intact.
3. **Given** a traveler clicks a category card or destination card, **When** navigated, **Then** the catalog displays experiences specific to that category or destination.

---

### User Story 2 - Search and Filter Tours with Complete State Preservation (Priority: P1)

As a traveler searching for activities, I want a comprehensive search experience with a persistent search bar, filter sidebar on desktop, full-height filter drawer on mobile, active filter chips, and flexible sorting, so that I can narrow down hundreds of tours to the exact ones matching my schedule, destination, and budget.

**Why this priority**: Discovery is useless without effective refinement. Travelers must be able to filter by price, category, destination, rating, and duration while keeping their place and query parameters intact.

**Independent Test**: Perform searches from the homepage and directly on the search page; apply combinations of filters and sorting on both desktop and mobile; verify URL query persistence, history navigation (Back/Forward), and result accuracy.

**Acceptance Scenarios**:

1. **Given** the homepage "Where to?" search bar, **When** a traveler inputs a destination or keyword and submits, **Then** they are taken to the search listing page with the query populated and results matching the criteria.
2. **Given** the desktop search listing page, **When** viewed on a screen width >= 1024px, **Then** a search summary with total result count, a left filter sidebar, an aligned sort control (price low/high, rating, popularity), and a dense tour grid are displayed.
3. **Given** the mobile search listing page, **When** viewed on screen width < 768px, **Then** sticky filter and sort buttons appear; clicking the filter button opens a full-height accessible filter drawer with touch targets >= 44x44px; filter selections are staged locally while toggling, a sticky bottom bar displays a dynamic "Show [N] Tours" action and a "Reset" button, and confirming commits filters to the URL, refreshes results, and closes the drawer.
4. **Given** applied filters (e.g. price range, specific categories, rating), **When** active, **Then** dismissible filter chips appear above the results, allowing one-click removal of individual filters or clearing all filters at once.
5. **Given** applied search parameters and filters, **When** the traveler reloads the page, shares the URL, or uses browser Back/Forward navigation, **Then** all filter states, sort selection, query terms, and pagination are faithfully restored from the URL.
6. **Given** a search query returning no results, **When** rendered, **Then** an honest, helpful empty state appears suggesting filter adjustments, without crashing or blank screens.

---

### User Story 3 - Inspect Redesigned Tour Cards and Manage Wishlist (Priority: P2)

As a traveler browsing discovery rails, category grids, or search results, I want rich, standardized tour cards displaying high-quality imagery, ratings, duration, pricing context, badges, and a one-click wishlist action, so that I can evaluate and save interesting tours effortlessly.

**Why this priority**: Tour cards are the core reusable discovery atom across the entire marketplace. They must present trustworthy facts consistently across horizontal rails and vertical grids.

**Independent Test**: Inspect tour cards in horizontal rails and search grids; toggle wishlist state with mouse, touch, and keyboard; verify visual badge variants and focus indicators.

**Acceptance Scenarios**:

1. **Given** any tour card in a rail or grid, **When** rendered, **Then** it presents:
   - A standardized 4:3 image ratio with rounded corners and subtle border tokens.
   - An interactive wishlist heart button positioned consistently on the top corner.
   - An optional Bookly badge (e.g., "Featured", "Best Seller") when supplied by backend data.
   - Clear location and category contextual metadata.
   - A two-line clamped title preventing card height misalignment.
   - Gold star rating with review count (or honest "New" / unrated indicator if zero reviews).
   - Duration label (e.g., "3 hours", "Full day").
   - A prominent "From [price]" display formatted in the active locale's currency.
2. **Given** an unauthenticated traveler clicks the wishlist button, **When** pressed, **Then** the tour is saved or removed from guest favorites in browser `localStorage` with immediate optimistic visual feedback and screen-reader announcement; guest favorites automatically merge into the authenticated account upon login or registration without duplication, remain stored locally if synchronization fails, and are wiped on logout to avoid exposing private user lists.
3. **Given** an authenticated traveler clicks the wishlist button, **When** toggled, **Then** the button updates immediately to reflect active/inactive state, persists to the traveler's profile, and announces the updated state to screen readers via ARIA attributes.
4. **Given** keyboard navigation, **When** a user tabs through tour cards, **Then** both the primary card link and the independent wishlist button show high-contrast, WCAG AA compliant focus rings.

---

### User Story 4 - Resilient Discovery under Partial or Failing Network States (Priority: P2)

As a traveler on a variable or slow internet connection, I want the homepage to load rapidly and handle missing or failed sections gracefully, so that a temporary glitch in secondary data does not ruin my ability to search and book.

**Why this priority**: In a composite marketplace, third-party services or optional feeds (like blog articles or seasonal campaigns) might experience transient timeouts. The core booking discovery path must remain 100% resilient.

**Independent Test**: Simulate partial API failures (e.g., blog feed fails, seasonal tours empty, partner panel omitted); verify the homepage renders surviving sections without broken spaces or error walls.

**Acceptance Scenarios**:

1. **Given** secondary data (e.g. blog posts, seasonal tours) is empty or fails to load, **When** the homepage renders, **Then** only the affected section is cleanly hidden; all surviving sections (hero, search, categories, destinations, featured tours) remain completely functional.
2. **Given** primary homepage catalog data fails completely, **When** the page renders, **Then** a polite, localized recovery alert appears offering a retry action, while maintaining the hero search and global navigation intact.
3. **Given** slow image delivery, **When** cards load, **Then** clean background placeholder surfaces prevent layout shifts (CLS <= 0.05), and the first visible cover image receives high fetch priority to optimize Largest Contentful Paint (LCP).

---

## Edge Cases

- **Zero Search Results**: When filters or query terms produce no matching tours, the system must show a helpful empty-state illustration, explain which filters are active, and provide an instant "Reset all filters" button.
- **Extreme Title or Location Lengths**: Titles must clamp at exactly 2 lines with an ellipsis (`line-clamp-2`), and locations/subtitles must clamp at 1 line, ensuring cards in the same row or rail maintain uniform alignment.
- **Missing or Broken Images**: When a tour, category, or destination lacks an image or the image URL fails, an accessible placeholder with category icon and fallback label must display instead of a broken image icon.
- **Currency and Numeric Formatting Across Locales**: Currency symbols and decimal separators must adhere to locale conventions (e.g., `$150` in EN, `150 €` in ES/IT) without corrupting the numeric amount.
- **Rapid Wishlist Toggling**: Rapid clicks on the wishlist heart icon must be debounced and handle optimistic UI updates with rollback on network failure.
- **No Categories or Destinations Configured**: If category or destination endpoints return empty arrays, those sections must hide entirely without leaving empty header titles on the page.
- **Guest Wishlist Synchronization & Logout Isolation**: If network synchronization fails after login, guest favorites must remain safely in `localStorage` for automatic retry on subsequent requests; when a user logs out, the client wishlist cache must be flushed to ensure the next guest on the device cannot view the prior user's private saved tours.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST render homepage discovery sections in the strict hierarchical sequence: (1) Hero “Where to?” search, (2) Category chips, (3) Bookly value panel, (4) Interest/category cards, (5) Featured tour rail, (6) Featured destinations, (7) Conditional seasonal/interest rails, (8) Blog inspiration, (9) Partner acquisition panel, and (10) Global footer.
- **FR-002**: System MUST isolate each homepage section so that partial data or section-level errors hide only the affected section, preserving all other functional sections.
- **FR-003**: System MUST display a localized error banner with recovery options only when primary homepage catalog data is completely unavailable.
- **FR-004**: Hero search input MUST accept destination names, attractions, and keywords in all supported languages, submitting queries to the search results route with URL encoding.
- **FR-005**: Category chips MUST display popular tour categories as interactive pills that navigate directly to `/[locale]/search?category=<slug>` with the category filter pre-applied, preserving all existing search, filtering, sorting, and pagination semantics.
- **FR-006**: Bookly value panel MUST communicate platform guarantees (direct confirmation, vetted partners, transparent pricing) without third-party advertisements or external tracking scripts.
- **FR-007**: Horizontal tour rails (including primary featured tours and backend-aggregated `curated_rails`) MUST provide smooth, touch-friendly horizontal snap scrolling on mobile and responsive step navigation on desktop, dynamically rendering each non-empty rail and hiding empty ones without layout shifts.
- **FR-008**: Category showcase grid MUST display category cards with title, cover image, and localized tour count (e.g., "12 tours" / "جولة 12").
- **FR-009**: Destination showcase MUST display top travel destinations with name, country, and available tour count, linking to pre-filtered destination catalog views.
- **FR-010**: Blog inspiration section MUST display up to 3 recent editorial articles with cover image, category, read time, and title when articles exist, linking to the blog catalog.
- **FR-011**: Partner acquisition panel MUST present a secondary call-to-action inviting tour operators to register, linking to the partner registration workflow.
- **FR-012**: Tour cards MUST standardize on a 4:3 image aspect ratio with lazy loading, except for the first visible rail card which MUST receive high fetch priority for LCP optimization.
- **FR-013**: Tour cards MUST feature an accessible wishlist button that toggles saved state with immediate optimistic visual feedback and ARIA state announcements for both guest and authenticated travelers.
- **FR-014**: Tour cards MUST support optional Bookly badge variants (`featured`, `best-seller`, `new`), rendered with high-contrast, accessible design tokens.
- **FR-015**: Tour cards MUST display location context, a 2-line clamped title, duration badge, gold rating average with total review count, and a "From [price]" pricing label.
- **FR-016**: Tour cards MUST maintain visible, high-contrast keyboard focus indicators complying with WCAG 2.1 AA on all focusable elements (card link and wishlist action).
- **FR-017**: Desktop search interface MUST provide a left filter sidebar, result count summary, sort dropdown, and dense results grid.
- **FR-018**: Mobile search interface MUST provide sticky filter/sort triggers opening a full-height accessible modal drawer with touch targets >= 44x44px; filter selections inside the drawer MUST remain staged in local state until the traveler confirms via a sticky bottom "Show [N] Tours" action or resets via a "Reset" button, committing to URL parameters and rehydrating state upon reopening.
- **FR-019**: Search interface MUST display active filter chips with individual dismiss buttons and a "Clear all" action.
- **FR-020**: Search interface MUST synchronize all applied filters, sort selections, search queries, and pagination state with URL query parameters and browser history.
- **FR-021**: System MUST preserve existing search API parameters and response contracts without introducing breaking changes to backend endpoints.
- **FR-022**: All user interface text, labels, place markers, and error messages MUST be fully externalized and localized for English (`en`), Spanish (`es`), and Italian (`it`).
- **FR-023**: System MUST format prices, dates, and numbers according to active locale rules without hardcoded currencies.
- **FR-024**: System MUST allow guests to add and remove tours from their wishlist without signing in, persisting tour IDs in `localStorage`, automatically deduplicating and merging them into the authenticated account wishlist upon login or registration, preserving local items if synchronization fails, and clearing client storage on logout to prevent exposing private wishlists.

---

### Key Entities

- **HomepageCatalog**: The aggregated homepage data structure containing `featured_tours`, `popular_categories`, `featured_destinations`, `curated_rails` (array of `CuratedRail` collections), and SEO metadata.
- **CuratedRail**: A themed or seasonal collection entity containing `id`, localized `title`, `slug`, and an array of `TourCardItem` entries.
- **TourCardItem**: The standardized tour summary entity containing identifier, title, slug, cover image URL, duration label, location, category, rating summary (average score and count), pricing object (amount, currency, formatted label), optional badge, and wishlist status.
- **SearchFilterState**: The active search criteria entity containing keyword query, destination identifier, category identifier, price range (min/max), rating threshold, duration filter, sort option, and pagination page number.
- **CategorySummary**: Category entity containing identifier, slug, localized name, description, image URL, and active tour count.
- **DestinationSummary**: Destination entity containing identifier, slug, localized name, country, image URL, and active tour count.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Homepage initial server-side render (SSR) delivers in under 2.0 seconds on standard mobile 3G networks.
- **SC-002**: Zero automated WCAG 2.1 AA accessibility violations (100% Axe-core clean; Lighthouse Accessibility score >= 90) across all discovery and search pages.
- **SC-003**: 100% of user-selected search filters, sorting options, and queries survive page reloads and browser Back/Forward navigation.
- **SC-004**: 100% of partial data failures (e.g. blog feed offline or optional rails empty) degrade gracefully by omitting the affected section without page crashes or blank views.
- **SC-005**: All interactive touch targets (wishlist button, category pills, filter drawer controls, card links) meet or exceed the 44x44 CSS pixel minimum size requirement on mobile viewports.
- **SC-006**: Cumulative Layout Shift (CLS) on the homepage and search listing remains <= 0.05 across all supported viewports and languages.
- **SC-007**: 100% of tour cards in horizontal rails and search grids share a unified component implementation without duplicated rendering logic.
- **SC-008**: Comprehensive automated test coverage (unit and end-to-end) validates complete, partial, empty, and API-error states across all homepage sections.

---

## Assumptions

- **Existing Backend API Availability**: The existing public search (`/api/public/search`) and homepage (`/api/public/homepage`) endpoints provide baseline catalog data and will be augmented or wrapped without breaking backward compatibility.
- **Wishlist Integration**: Unauthenticated wishlist actions store identifiers in browser `localStorage` and merge into the account upon authentication; authenticated actions sync directly with the traveler profile API.
- **Image Hosting**: All tour and category imagery is delivered from configured CDN/asset storage with predictable aspect ratios or valid object-fit cover styling.
- **Supported Languages**: The marketplace actively supports English (`en`), Spanish (`es`), and Italian (`it`); Arabic and RTL are out of scope per the Bookly Constitution.
- **No Third-Party Advertising**: In accordance with the Bookly Constitution, no external advertisements, sponsored networks, or tracking iframes are permitted on discovery surfaces.
