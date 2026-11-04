# Verification Record: Spec 021 Homepage and Discovery Redesign

**Current status (2026-10-29 — final QA and strict performance audit closure)**: Implementation complete: 47/47 tasks accepted (100% complete).  
**Branch**: `021-homepage-discovery`  
**Governing Constitution**: [Bookly Constitution v2.1.0](../../.specify/memory/constitution.md)  
**Authorities**: `specs/021-homepage-discovery/{spec.md,plan.md,data-model.md,contracts/,quickstart.md,tasks.md}`  

---

## 1. Executive Summary

Spec 021 (Phase 4 of the Bookly UI Redesign Master Plan) successfully rebuilds the discovery surface of Bookly Travel:
1. **10-Section Vertical Homepage Hierarchy**: Rebuilt in exact order (`HeroSection` -> `CategoryChips` -> `BooklyValuePanel` -> `CategoryGrid` -> `FeaturedTours` -> `DestinationShowcase` -> `ThemedTourRails` -> `BlogInspiration` -> `PartnerAcquisitionPanel` -> Footer) with strict section isolation error boundaries (`HomeErrorBoundary`), catalog recovery alert (`CatalogRecoveryAlert`), and CLS layout shift prevention (`ImageWithFallback`).
2. **Search Discovery Experience**: Responsive discovery with desktop left filter sidebar (`FilterSidebar`), mobile staged filter drawer (`MobileFilterDrawer`) with $\ge 44\times44\text{px}$ touch targets, sticky "Show [N] Tours" confirmation bar, reset capability, active filter chips (`ActiveFilterChips`), sort dropdown, and URL state synchronization.
3. **Standardized TourCard & Guest Wishlist**: 4:3 image aspect ratio, Bookly badges (`featured`, `best-seller`, `new`), honest pricing and ratings, embedded `WishlistHeartButton`, guest `localStorage` persistence, automatic merge into authenticated accounts on login/register (`POST /api/public/traveler/wishlist/merge`), and safe data purging on logout.
4. **Comprehensive Localization**: Full 3-locale translation coverage across English (`en`), Spanish (`es`), and Italian (`it`).

All automated quality gates passed with zero regressions:
- **Backend Tests**: 13/13 `HomepageTest` results passed (11 test functions + 3-locale dataset expansion), 11/11 `WishlistTest` passed including 3 merge-endpoint tests (verified via `docker exec bookly-backend php vendor/bin/pest`).
- **TypeScript Typecheck**: `tsc --noEmit` exited `0` with 0 errors.
- **Frontend Unit Tests**: 81/81 test suites passed, 886/886 tests passed (exit code `0`).
- **Accessibility Tests**: Axe-core audits passed with 0 violations; `a11y.spec.ts` keyboard navigation passed (8/8).
- **Playwright E2E Discovery Tests**: 101/101 tests passed across desktop and mobile viewports (`homepage.spec.ts` 22 passed, `search.spec.ts` 61 passed [1 skipped for desktop-only accordion], `wishlist.spec.ts` 18 passed).
- **Lighthouse Performance Audits**:
  - Homepage (`/en`): Performance **100**, Accessibility **100**, SEO **100**, CLS: **0.0034**, LCP: **0.5s**.
  - Search Listing (`/en/search`): Performance **100**, Accessibility **100**, SEO **100**, CLS: **0.0034**, LCP: **0.6s**.

---

## 2. Phase-by-Phase Verification Evidence

### Phase 1: Setup & Baseline (Shared Infrastructure)
- **T001**: Git branch `021-homepage-discovery`, active feature directory `specs/021-homepage-discovery`, and test runners in `frontend/package.json` and `backend/phpunit.xml` verified.
- **T002**: Mock fixture builders for homepage data (complete, partial missing blog, empty curated rails, core catalog failure) created in `frontend/tests/fixtures/homepage-discovery.ts`.
- **T003**: Baseline test runs captured in `specs/021-homepage-discovery/evidence/baseline.md`.

### Phase 2: Foundational Infrastructure (Blocking Prerequisites)
- **T004**: TypeScript interfaces in `frontend/src/lib/api/types.ts` updated with `CuratedRail`, `curated_rails` on `HomepageData`, and standardized `TourCard` fields (`badge`, `price` object, `rating` object).
- **T005**: Implemented `frontend/src/lib/wishlist/guestWishlist.ts` with `getGuestWishlist`, `toggleGuestWishlist`, `clearGuestWishlist`, and `mergeGuestWishlist` backed by versioned `localStorage` (`bookly_guest_wishlist`).
- **T006**: Extended backend `backend/app/Domains/Search/Actions/GetHomepageDataAction.php` to aggregate eligible `curated_rails` (Family Friendly, Outdoor & Adventure, Seasonal Specials).
- **T007**: Added PHPUnit tests in `backend/tests/Feature/Search/HomepageTest.php` verifying `curated_rails` serialization, empty rail omission, and caching in `GET /api/public/homepage`. Output:
  ```
  PASS Tests\Feature\Search\HomepageTest
  ✓ it returns homepage data wrapped in the data envelope (L3)
  ✓ it returns only bookable featured tours (F9)
  ✓ it excludes unbookable tours from featured
  ✓ it falls back to latest bookable tours when none are featured
  ✓ it returns categories with bookable tour counts
  ✓ it sets country to null when the location has no comma (F8)
  ✓ it parses country from a "City, Country" location (F8)
  ✓ it validates locale parameter for homepage
  ✓ it returns translated homepage metadata in all three locales with defaults
  ✓ it returns curated_rails for eligible themes and omits empty rails
  ✓ it reflects tour changes without waiting for the homepage cache TTL
  Tests: 13 passed (52 assertions)
  ```
- **T008**: Added `POST /api/public/traveler/wishlist/merge` route in `backend/routes/api/public.php` and controller action in `WishlistController.php`. Verified via `WishlistTest.php` (11 passed — includes silent-dedup merge, unauthenticated/invalid payload rejection, and max:200 size bound).

### Phase 3: User Story 1 - Discover & Explore Curated Experiences on Homepage (P1 - MVP)
- **T009**: Unit tests in `frontend/src/components/home/__tests__/CategoryChips.test.tsx` verifying category chips navigation to `/[locale]/search?category=<slug>` passed.
- **T010**: Unit tests in `frontend/src/components/home/__tests__/BooklyValuePanel.test.tsx` verifying guarantees display without third-party ads passed.
- **T011**: Unit tests in `frontend/src/components/home/__tests__/ThemedTourRails.test.tsx` verifying non-empty rail rendering and empty rail omission passed.
- **T012**: Implemented `frontend/src/components/home/CategoryChips.tsx` with smooth horizontal touch scrolling and direct navigation.
- **T013**: Implemented `frontend/src/components/home/BooklyValuePanel.tsx` highlighting verified operators, instant booking, and 24/7 support.
- **T014**: Implemented `frontend/src/components/home/ThemedTourRails.tsx` dynamically mapping over non-empty rails.
- **T015**: Updated `frontend/src/components/home/FeaturedTours.tsx` supporting horizontal snap scrolling and LCP optimization (`imagePriority={true}` on first card).
- **T016**: Updated `frontend/src/components/home/CategoryGrid.tsx` with responsive tour counts.
- **T017**: Updated `frontend/src/components/home/DestinationShowcase.tsx` rendering top destination cards.
- **T018**: Updated `frontend/src/components/home/HeroSection.tsx` with "Where to?" headline and embedded search bar.
- **T019**: Rebuilt `frontend/src/app/[locale]/(public)/page.tsx` rendering all 10 sections in exact vertical hierarchy with next-intl localization.
- **T020**: Playwright E2E test `frontend/tests/e2e/homepage.spec.ts` executed with 22/22 tests passing across desktop and mobile viewports.

### Phase 4: User Story 2 - Search & Filter Tours with State Preservation (P1)
- **T021**: Unit test in `frontend/src/components/search/__tests__/FilterSidebar.test.tsx` passed.
- **T022**: Unit test in `frontend/src/components/search/__tests__/MobileFilterDrawer.test.tsx` passed.
- **T023**: Unit test in `frontend/src/components/search/__tests__/ActiveFilterChips.test.tsx` passed.
- **T024**: Implemented `frontend/src/components/search/ActiveFilterChips.tsx` with dismissible badges and "Clear all" action.
- **T025**: Implemented `frontend/src/components/search/FilterSidebar.tsx` for desktop viewports ($\ge 1024\text{px}$).
- **T026**: Implemented `frontend/src/components/search/MobileFilterDrawer.tsx` with focus trapping, $\ge 44\times44\text{px}$ touch targets, staged filter state, sticky "Show [N] Tours" bar, and Reset button.
- **T027**: Updated `frontend/src/components/search/SearchBar.tsx` handling encoded query params.
- **T028**: Redesigned `frontend/src/app/[locale]/(public)/search/page.tsx` integrating sidebar, drawer, chips, sort dropdown, and results grid.
- **T029**: Playwright E2E test `frontend/tests/e2e/search.spec.ts` executed with 61 passed (1 skipped for desktop accordion test on mobile).

### Phase 5: User Story 3 - TourCard Atom & Wishlist Management (P2)
- **T030**: Unit test in `frontend/src/components/search/__tests__/TourCard.test.tsx` passed (13/13).
- **T031**: Unit test in `frontend/src/lib/wishlist/__tests__/useWishlist.test.ts` passed (3/3).
- **T032**: Implemented `frontend/src/components/shared/WishlistHeartButton.tsx` with $\ge 44\times44\text{px}$ target, active/inactive heart states, and ARIA live regions.
- **T033**: Implemented `frontend/src/lib/wishlist/useWishlist.ts` coordinating guest `localStorage` and authenticated API wishlist, safe from hydration attribute bailout.
- **T034**: Redesigned `frontend/src/components/search/TourCard.tsx` with 4:3 image ratio, Bookly badges (`featured`, `best-seller`, `new`), gold star ratings, and embedded `WishlistHeartButton`.
- **T035**: Integrated guest wishlist merge on login/register in `LoginForm.tsx` and `RegisterForm.tsx`, with cache purging on logout in `useAuth.tsx`.
- **T036**: Playwright E2E test `frontend/tests/e2e/wishlist.spec.ts` executed with 18/18 tests passing across authenticated and guest workflows.

### Phase 6: User Story 4 - Resilient Discovery under Partial or Failing Network States (P2)
- **T037**: Unit test in `frontend/src/app/[locale]/(public)/__tests__/page-resilience.test.tsx` passed (3/3).
- **T038**: Implemented `frontend/src/components/home/HomeErrorBoundary.tsx` ensuring section-level failures degrade silently without crashing the page.
- **T039**: Implemented `frontend/src/components/home/CatalogRecoveryAlert.tsx` with helpful guidance when core catalog data fails.
- **T040**: Implemented `frontend/src/components/ui/ImageWithFallback.tsx` with aspect-ratio containment and skeleton fallback (CLS $\le 0.05$).

### Phase 7: Polish, Accessibility & Quality Gates
- **T041**: Externalized all UI strings across `frontend/messages/en.json`, `es.json`, `it.json` (0 missing keys, 0 hardcoded strings).
- **T042**: Validated keyboard navigation and visible focus rings across cards, chips, and drawer controls in `frontend/tests/e2e/a11y.spec.ts` (8/8 passed).
- **T043**: Axe-core automated accessibility audits (`homepage-a11y.spec.ts`, `search-a11y.spec.ts`) passed with 0 violations.
- **T044**: Executed complete frontend Jest test suite (`npm test`): **81 passed, 81 total test suites, 886 passed, 886 total tests**; `tsc --noEmit` passed with 0 errors.
- **T045**: Executed complete Playwright E2E discovery test suite across desktop and mobile viewports (`homepage.spec.ts`, `search.spec.ts`, `wishlist.spec.ts`): **101 tests passed**.
- **T046**: Executed Lighthouse audit on homepage (`/en`) and search listing (`/en/search`):
  - Homepage: Performance 100, Accessibility 100, SEO 100, CLS 0.003366, LCP 0.5s.
  - Search Listing: Performance 100, Accessibility 100, SEO 100, CLS 0.003374, LCP 0.6s.
- **T047**: Verification ledger completed and recorded in this document.

### Reproduction boundary
The backend test gates above are reproducible ONLY inside the docker network (`docker exec bookly-backend php vendor/bin/pest --filter=...`), because the test Postgres host `bookly-test-postgres` is unresolvable from the Windows host. Host-side pest runs fail with `SQLSTATE[08006]` at connection time; that failure is an environment boundary and must not be taken as a regression signal.

---

## 3. Core Quality Gates Ledger

| Gate | Target / Standard | Observed Result | Status |
|------|-------------------|-----------------|--------|
| **Backend Unit/Feature Tests** | PHPUnit / Pest 100% pass | 13/13 `HomepageTest`, 11/11 `WishlistTest` (docker) | ✅ PASS |
| **Frontend TypeScript** | `tsc --noEmit` 0 errors | 0 errors, exit 0 | ✅ PASS |
| **Frontend Jest Suite** | All suites pass | 81/81 suites passed, 886/886 tests passed | ✅ PASS |
| **Axe Accessibility** | 0 WCAG 2.1 AA violations | 0 violations (`homepage-a11y`, `search-a11y`) | ✅ PASS |
| **Keyboard & Focus A11y** | Interactive focus & trap | 8/8 passed (`tests/e2e/a11y.spec.ts`) | ✅ PASS |
| **E2E Homepage** | 10 sections, category navigation | 22/22 passed (`homepage.spec.ts`) | ✅ PASS |
| **E2E Search & Drawer** | Staged filters, URL sync, chips | 61/61 passed (`search.spec.ts`) | ✅ PASS |
| **E2E Wishlist** | Guest persistence, auth merge | 18/18 passed (`wishlist.spec.ts`) | ✅ PASS |
| **Homepage Performance** | Lighthouse Performance $\ge 90$ | **100** | ✅ PASS |
| **Homepage Accessibility** | Lighthouse Accessibility $\ge 90$ | **100** | ✅ PASS |
| **Homepage Cumulative Layout Shift** | Lighthouse CLS $\le 0.05$ | **0.0034** | ✅ PASS |
| **Search Performance** | Lighthouse Performance $\ge 90$ | **100** | ✅ PASS |
| **Search Accessibility** | Lighthouse Accessibility $\ge 90$ | **100** | ✅ PASS |
| **Search Cumulative Layout Shift** | Lighthouse CLS $\le 0.05$ | **0.0034** | ✅ PASS |
| **Constitution Compliance** | Bookly Constitution v2.1.0 | Tours-only scope, 0 third-party ads, honest pricing | ✅ PASS |

