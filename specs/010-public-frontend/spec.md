# Feature Specification: Public Frontend — Search, Booking & Payments

**Feature Branch**: `010-public-frontend`  
**Created**: 2026-05-19  
**Status**: Draft  
**Constitution**: v1.0.1  
**Input**: User description: "Complete the public-facing frontend for Bookly: homepage, tour search and discovery, tour detail pages, booking checkout flow, and Stripe payment integration. Scaffold from existing Stitch UI designs and rebuild as reusable Next.js components matching the design system."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Discover Tours on Homepage (Priority: P1)

A traveler visits the Bookly homepage and is immediately immersed in a premium travel marketplace experience. They see a hero section with a global search bar, featured tour categories, popular destinations, and highlighted tour cards — all reflecting the Stitch design system (Navy #0A2540, Gold #FFB800, Inter typography, 8px grid).

**Why this priority**: The homepage is the first touchpoint for all travelers. It sets brand perception and drives all downstream conversions. Without a polished homepage, no other feature matters.

**Independent Test**: Can be fully tested by navigating to `/{locale}/` and verifying hero rendering, category display, featured tours, and responsive layout delivers value as a standalone landing page.

**Acceptance Scenarios**:

1. **Given** a traveler visits `/en/`, **When** the page loads, **Then** they see a hero section with a search bar (Location, Dates, Guests fields), at least 4 tour categories, and 6+ featured tour cards with cover image, title, price, rating, location, and duration.
2. **Given** a traveler visits `/es/`, **When** the page loads, **Then** all static text is rendered in Spanish and the URL reflects the locale.
3. **Given** a traveler visits `/it/`, **When** the page loads, **Then** all static text is rendered in Italian and the URL reflects the locale.
4. **Given** the homepage loads on a mobile device (≤ 780px), **When** the traveler scrolls, **Then** the layout adapts to a single-column mobile view matching the Stitch "BooklyTravel Home Mobile" design.
5. **Given** a search engine crawls `/en/`, **When** it reads the response, **Then** it receives server-rendered HTML with meta title, meta description, Open Graph tags, `hreflang` alternates, and JSON-LD structured data.

---

### User Story 2 - Search and Filter Tours (Priority: P1)

A traveler searches for tours using the global search bar or navigates to the tour listing page. They can filter by location, category, price range, duration, and date. Results appear as a grid of tour cards with sorting options.

**Why this priority**: Search and discovery is the primary conversion funnel. Travelers who cannot find tours cannot book them.

**Independent Test**: Can be fully tested by navigating to `/{locale}/tours`, entering search terms, applying filters, and verifying results render correctly.

**Acceptance Scenarios**:

1. **Given** a traveler is on `/en/tours`, **When** the page loads, **Then** they see a filterable, sortable grid of published tour cards.
2. **Given** a traveler enters "Rome" in the search bar, **When** they submit, **Then** tour cards matching "Rome" in title, description, or location are displayed.
3. **Given** a traveler applies filters (category: "History & Culture", price: €50–€150), **When** filters are applied, **Then** only matching tours are shown and the URL query parameters update.
4. **Given** a traveler selects "Price: Low to High" sorting, **When** applied, **Then** tour cards reorder by ascending price.
5. **Given** no tours match the applied filters, **When** results are empty, **Then** an informative empty state is displayed with suggestions.
6. **Given** there are more than 12 tours matching, **When** the traveler scrolls or clicks "Load More", **Then** additional results are loaded (pagination or infinite scroll).
7. **Given** a traveler views the listing on mobile (≤ 780px), **When** the layout renders, **Then** filters collapse into a slide-out panel and cards stack in a single column.

---

### User Story 3 - View Tour Details (Priority: P1)

A traveler clicks on a tour card and lands on a detailed tour page showing full information: image gallery, description, highlights, inclusions/exclusions, meeting point, pricing, availability calendar, reviews summary, and a prominent "Book Now" CTA.

**Why this priority**: The tour detail page is where booking intent converts. It must present all information needed for a booking decision.

**Independent Test**: Can be fully tested by navigating to `/{locale}/tours/{slug}` and verifying all content sections render correctly.

**Acceptance Scenarios**:

1. **Given** a traveler navigates to `/en/tours/colosseum-guided-tour`, **When** the page loads, **Then** they see an image gallery (swipeable), tour title, rating badge, price per person, location, duration, and full description.
2. **Given** the tour has translations in ES and IT, **When** the traveler switches locale, **Then** title, description, highlights, inclusions, and exclusions render in the selected language.
3. **Given** the tour has availability, **When** the traveler views the availability section, **Then** they see selectable dates with pricing and remaining capacity.
4. **Given** the page is viewed on mobile, **When** the layout renders, **Then** the image gallery becomes a full-width swipeable carousel, and the booking CTA becomes a sticky footer button.
5. **Given** a search engine crawls the tour page, **When** it reads the HTML, **Then** it finds schema.org TouristAttraction structured data, canonical URL, and hreflang tags.
6. **Given** the tour has reviews, **When** the reviews section renders, **Then** average star rating, total review count, and individual reviews (most recent first) are visible.

---

### User Story 4 - Browse by Category and Destination (Priority: P2)

A traveler navigates to a category page (e.g., "History & Culture") or a destination page (e.g., "Rome") and sees curated tours within that context, with a hero banner and descriptive content.

**Why this priority**: Category and destination pages drive organic traffic (SEO) and provide structured browsing for travelers who aren't searching specific terms.

**Independent Test**: Can be tested by navigating to `/{locale}/tours/category/{slug}` or `/{locale}/tours/destination/{slug}` and verifying content renders.

**Acceptance Scenarios**:

1. **Given** a traveler navigates to `/en/tours/category/history-culture`, **When** the page loads, **Then** they see a category hero, description, and a grid of tours in that category.
2. **Given** a traveler navigates to `/en/tours/destination/rome`, **When** the page loads, **Then** they see a destination hero image, description, and all tours available in Rome.
3. **Given** the page is server-rendered, **When** crawled by a search engine, **Then** meta tags, structured data, and breadcrumbs are present.

---

### User Story 5 - Complete Booking Checkout (Priority: P1)

A traveler selects a date, number of participants, and enters their details through a multi-step checkout flow. The flow works for both guest and authenticated users, with real-time availability validation.

**Why this priority**: Checkout is the revenue-generating action. A broken or confusing checkout flow directly impacts business revenue.

**Independent Test**: Can be tested end-to-end by selecting a tour, choosing a date/participants, entering guest details, and reaching the payment step.

**Acceptance Scenarios**:

1. **Given** a traveler clicks "Book Now" on a tour detail page, **When** the checkout opens, **Then** they see Step 1: Select Date, Time Slot, and Number of Participants.
2. **Given** a traveler selects 2 participants on a date with 3 remaining spots, **When** they proceed, **Then** Step 2 shows: enter Name, Email, Phone, and Special Requests.
3. **Given** a traveler selects 5 participants on a date with only 3 remaining, **When** they try to proceed, **Then** an error message shows "Only 3 spots available for this date."
4. **Given** a guest traveler fills in contact details, **When** they proceed to payment, **Then** Step 3 shows the Stripe payment form with order summary.
5. **Given** an authenticated traveler starts checkout, **When** Step 2 loads, **Then** their name, email, and phone are pre-filled from their account.
6. **Given** the checkout is on a mobile device, **When** each step renders, **Then** the multi-step form adapts to full-width mobile layout with a sticky progress indicator.
7. **Given** the traveler is on Step 3, **When** they navigate back to Step 1, **Then** previously entered data is preserved.

---

### User Story 6 - Pay with Stripe (Priority: P1)

A traveler enters payment details using Stripe Elements (card input) and completes the booking. On successful payment, they see a confirmation screen with booking reference and an option to create an account.

**Why this priority**: Payment is the final step in the conversion funnel. Stripe Elements provides PCI-compliant card capture without storing card data server-side.

**Independent Test**: Can be tested using Stripe test mode cards (4242 4242 4242 4242) to verify successful and failed payment flows.

**Acceptance Scenarios**:

1. **Given** a traveler is on the payment step, **When** they see the Stripe Elements card input, **Then** the input accepts card number, expiry, and CVC with real-time validation.
2. **Given** a traveler enters a valid test card and clicks "Confirm & Pay", **When** payment succeeds, **Then** they see a confirmation page with: booking reference number, tour name, date, participants, amount paid, and meeting point.
3. **Given** payment fails (e.g., insufficient funds), **When** the error occurs, **Then** the traveler sees a clear error message and can retry with a different card.
4. **Given** the traveler is a guest, **When** they see the confirmation page, **Then** they are offered an option to "Create an Account" using their checkout email.
5. **Given** the traveler double-clicks "Confirm & Pay", **When** the request fires, **Then** the idempotency key prevents duplicate charges and the same booking is returned.
6. **Given** the checkout session expires (availability taken), **When** payment is attempted, **Then** the traveler sees a message that the tour is no longer available for the selected date and is redirected back.

---

### User Story 7 - Authenticate (Login/Register) (Priority: P2)

A traveler can sign in or register via dedicated auth pages that match the Stitch design. The auth state persists across the site and unlocks account-specific features.

**Why this priority**: Authentication unlocks the traveler dashboard, pre-filled checkout, and wishlist features. It's secondary to discovery and booking which work for guests.

**Independent Test**: Can be tested by navigating to `/{locale}/auth/login` and `/{locale}/auth/register`, completing forms, and verifying session state.

**Acceptance Scenarios**:

1. **Given** a traveler navigates to `/en/auth/login`, **When** the page loads, **Then** they see a login form matching the Stitch "Login to BooklyTravel" design with email and password fields.
2. **Given** a traveler enters valid credentials, **When** they submit, **Then** they are redirected to the homepage with their name shown in the header.
3. **Given** a traveler enters invalid credentials, **When** they submit, **Then** they see an inline error "Invalid email or password" without page reload.
4. **Given** a traveler navigates to `/en/auth/register`, **When** they fill in name, email, and password, **Then** their account is created and they receive a verification email.
5. **Given** the login page loads on mobile, **When** rendered, **Then** it matches the Stitch "Login Mobile" design.

---

### Edge Cases

- What happens when a tour is unpublished while a traveler is viewing the detail page? → Show a "Tour No Longer Available" message with redirect to search.
- What happens when the API is unreachable during checkout? → Show a user-friendly error with retry option; do NOT proceed to payment.
- What happens when session token expires mid-checkout? → Allow guest checkout to continue; for authenticated users, prompt re-login in a modal without losing form data.
- How does the system handle slow image loading? → Use blurred placeholder images (blurhash or LQIP) for progressive loading.
- What happens when a locale route doesn't exist? → Redirect to the default locale (`/en/`) with a 302.
- What happens on a 404 (tour slug not found)? → Render a branded 404 page with search suggestions.
- What happens when Sentry is unreachable? → Error tracking fails silently; the user experience MUST NOT be degraded by Sentry unavailability.
- What happens when a user exceeds rate limits during booking? → Return a clear message "Too many attempts. Please wait [X] seconds and try again." with the retry-after header value displayed.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The public website MUST render all pages using Next.js 16 SSR/SSG for SEO-crawlable HTML (per Constitution §4).
- **FR-002**: All public pages MUST support three locales (EN, ES, IT) via localized routes (`/en/`, `/es/`, `/it/`).
- **FR-003**: The homepage MUST display a hero search bar, category sections, featured destinations, and tour cards.
- **FR-004**: The tour listing page MUST support text search, filters (location, category, price range, duration, date), and sorting (relevance, price, rating, newest).
- **FR-005**: Tour cards MUST display: cover image, title, price per person, average rating, location, and duration.
- **FR-006**: The tour detail page MUST display: image gallery, full description (translated), highlights, inclusions/exclusions, meeting point, pricing, availability calendar, reviews, and a "Book Now" CTA.
- **FR-007**: The checkout flow MUST have 3 steps: (1) Select date/participants, (2) Enter details, (3) Payment.
- **FR-008**: The checkout MUST work for both guest and authenticated users.
- **FR-009**: The payment step MUST use Stripe Elements for PCI-compliant card capture.
- **FR-010**: Successful payment MUST redirect to a confirmation page with booking reference, details summary, and account creation offer for guests.
- **FR-011**: All pages MUST include meta title, meta description, Open Graph tags, canonical tags, and hreflang alternates.
- **FR-012**: Category pages MUST exist at `/{locale}/tours/category/{slug}` with SSR rendering.
- **FR-013**: Destination pages MUST exist at `/{locale}/tours/destination/{slug}` with SSR rendering.
- **FR-014**: All pages MUST be fully responsive with mobile-first design, tested at 390px and 780px breakpoints.
- **FR-015**: The global header MUST include navigation links, locale switcher, and auth state (login/register or user menu).
- **FR-016**: The global footer MUST include site links, language selector, and brand information.
- **FR-017**: Image loading MUST use progressive techniques (placeholder → full resolution) to maintain perceived performance.
- **FR-018**: The design system MUST implement the Stitch tokens: Navy (#0A2540) primary, Gold (#FFB800) accent, Off-white (#F7F9FB) background, Inter font family, 8px spacing grid, 12px border radius.
- **FR-023**: All public pages MUST meet WCAG 2.1 AA accessibility standards (color contrast, keyboard navigation, screen reader support, semantic HTML).
- **FR-019**: Auth pages (login, register, forgot password) MUST be accessible at `/{locale}/auth/*` with form validation.
- **FR-021**: A cookie consent banner MUST be displayed on first visit, allowing accept/reject of non-essential cookies, with preference stored and respected across sessions.
- **FR-022**: Privacy policy pages MUST exist at `/{locale}/privacy` and terms & conditions pages at `/{locale}/terms`, translated in all three locales (EN, ES, IT).
- **FR-020**: Checkout MUST generate and send a unique idempotency key per attempt to prevent duplicate bookings.
- **FR-024**: Sentry MUST be integrated for frontend (Next.js) and backend (Laravel) error monitoring, capturing payment failures, checkout errors, Stripe webhook issues, and API exceptions with contextual metadata while excluding PII.
- **FR-025**: Laravel throttle middleware MUST protect auth endpoints (login/register: 5 req/min/IP, password reset: 3 req/min/IP) and booking/payment endpoints (3 req/min/IP), returning clear error responses when limits are exceeded.

### Key Entities

- **Tour Card**: Compact visual representation of a tour (cover image, title, price, rating, location, duration). Used in listing grids, homepage features, and search results.
- **Tour Detail**: Full tour information including gallery, translated content, availability, pricing, and reviews. Drives the booking decision.
- **Checkout Session**: Client-side state (Zustand + sessionStorage) tracking the selected tour, date, participants, guest details, current step, and payment intent through the multi-step flow. Survives back-navigation and page refresh within the same browser session. Does not store sensitive data (payment details, tokens, secrets).
- **Booking Confirmation**: Read-only summary of a completed booking with reference number, tour details, and payment receipt.
- **Design System Tokens**: Centralized visual language (colors, typography, spacing, radius) extracted from the Stitch design system and applied to all UI components.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A first-time visitor can discover a tour and reach the checkout payment step within 4 clicks from the homepage.
- **SC-002**: All public pages achieve a Lighthouse Performance score ≥ 90 (per Constitution §4).
- **SC-003**: The complete checkout flow (select → details → pay → confirm) can be completed in under 3 minutes.
- **SC-004**: All pages render correctly on mobile (390px) and desktop (1280px+) without horizontal scrolling or layout breakage.
- **SC-005**: Page transitions between routes complete within 500ms perceived load time using client-side navigation.
- **SC-006**: Search results appear within 1 second of filter/search submission.
- **SC-007**: All 3 locales (EN, ES, IT) render correct translations with no untranslated strings visible to users.
- **SC-008**: 100% of pages pass automated SEO audit (meta tags, structured data, canonical, hreflang present).
- **SC-009**: Successful Stripe test payment completes without errors and produces a booking confirmation with reference number.
- **SC-010**: Image gallery on tour detail page supports swipe navigation on touch devices.
- **SC-011**: Cookie consent banner renders on first visit and non-essential scripts are blocked until consent is given.
- **SC-012**: All pages achieve WCAG 2.1 AA compliance verified by automated audit (axe-core or Lighthouse accessibility score ≥ 90).

## Clarifications

### Session 2026-05-19

- Q: What GDPR/cookie consent approach is required for the EU-facing locales? → A: Full GDPR baseline: cookie consent banner with accept/reject preferences, privacy policy pages (EN/ES/IT), and terms & conditions pages linked in footer. Consent stored and respected before loading non-essential tracking scripts. MVP-level implementation.
- Q: What WCAG accessibility conformance level is required? → A: WCAG 2.1 AA.
- Q: What observability/error tracking should be integrated? → A: Sentry for frontend (Next.js) and backend (Laravel) error monitoring, performance tracking, and production alerts. Payment failures, checkout errors, Stripe webhook issues, and API exceptions captured with contextual metadata (booking ID, payment ID, environment). PII excluded. MVP scope only.
- Q: Where/how should checkout session state be stored? → A: Client-side Zustand store with sessionStorage persistence. Survives back-navigation and page refresh within same browser session. Sensitive data (payment details, secrets, tokens) excluded. No server-side draft bookings for guest checkout.
- Q: What rate limiting is required on form submissions and API endpoints? → A: Server-side Laravel throttle middleware: login/register 5 req/min per IP, password reset 3 req/min, booking/payment creation 3 req/min. Returns clear API errors. Works alongside idempotency protection. No client-side throttle for MVP.

## Assumptions

- The Laravel backend API is fully operational for specs 006, 007, and 008 (search, booking, payments endpoints).
- The Stitch project (`projects/12748463214263676465`) screens serve as the definitive UI reference — component styles, layout structure, and responsive behavior are derived from these designs.
- Stitch HTML code exports will be used as scaffolding reference but all components will be rebuilt as proper React/Next.js components with TypeScript, not copy-pasted HTML.
- The existing Next.js frontend at `frontend/` has the project skeleton (Next.js 16, TypeScript, Tailwind, i18n) already configured from Phase A.
- Redis caching is available for API response caching on the frontend (SWR/TanStack Query handles client-side caching).
- Cloudflare R2 serves tour images via public CDN URLs; no frontend-side image upload is needed in this spec (that's a partner feature).
- Guest checkout does not require any authentication token; authenticated checkout uses Sanctum bearer tokens.
- Stripe publishable key is available via environment variable (`NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`).
