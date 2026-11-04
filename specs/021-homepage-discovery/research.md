# Research & Technical Decisions: Homepage and Discovery (Spec 021)

**Feature**: Homepage and Discovery Redesign  
**Date**: 2026-10-29  
**Status**: Complete — all technical decisions and tradeoffs resolved against Bookly Constitution v2.1.0 and Master Plan Phase 4.

---

## 1. Unauthenticated (Guest) Wishlist Lifecycle

### Context & Problem
Travelers browsing the homepage and search results need to bookmark interesting tours without being forced to create an account immediately. Forcing a login modal on every heart click causes drop-off, but guest favorites must not be lost upon account creation or login, nor should logged-out users be exposed to prior users' saved tours on shared devices.

### Decision
Implement a client-side `guestWishlist` manager backed by browser `localStorage` (`bookly_guest_wishlist`):
- **Guest State**: Store an array of tour IDs in `localStorage`. Toggling a heart updates local storage immediately and reflects active state with zero network latency.
- **Merge on Login/Registration**: When a traveler signs in or registers, the frontend sends the array of guest tour IDs to the backend wishlist endpoint (or performs batch merge), deduplicating against already saved items.
- **Sync Failure Resilience**: If the post-login merge request fails due to temporary network issues, local IDs remain intact and retry on subsequent authenticated sessions.
- **Logout Privacy**: When a traveler logs out, the client storage is explicitly cleared, ensuring a subsequent guest on a shared or public device cannot view the previous user's private favorites.

### Alternatives Considered
- *Immediate Auth Modal on Click*: Rejected — creates high friction for top-of-funnel discovery travelers.
- *Full-page Redirect to Login*: Rejected — disrupts discovery context and risks losing scroll position.
- *Anonymous Server Session Cookie*: Rejected — introduces server session state overhead for read-heavy public browsing.

---

## 2. Homepage Section Hierarchy & Resilience Strategy

### Context & Problem
The homepage is composed of 10 distinct vertical sections. If an optional secondary data source fails (e.g. blog feed times out, or no seasonal tours exist), the entire homepage must not crash or display broken white space.

### Decision
Adopt a Server-Side Rendered (SSR) composite page layout in `frontend/src/app/[locale]/(public)/page.tsx` utilizing React Server Components with isolated data resolution:
- **Vertical Hierarchy**:
  1. “Where to?” heading & search hero (`HeroSection`).
  2. Tour-category rapid exploration chips (`CategoryChips`).
  3. Promotional Bookly value panel highlighting trust guarantees (`BooklyValuePanel`).
  4. Popular interest/category cards (`CategoryGrid`).
  5. Featured / traveler-loved horizontal tour rail (`FeaturedTours`).
  6. Top destination showcase (`DestinationShowcase`).
  7. Conditional themed/seasonal tour rails (`ThemedTourRails`).
  8. Blog inspiration preview (`BlogInspiration`).
  9. Partner acquisition invitation panel (`PartnerAcquisitionPanel`).
  10. Expanded application footer (`Footer`).
- **Resilience Strategy**:
  - Core homepage data (`featured_tours`, `popular_categories`, `featured_destinations`, `curated_rails`) is fetched via `getHomepageData(locale)`. If this fails completely, a polite localized recovery alert is shown while keeping the hero search operable.
  - Secondary data (blog posts) is fetched via `Promise.allSettled`. If rejected or empty, the blog section is cleanly omitted (`null` render) with zero impact on other sections.
  - Themed rails without tours are omitted dynamically.

### Alternatives Considered
- *Independent Client-Side SWR/React Query for Each Section*: Rejected — causes visual layout shifts (CLS) and cascading loading spinners that harm SEO and Core Web Vitals.
- *Strict All-or-Nothing SSR*: Rejected — a single transient blog or seasonal query error would 500 the entire homepage.

---

## 3. Data Sourcing for Themed and Seasonal Tour Rails

### Context & Problem
Section 7 requires conditional rails (e.g., Family, Adventure, Seasonal) when matching data exists. Sourcing these via separate client-side or SSR API queries causes request waterfalls and slows down initial page load.

### Decision
Extend the existing backend `GET /api/public/homepage` endpoint in Laravel:
- The backend `GetHomepageDataAction` aggregates and caches a `curated_rails` array in Redis (`homepage.{locale}`).
- Each entry in `curated_rails` is structured as `{ id: string, slug: string, title: string, tours: TourCard[] }`.
- Backend uses existing indexed category/tag relationships and bookable tour scopes to populate up to 6 tours per themed rail.
- If a curated theme has 0 bookable tours, it is excluded from `curated_rails`, allowing the frontend to simply map over the returned list with zero extra logic or requests.

### Alternatives Considered
- *Separate SSR Queries to `/api/public/search`*: Rejected — adds 2-3 additional HTTP roundtrips between Next.js and Laravel during SSR, risking the $<2.0\text{s}$ mobile 3G budget.
- *Client-side In-Memory Filtering from Featured Tours*: Rejected — limited to whatever 8 tours happen to be featured; cannot surface true category-specific inventory.

---

## 4. Tour Card Component Architecture & Standardization

### Context & Problem
Tour cards appear in multiple layouts: horizontal scrolling rails (homepage) and responsive dense grids (category/search listing). Duplicating card logic between rails and grids leads to inconsistent accessibility, divergent pricing formatting, and styling bugs.

### Decision
Standardize on a single, shared `TourCard` component (`frontend/src/components/search/TourCard.tsx`):
- **Aspect Ratio**: Standardized `aspect-[4/3]` cover container with subtle border radius and placeholder background.
- **LCP Optimization**: Accept an `imagePriority` boolean prop. The first visible card in the primary rail receives `priority={true}` (`fetchpriority="high"`, `loading="eager"`), while remaining cards default to `lazy`.
- **Metadata Layout**:
  - Contextual eyebrow: Category and destination/location.
  - Two-line clamped title: `line-clamp-2` with fixed line-height to guarantee equal card heights.
  - Rating: Gold star with numeric average and review count in parentheses.
  - Badges: Optional Bookly badge (`featured`, `best-seller`, `new`) absolutely positioned on the top corner.
  - Wishlist Button: Independent `<button>` with high-contrast focus ring and `aria-label` stating tour title and saved state.
  - Pricing & Duration: "From [formatted price]" prominently displayed alongside duration label.

### Alternatives Considered
- *Separate RailCard and GridCard components*: Rejected — introduces code drift and double maintenance.

---

## 5. Mobile Search Filter Drawer vs Desktop Filter Sidebar

### Context & Problem
On mobile screens ($<768\text{px}$), screen real estate cannot accommodate a permanent left sidebar. Furthermore, re-fetching search results on every single checkbox toggle causes premature scrolling, jitter, and excessive API traffic.

### Decision
Implement a responsive two-mode filter architecture:
- **Desktop ($\ge 1024\text{px}$)**: Left sidebar with instant reactive filters, result count summary, and aligned sorting dropdown.
- **Mobile ($< 768\text{px}$)**:
  - Sticky bottom/top filter trigger button displaying active filter count.
  - Full-height accessible modal drawer (`MobileFilterDrawer`) using focus trapping, Escape key listener, and `aria-modal="true"`.
  - **Staged Filter Application**: Filter changes inside the drawer are held in local state. A sticky bottom bar presents a dynamic "Show [N] Tours" button and a "Reset" button. Tapping the button commits all staged filters to the URL, triggers a single search request, and closes the drawer.
  - Touch targets on all checkboxes, radio buttons, and buttons are guaranteed $\ge 44\times44\text{px}$.
- **State Synchronization**: Active filters are represented as dismissible chips above results on both viewports, fully synchronized with URL search params.

### Alternatives Considered
- *Immediate Live Filtering on Mobile*: Rejected — triggers annoying layout shifts behind the drawer and wastes cellular data.
