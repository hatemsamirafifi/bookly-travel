# Tasks: Public Frontend — Search, Booking & Payments

**Input**: Design documents from `/specs/010-public-frontend/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: E2E and a11y test tasks are included per spec requirements (WCAG 2.1 AA compliance, Lighthouse ≥ 90, Playwright e2e for booking/payment flows).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Install missing dependencies and configure tooling for the frontend project

- [X] T001 Install missing runtime dependencies (zustand, @tanstack/react-query, @sentry/nextjs, react-cookie-consent, plaiceholder) via `cd frontend && npm install`
- [X] T002 [P] Install missing dev dependencies (@axe-core/playwright, @playwright/test) via `cd frontend && npm install -D`
- [X] T003 [P] Configure TanStack Query provider in `frontend/src/lib/query-provider.tsx` and wrap in root layout at `frontend/src/app/layout.tsx`
- [X] T004 [P] Configure Sentry client and server configs in `frontend/sentry.client.config.ts` and `frontend/sentry.server.config.ts` with PII exclusion via `beforeSend` hook per FR-024
- [X] T005 [P] Add Playwright test script (`test:e2e`) and axe-core a11y script (`test:a11y`) to `frontend/package.json` scripts per quickstart.md
- [X] T006 [P] Configure `next.config.ts` with image `remotePatterns` for Cloudflare R2 CDN domain per research.md #7

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**CRITICAL**: No user story work can begin until this phase is complete

- [X] T007 [P] Create design system tokens file `frontend/src/lib/design-tokens.ts` with Stitch colors (Navy #0A2540, Gold #FFB800, Off-white #F7F9FB), Inter typography, 8px grid, 12px border radius per data-model.md §5
- [X] T008 Apply design tokens to Tailwind `theme.extend` in `frontend/tailwind.config.ts` (if not already using CSS-based config in Tailwind v4)
- [X] T009 [P] Verify and enhance API client in `frontend/src/lib/api/client.ts` — ensure Sanctum CSRF cookie handling, typed error responses, locale header forwarding, and auth token injection per contracts/ notes
- [X] T010 [P] Verify and complete i18n routing config in `frontend/src/i18n/routing.ts` with EN/ES/IT locales and pathname-based routing per research.md #1
- [X] T011 [P] Verify and complete locale middleware in `frontend/src/middleware.ts` for locale detection, prefix-based redirect, and default locale fallback
- [X] T012 [P] Populate base translation keys in `frontend/messages/en.json` for shared UI (header nav, footer links, cookie consent, error messages, 404, form labels)
- [X] T013 [P] Copy base translation keys to `frontend/messages/es.json` and `frontend/messages/it.json` with Spanish and Italian translations
- [X] T014 [P] Create shared SEO head component in `frontend/src/components/shared/SEOHead.tsx` with meta title/description, OG tags, canonical, hreflang alternates, and JSON-LD injection per FR-011
- [X] T015 [P] Verify Header component in `frontend/src/components/layout/Header.tsx` includes nav links, LocaleSwitcher, and auth state (login/register links or user menu) per FR-015
- [X] T016 [P] Verify Footer component in `frontend/src/components/layout/Footer.tsx` includes site links, language selector, privacy/terms links, and brand info per FR-016
- [X] T017 [P] Create cookie consent banner component in `frontend/src/components/shared/CookieConsent.tsx` using react-cookie-consent with Stitch styling, accept/reject, and non-essential script gating per FR-021
- [X] T018 [P] Create error boundary component in `frontend/src/components/shared/ErrorBoundary.tsx` wrapping app with Sentry error capture and fallback UI per FR-024
- [X] T019 Create API response TypeScript types in `frontend/src/lib/api/types.ts` matching all contracts/ endpoints (TourCard, TourDetail, BookingResponse, PaymentIntent, AuthUser, AvailabilityCheck, CategoryPage, DestinationPage, PaginatedResponse)

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Discover Tours on Homepage (Priority: P1)

**Goal**: Traveler visits the homepage and sees hero search bar, category grid, featured destinations, and tour cards with full Stitch branding, SSR-rendered for SEO, responsive across mobile/desktop

**Independent Test**: Navigate to `/{locale}/` and verify: hero renders with search fields, 4+ categories visible, 6+ featured tour cards with cover/title/price/rating/location/duration, responsive layout at 390px and 1280px

### Tests for User Story 1

- [X] T020 [P] [US1] Playwright e2e test for homepage rendering in `frontend/tests/e2e/homepage.spec.ts` — verifies hero, categories, featured tours, locale switching
- [X] T021 [P] [US1] axe-core a11y scan for homepage in `frontend/tests/e2e/a11y/homepage-a11y.spec.ts`

### Implementation for User Story 1

- [X] T022 [P] [US1] Create homepage API service in `frontend/src/lib/api/homepage.ts` consuming `GET /api/tours` with featured/sorted params for hero content
- [X] T023 [P] [US1] Verify and enhance HeroSection component in `frontend/src/components/home/HeroSection.tsx` — search bar with Location/Date/Guests fields, Stitch Navy gradient background, CTA button per FR-003
- [X] T024 [P] [US1] Verify and enhance CategoryGrid component in `frontend/src/components/home/CategoryGrid.tsx` — fetch categories from API, display 4+ category cards with images, links to `/tours/category/{slug}`
- [X] T025 [P] [US1] Verify and enhance FeaturedTours component in `frontend/src/components/home/FeaturedTours.tsx` — 6+ tour cards with cover/title/price/rating/location/duration, links to `/tours/{slug}`
- [X] T026 [P] [US1] Verify and enhance DestinationShowcase component in `frontend/src/components/home/DestinationShowcase.tsx` — popular destinations grid with images and links
- [X] T027 [US1] Wire homepage data fetching in `frontend/src/app/[locale]/page.tsx` — SSR fetch for categories, featured tours, destinations; pass to client components
- [X] T028 [US1] Add homepage-specific translations to `frontend/messages/en.json`, `es.json`, `it.json` (hero title/subtitle/CTA, section headings, "Starting from" label)
- [X] T029 [US1] Add SEO metadata and JSON-LD structured data (WebSite/Organization) to homepage at `frontend/src/app/[locale]/page.tsx` using SEOHead component

**Checkpoint**: Homepage renders independently with search bar, categories, featured tours, destinations; SEO-optimized with SSR; responsive across breakpoints

---

## Phase 4: User Story 2 - Search and Filter Tours (Priority: P1)

**Goal**: Traveler searches and filters tours on the listing page with text search, category/price/duration/date filters, sorting options, and paginated results

**Independent Test**: Navigate to `/{locale}/tours`, enter search terms, apply filters, verify results, test empty state, verify URL query parameters update

### Tests for User Story 2

- [X] T030 [P] [US2] Playwright e2e test for search flow in `frontend/tests/e2e/search.spec.ts` — search, filter, sort, paginate, empty state
- [X] T031 [P] [US2] axe-core a11y scan for search page in `frontend/tests/e2e/a11y/search-a11y.spec.ts`

### Implementation for User Story 2

- [X] T032 [P] [US2] Verify and enhance tours search API service in `frontend/src/lib/api/search.ts` — typed params for all filters (q, category, destination, min_price, max_price, min_duration, max_duration, date, sort, page, per_page, locale) per contracts/tours-api.md
- [X] T033 [P] [US2] Create useTours hook with TanStack Query in `frontend/src/lib/hooks/useTours.ts` — `useQuery` with query keys scoped by locale + filter params, staleTime 30s
- [X] T034 [P] [US2] Verify and enhance SearchBar component in `frontend/src/components/search/SearchBar.tsx` — text input with submit, accessible labels, ARIA attributes
- [X] T035 [P] [US2] Verify and enhance FilterPanel component in `frontend/src/components/search/FilterPanel.tsx` — category dropdown, price range inputs, duration range, date picker; collapse to slide-out panel on mobile per FR-004
- [X] T036 [P] [US2] Verify and enhance SortDropdown component in `frontend/src/components/search/SortDropdown.tsx` — relevance, price_asc, price_desc, rating, newest options per FR-004
- [X] T037 [US2] Verify and enhance TourCard component in `frontend/src/components/search/TourCard.tsx` — cover image with blur placeholder, title, price, rating, location, duration per FR-005; link to `/tours/{slug}`
- [X] T038 [US2] Verify and enhance SearchResults component in `frontend/src/components/search/SearchResults.tsx` — grid layout, loading skeletons, empty state with suggestions per spec edge cases
- [X] T039 [US2] Verify and enhance Pagination component in `frontend/src/components/search/Pagination.tsx` — page numbers or "Load More" with infinite scroll per spec US2 scenario 6
- [X] T040 [US2] Wire search page in `frontend/src/app/[locale]/search/page.tsx` — server component fetching initial results, client components for interactive filters/sort; URL query param sync per FR-004
- [X] T041 [US2] Add search/listing translations to `frontend/messages/en.json`, `es.json`, `it.json` (filter labels, sort options, empty state, "Load More", "No results found")

**Checkpoint**: Tour search fully functional — filter, sort, paginate; empty state handled; URL params reflect active filters

---

## Phase 5: User Story 3 - View Tour Details (Priority: P1)

**Goal**: Traveler views a tour detail page with image gallery, full translated description, highlights, inclusions/exclusions, meeting point, availability calendar, reviews, and a "Book Now" CTA

**Independent Test**: Navigate to `/{locale}/tours/{slug}` and verify all content sections render, gallery swipes, availability shows selectable dates with capacity, reviews display

### Tests for User Story 3

- [X] T042 [P] [US3] Playwright e2e test for tour detail in `frontend/tests/e2e/tour-detail.spec.ts` — gallery, content sections, availability, reviews, CTA, 404 handling
- [X] T043 [P] [US3] axe-core a11y scan for tour detail page in `frontend/tests/e2e/a11y/tour-detail-a11y.spec.ts`

### Implementation for User Story 3

- [X] T044 [P] [US3] Verify and enhance tour detail API service in `frontend/src/lib/api/tours.ts` — `getTourBySlug(slug, locale)` fetching full detail per contracts/tours-api.md GET /tours/{slug}
- [X] T045 [P] [US3] Create useTour hook with TanStack Query in `frontend/src/lib/hooks/useTour.ts` — `useQuery` with 5min staleTime, error handling for 404
- [X] T046 [P] [US3] Verify and enhance ImageGallery component in `frontend/src/components/tour/ImageGallery.tsx` — swipeable carousel (touch + mouse), blur placeholder progressive loading via plaiceholder, full-width on mobile per FR-017
- [X] T047 [P] [US3] Verify and enhance TourDetail component in `frontend/src/components/tour/TourDetail.tsx` — title, rating badge, price, location, duration, translated description, highlights, inclusions/exclusions, meeting point per FR-006
- [X] T048 [P] [US3] Verify and enhance AvailabilityCalendar component in `frontend/src/components/tour/AvailabilityCalendar.tsx` — selectable date/time cards with pricing and remaining capacity display per spec US3 scenario 3
- [X] T049 [P] [US3] Verify reviews display using ReviewList in `frontend/src/components/reviews/ReviewList.tsx` and AggregateRating in `frontend/src/components/reviews/AggregateRating.tsx` on tour detail page per FR-006
- [X] T050 [US3] Verify and enhance BookingCTA component in `frontend/src/components/tour/BookingCTA.tsx` — prominent "Book Now" button, sticky footer on mobile, navigates to checkout with selected date/participants
- [X] T051 [US3] Wire tour detail page in `frontend/src/app/[locale]/tours/[slug]/page.tsx` — SSR fetch tour data, render all content sections, 404 handling for invalid/unpublished slugs per spec edge cases
- [X] T052 [US3] Add SEO metadata and JSON-LD TouristAttraction structured data to tour detail page per FR-011
- [X] T053 [US3] Add tour detail translations to `frontend/messages/en.json`, `es.json`, `it.json` (section headings: "Highlights", "Inclusions", "Exclusions", "Meeting Point", "Availability", "Reviews", "From", "Book Now")

**Checkpoint**: Tour detail page fully functional — all sections render, gallery swipes, availability selectable, reviews visible, "Book Now" CTA navigates to checkout

---

## Phase 6: User Story 4 - Browse by Category and Destination (Priority: P2)

**Goal**: Traveler browses curated tours on category pages (e.g., "History & Culture") and destination pages (e.g., "Rome") with hero banners and descriptive content, SSR-rendered for SEO

**Independent Test**: Navigate to `/{locale}/tours/category/{slug}` and `/{locale}/tours/destination/{slug}`, verify hero banner, description text, filtered tour grid, SEO tags

### Tests for User Story 4

- [X] T054 [P] [US4] Playwright e2e test for category/destination pages in `frontend/tests/e2e/category-destination.spec.ts`
- [X] T055 [P] [US4] axe-core a11y scan for category/destination pages in `frontend/tests/e2e/a11y/category-destination-a11y.spec.ts`

### Implementation for User Story 4

- [X] T056 [P] [US4] Verify and enhance category API service in `frontend/src/lib/api/categories.ts` — `getCategoryBySlug(slug, locale, params)` per contracts/tours-api.md GET /tours/category/{slug}
- [X] T057 [P] [US4] Verify and enhance destination API service in `frontend/src/lib/api/destinations.ts` — `getDestinationBySlug(slug, locale, params)` per contracts/tours-api.md GET /tours/destination/{slug}
- [X] T058 [US4] Wire category page in `frontend/src/app/[locale]/categories/[slug]/page.tsx` — SSR fetch category data, hero banner (category name + description), tour grid reusing TourCard/SearchResults components per FR-012
- [X] T059 [US4] Wire destination page in `frontend/src/app/[locale]/destinations/[slug]/page.tsx` — SSR fetch destination data, hero image + description, tour grid per FR-013
- [X] T060 [US4] Add SEO metadata, breadcrumbs, and structured data to category and destination pages per FR-011

**Checkpoint**: Category and destination pages fully functional with hero banners, tour grids, and SEO metadata

---

## Phase 7: User Story 5 - Complete Booking Checkout (Priority: P1)

**Goal**: Traveler completes a multi-step checkout: Step 1 (select date/participants) → Step 2 (enter details) → Step 3 (payment). Works for both guest and authenticated users with real-time availability validation

**Independent Test**: Click "Book Now" on a tour, select date + participants, enter guest details, proceed to payment step. Verify back-navigation preserves data. Verify capacity enforcement.

### Tests for User Story 5

- [X] T061 [P] [US5] Playwright e2e test for checkout flow in `frontend/tests/e2e/checkout.spec.ts` — date selection, participant limits, guest form, back-navigation, auth pre-fill
- [X] T062 [P] [US5] axe-core a11y scan for checkout pages in `frontend/tests/e2e/a11y/checkout-a11y.spec.ts`

### Implementation for User Story 5

- [X] T063 [P] [US5] Create Zustand checkout store in `frontend/src/lib/stores/checkout-store.ts` with persist middleware (sessionStorage) — state shape per data-model.md §3: tourId, selectedDate, timeSlot, participants, pricePerPerson, totalPrice, guestName/Email/Phone, specialRequests, currentStep, idempotencyKey; exclude sensitive fields from persistence; clear on completion
- [X] T064 [P] [US5] Verify and enhance booking API service in `frontend/src/lib/api/bookings.ts` — `createBooking()`, `checkAvailability()` per contracts/booking-api.md; handle 400/409/422/429 errors
- [X] T065 [P] [US5] Create useCheckout hook in `frontend/src/lib/hooks/useCheckout.ts` — wraps checkout store actions + API calls, step validation, availability re-check on step transitions
- [X] T066 [US5] Verify and enhance Step 1 — DateConfirmation component in `frontend/src/components/booking/DateConfirmation.tsx` and ParticipantSelector in `frontend/src/components/booking/ParticipantSelector.tsx` with capacity validation per US5 scenarios 1-3
- [X] T067 [US5] Verify and enhance Step 2 — BookingForm component in `frontend/src/components/booking/BookingForm.tsx` with Name/Email/Phone/Special Requests fields; react-hook-form + zod validation; pre-fill for authenticated users per US5 scenario 5
- [X] T068 [US5] Verify and enhance checkout page in `frontend/src/app/[locale]/booking/page.tsx` — multi-step layout with StepIndicator, step navigation (back/forward), sticky progress on mobile per FR-007, FR-008
- [X] T069 [US5] Create PriceBreakdown component in `frontend/src/components/booking/PriceBreakdown.tsx` — display unit price × participants = total, formatted per locale
- [X] T070 [US5] Implement idempotency key generation (`crypto.randomUUID()`) at checkout start and pass with booking API calls per FR-020
- [X] T071 [US5] Handle checkout error states — availability expired redirect, API unreachable retry, rate limit message display per spec edge cases
- [X] T072 [US5] Add checkout translations to `frontend/messages/en.json`, `es.json`, `it.json` (step labels, form fields, validation errors, capacity messages, "Proceed to Payment")

**Checkpoint**: Multi-step checkout functional — date/participants selection, guest details form, back-navigation with data preservation, capacity enforcement, guest + auth flows

---

## Phase 8: User Story 6 - Pay with Stripe (Priority: P1)

**Goal**: Traveler enters payment details via Stripe Elements, completes booking, and sees confirmation page with booking reference. Handles payment failures, double-click prevention, and account creation offer for guests.

**Independent Test**: Complete checkout with Stripe test card `4242 4242 4242 4242` — verify success confirmation with reference; test decline card `4000 0000 0000 0002` for error handling; test double-click prevention.

### Tests for User Story 6

- [X] T073 [P] [US6] Playwright e2e test for payment flow in `frontend/tests/e2e/payment.spec.ts` — success path, decline path, double-click idempotency, session expiry
- [X] T074 [P] [US6] axe-core a11y scan for payment and confirmation pages in `frontend/tests/e2e/a11y/payment-a11y.spec.ts`

### Implementation for User Story 6

- [X] T075 [P] [US6] Verify Stripe client initialization in `frontend/src/lib/stripe/stripe-client.ts` — loadStripe with publishable key, Appearance API for Stitch theme matching (Navy primary, Inter font, 12px radius)
- [X] T076 [US6] Verify and enhance StripePaymentForm in `frontend/src/components/booking/StripePaymentForm.tsx` — Stripe Elements CardElement with Stitch styling, real-time validation, "Confirm & Pay" button, loading/disabled states; wrap with Elements provider per FR-009
- [X] T077 [US6] Wire Step 3 payment flow — on "Confirm & Pay", call `stripe.confirmCardPayment(clientSecret)`, then `POST /bookings/{id}/confirm` with idempotency key; handle success/decline/error per contracts/booking-api.md
- [X] T078 [US6] Verify and enhance BookingConfirmation component in `frontend/src/components/booking/BookingConfirmation.tsx` — display booking reference, tour name, date, time, participants, amount, meeting point per FR-010
- [X] T079 [US6] Wire confirmation page in `frontend/src/app/[locale]/booking/confirmation/page.tsx` — render booking data, "Create Account" offer for guests (pre-filled email) per FR-010
- [X] T080 [US6] Implement double-click prevention — disable button after first click, idempotency key deduplication, handle 409 conflict response per FR-020
- [X] T081 [US6] Handle payment error states — card decline with retry, session expiry with redirect, network failure with recovery per spec US6 scenarios 3, 5, 6
- [X] T082 [US6] Add payment/confirmation translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Confirm & Pay", "Processing payment", "Booking Confirmed", "Create Account", error messages)

**Checkpoint**: Full payment flow functional — Stripe Elements capture, success confirmation, decline handling, double-click protection, guest account creation offer

---

## Phase 9: User Story 7 - Authenticate (Login/Register) (Priority: P2)

**Goal**: Traveler can sign in or register via dedicated auth pages matching Stitch design. Auth state persists across site and pre-fills checkout forms.

**Independent Test**: Navigate to `/{locale}/auth/login` and `/{locale}/auth/register`, complete forms, verify redirect and session state in header.

### Tests for User Story 7

- [X] T083 [P] [US7] Playwright e2e test for auth flow in `frontend/tests/e2e/auth.spec.ts` — login success/failure, register success/validation, logout, session persistence
- [X] T084 [P] [US7] axe-core a11y scan for auth pages in `frontend/tests/e2e/a11y/auth-a11y.spec.ts`

### Implementation for User Story 7

- [X] T085 [P] [US7] Verify and enhance auth API service in `frontend/src/lib/api/auth.ts` — CSRF cookie fetch before login/register, login, register, logout, getUser per contracts/auth-api.md
- [X] T086 [P] [US7] Create/verify auth store in `frontend/src/lib/stores/auth-store.ts` — non-persisted Zustand store (or React context) per data-model.md §6: isAuthenticated, user, isLoading
- [X] T087 [US7] Verify and enhance LoginForm in `frontend/src/components/auth/LoginForm.tsx` — email/password with react-hook-form + zod validation, inline error display, Stitch styling per spec US7 scenario 1-3
- [X] T088 [US7] Verify and enhance RegisterForm in `frontend/src/components/auth/RegisterForm.tsx` — name/email/password/confirm with validation, success message about verification email per spec US7 scenario 4
- [X] T089 [US7] Verify login page in `frontend/src/app/[locale]/auth/login/page.tsx` renders LoginForm with Stitch "Login to BooklyTravel" design, responsive mobile layout
- [X] T090 [US7] Verify register page in `frontend/src/app/[locale]/auth/register/page.tsx` renders RegisterForm with translated UI
- [X] T091 [US7] Wire auth state to Header — show user name when authenticated, login/register links when guest, logout action per FR-015
- [X] T092 [US7] Create AuthGuard wrapper in `frontend/src/components/auth/AuthGuard.tsx` — redirect unauthenticated users, preserve intended destination
- [X] T093 [US7] Add auth translations to `frontend/messages/en.json`, `es.json`, `it.json` (login/register titles, form labels, validation errors, "Invalid credentials", "Check your email")

**Checkpoint**: Auth fully functional — login, register, session persistence, header reflects auth state, checkout pre-fills for authenticated users

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories and ensure production readiness

- [X] T094 [P] Create privacy policy page in `frontend/src/app/[locale]/privacy/page.tsx` with translated content (EN/ES/IT) per FR-022
- [X] T095 [P] Create terms & conditions page in `frontend/src/app/[locale]/terms/page.tsx` with translated content (EN/ES/IT) per FR-022
- [X] T096 [P] Create branded 404 page in `frontend/src/app/[locale]/not-found.tsx` with search suggestions per spec edge cases
- [X] T097 [P] Add blur placeholder generation (plaiceholder) integration for tour card and gallery images in `frontend/src/lib/images.ts` per FR-017
- [X] T098 [P] Review all components for WCAG 2.1 AA compliance — color contrast (Navy #0A2540 on Off-white #F7F9FB), keyboard navigation, semantic HTML, ARIA labels, focus indicators per FR-023
- [X] T099 Configure Lighthouse CI for performance monitoring with ≥ 90 threshold in `frontend/lighthouserc.js` per SC-002
- [X] T100 [P] Add loading skeletons for all pages (homepage sections, tour cards grid, tour detail content, checkout steps)
- [X] T101 [P] Comprehensive Playwright e2e smoke test in `frontend/tests/e2e/smoke.spec.ts` — full user journey: homepage → search → tour detail → checkout → payment → confirmation
- [X] T102 Run through quickstart.md validation checklist — verify `npm run dev`, `npm run build`, `npm test`, `npm run test:e2e`, `npm run test:a11y`, `npm run lint` all pass
- [X] T103 [P] Verify all 3 locales render with no untranslated strings visible; run `grep -r "TODO\|FIXME\|MISSING" messages/` to catch gaps per SC-007
- [X] T104 Performance audit — verify page transitions <500ms, search results <1s, checkout completion <3min per SC-003, SC-005, SC-006

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **User Stories (Phase 3–9)**: All depend on Foundational phase completion
  - US1 (Homepage), US2 (Search), US3 (Tour Detail) can proceed in parallel
  - US5 (Checkout) depends on US3 (needs tour detail page to navigate from)
  - US6 (Payment) depends on US5 (extends checkout step 3)
  - US4 (Category/Destination) depends on US2 (reuses search/listing components)
  - US7 (Auth) is independent — can run in parallel with any story
- **Polish (Phase 10)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational — No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational — No dependencies on other stories
- **User Story 3 (P1)**: Can start after Foundational — No dependencies on other stories
- **User Story 4 (P2)**: Depends on US2 completion (reuses TourCard, SearchResults, filter components)
- **User Story 5 (P1)**: Depends on US3 completion (navigates from tour detail "Book Now" CTA)
- **User Story 6 (P1)**: Depends on US5 completion (extends checkout with payment step)
- **User Story 7 (P2)**: Can start after Foundational — No dependencies on other stories

### Within Each User Story

- Tests written and verified as FAILING before implementation (where applicable)
- API services before hooks
- Hooks before components
- Components before page wiring
- Page wiring before translations
- Story complete before moving to next priority

### Parallel Opportunities

```
Phase 1: T001 ──┬── T002 (parallel)
                ├── T003 (parallel)
                ├── T004 (parallel)
                ├── T005 (parallel)
                └── T006 (parallel)

Phase 2: T007 ──┬── T009 (parallel)
                ├── T010 (parallel)
                ├── T011 (parallel)
                ├── T012 → T013 (sequential: copy after populate)
                ├── T014 (parallel)
                ├── T015 (parallel)
                ├── T016 (parallel)
                ├── T017 (parallel)
                ├── T018 (parallel)
                └── T019 (parallel)

After Phase 2: US1, US2, US3, US7 can start in parallel
After US2: US4 can start
After US3: US5 can start
After US5: US6 can start
```

---

## Parallel Example: User Story 2

```bash
# Phase 1: Launch all parallel service/hook/component tasks together:
Task: "Verify and enhance tours search API service in frontend/src/lib/api/search.ts"
Task: "Create useTours hook with TanStack Query in frontend/src/lib/hooks/useTours.ts"
Task: "Verify and enhance SearchBar component in frontend/src/components/search/SearchBar.tsx"
Task: "Verify and enhance FilterPanel component in frontend/src/components/search/FilterPanel.tsx"
Task: "Verify and enhance SortDropdown component in frontend/src/components/search/SortDropdown.tsx"
Task: "Verify and enhance TourCard component in frontend/src/components/search/TourCard.tsx"

# Phase 2: Wire page (depends on components):
Task: "Wire search page in frontend/src/app/[locale]/search/page.tsx"

# Phase 3: Translations (depends on all components knowing their keys):
Task: "Add search/listing translations to messages/"
```

---

## Implementation Strategy

### MVP First (User Stories 1, 2, 3)

1. Complete Phase 1: Setup — install deps, configure tooling
2. Complete Phase 2: Foundational — design system, i18n, API client, layout, SEO (CRITICAL)
3. Complete Phase 3: US1 — Homepage
4. **STOP and VALIDATE**: Test homepage independently at `/en/`
5. Complete Phase 4: US2 — Search & Filter
6. **STOP and VALIDATE**: Test search independently
7. Complete Phase 5: US3 — Tour Details
8. **STOP and VALIDATE**: Test tour detail independently
9. **MVP READY**: Travelers can discover, search, and view tours

### Full Product (Add Booking + Payment)

10. Complete Phase 7: US5 — Booking Checkout
11. Complete Phase 8: US6 — Stripe Payment
12. **STOP and VALIDATE**: Full booking flow end-to-end
13. Complete Phase 6: US4 — Category & Destination (SEO traffic)
14. Complete Phase 9: US7 — Authentication
15. Complete Phase 10: Polish — privacy/terms, a11y, perf, 404

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 → Deploy (Landing page live)
3. Add US2 + US3 → Deploy (Discovery experience live)
4. Add US5 + US6 → Deploy (Booking + payments live — revenue!)
5. Add US4 → Deploy (SEO landing pages live)
6. Add US7 → Deploy (Auth + account features live)
7. Polish → Production-ready

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- The existing codebase at `frontend/` has substantial scaffolding — tasks marked "Verify and enhance" indicate existing components that need review and completion
- US5 (Checkout) and US6 (Payment) are separate phases because US6 extends US5 with Stripe-specific payment step, but they must be implemented sequentially
- Category/destination pages route to `/[locale]/categories/[slug]` and `/[locale]/destinations/[slug]` in existing code; plan.md shows `/[locale]/tours/category/[slug]` — verify which convention the routing config uses and align
- Sentry `beforeSend` hook must strip PII (names, emails, phones) per FR-024
- Idempotency keys must be generated once at checkout start and reused for booking creation + payment confirmation
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
