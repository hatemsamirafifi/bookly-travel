# Research: Spec 020 Tour Detail Experience

**Date**: 2026-10-14 (Africa/Cairo)  
**Scope**: Source inspection and design decisions; runtime/visual/performance acceptance has not been executed. Repository versions are pinned by manifests; online docs may describe newer minor versions and do not authorize an upgrade.

## 1. Existing behavior and server/client boundaries

**Evidence**: `frontend/src/components/tour/TourDetail.tsx` has no client directive and composes current sections; `frontend/src/app/[locale]/(public)/tours/[slug]/page.tsx` reads public content and emits metadata/schema; `frontend/src/lib/api/tours.ts` explicitly uses `cache: 'no-store'`.

**Decision**: Preserve server composition and fresh selected content. Extract static sections; isolate interaction in gallery, overview/disclosures, navigation and booking controller. Pass only narrow public props to each island.

**Rationale**: Indexable facts and fallback-language attributes remain server-visible while hydration is limited to necessary interaction. Next recommends keeping client boundaries small and supports server-rendered children inside client wrappers. [Next server/client guidance](https://nextjs.org/docs/app/getting-started/server-and-client-components).

**Alternatives considered**: A whole-page client component increases bundle/hydration and risks losing SEO/fresh reads; a global store needlessly broadens transient state. Neither is needed.

## 2. Booking selection and money

**Evidence**: AvailabilityCalendar owns a separate selected date, caps display at 30 and derives a UTC today. BookingCTA owns count but always sends next_available_date. MobileBookingAction sends next_available_date plus minimum count, independent of both. `frontend/src/lib/utils.ts` and backend `app/Models/Tour.php` use integer cents with divisor 100; Tour::currency currently returns EUR. BookingForm retains server confirmation and drift handling.

**Decision**: One TourBookingController persists date/count and derives validity/estimate/query for both card/bar. Use a controlled native labelled date select exposing every offered future-UTC date, preserving date strings with no arbitrary first-30 cap. Backend config/app.php uses UTC and CreateBookingAction rejects dates ≤ UTC today; the current offered list can contain today, so filter/disable it according to that verified rule. Initialize from a server-supplied UTC-day snapshot to avoid hydration disagreement, and recheck current UTC day on selection/continuation and midnight rollover. Reuse formatCurrency after safe-integer amount/count/product guards; preserve current cents/currency convention, with no currency-exponent or support expansion.

**Rationale**: One owner prevents mobile reset and disconnected selection. Native selection supplies standard keyboard semantics. Integer multiplication preserves the financial contract; Intl is formatting only. Currency display precision is documented by [MDN resolvedOptions](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/NumberFormat/resolvedOptions); it must not redefine backend storage units.

**Alternatives considered**: Duplicated state, localStorage or a new global store introduces synchronization issues. Reusing the current uncontrolled calendar unchanged preserves the date bug. New date-specific inventory or tier pricing is unsupported.

## 3. Gallery, focus and performance

**Evidence**: Current ImageGallery provides a single active image, thumbnail strip and custom focus handling; no requested desktop mosaic/mobile swipe. Shared `ui/Dialog.tsx` already portals, contains focus, makes background inert, locks body and restores focus. `CarouselRail.tsx` supports scroll-snap but is not a complete image viewer.

**Decision**: TourGallery owns selected image/viewer state, uses a sparse desktop mosaic and mobile scroll-snap rail with visible controls; share Dialog for the enlarged viewer, add arrow-key navigation scoped to the viewer, and isolate actual image-language attributes from localized controls. Failed images get same-sized truthful placeholders; remaining images retain stable positions and the original total count. Render only needed viewer content.

**Rationale**: Reusing modal infrastructure avoids inconsistent layers and focus handling. The [WAI modal-dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/) requires contained focus and appropriate restoration. Fixed aspect ratios/accurate sizes, lazy supporting images and a single hero loading strategy control bandwidth/layout shifts; see [Next Image documentation](https://nextjs.org/docs/app/api-reference/components/image).

**Alternatives considered**: A third-party gallery adds weight and unneeded dependency; duplicating Dialog's page lock is fragile. Preloading every photo harms the first view. Copying third-party tour photos is excluded.

## 4. Navigation and responsive layout

**Evidence**: TourSectionNav currently uses native anchor links with top-0 stickiness but has no reading-position tracking. Header currently uses `relative`, not fixed positioning. Shared Tabs hides inactive tabpanels, so it does not express an always-readable long document.

**Decision**: Retain nav/anchor semantics, add active-section observation and visible entry reveal. Use measured navigation height and any actually sticky header obstruction for scroll offsets; do not invent a constant header height. Rebuild observer after relevant resizing, and never move focus during passive scroll. On explicit activation reveal and focus the target heading; reduced motion uses immediate scrolling.

**Alternatives considered**: Tabpanel semantics would incorrectly hide sections; assuming the current header is fixed creates incorrect offsets. Scroll handlers running on every frame are unnecessary when observation suffices.

## 5. Content, trust and localization

**Evidence**: Existing TourDetail exposes independent content/itinerary fallback checks, live-guide display names, ordered content and optional operator/related sections. Spec 019 contract governs intentional empty itinerary and actual language. Reviews/wishlist have independent existing flows.

**Decision**: Centralize pure nonempty/section visibility/language derivation, preserving selected values. Reuse reviews, operator, related cards and wishlist; restyle through semantic tokens and shared primitives. Overview collapses only beyond 600 Unicode code points, with a plain-text preview and complete text on expansion; short text stays fully shown. Practical sections retain important information; no map/geocoder is added.

**Alternatives considered**: Generic English fallback for the entire page mislabels independent itinerary fallback. Generating missing ratings, operator badges or cancellation copy violates supplied-data rules.

## 6. Route states and metadata

**Evidence**: apiClient maps backend 410 to GoneError; current detail page catches only NotFoundError. generateMetadata catches any failure and returns a hardcoded English not-found title. The route directory currently contains page.tsx and tests, without dedicated loading/error files.

**Decision**: Add local loading/recovery UI; handle known GoneError as a localized withdrawn-tour state with no booking/private content and noindex metadata. Preserve backend 410 and existing 404 handling. Localize fallback metadata and distinguish transient/denied cases without claiming a new Next HTTP 410 response. Test actual backend status and rendered frontend state separately.

**Alternatives considered**: Silently collapsing archive/network failures into not-found loses meaning. Redesigning program-wide error pages belongs to Spec 022; this plan only supplies detail-route behavior required by FR-001/031.

## 7. Authentication continuation

**Evidence**: Booking is under the public layout while create-booking is Sanctum protected. BookingForm has no dedicated 401 recovery; generic AuthGuard encodes pathname only and loses booking query. `frontend/src/lib/auth/return-url.ts` validates same-locale full paths including query/hash and existing login/register consume it.

**Decision**: Add a bounded continuation adapter for tour booking, using the complete validated booking URL as encoded returnUrl and existing safeAuthReturnUrl. After auth restoration resolves, logged-out continuation uses existing login; login/register return to fresh checkout with preserved date/count/tour. Handle expired-session 401 in BookingForm with the same safe full return path, no automatic resubmission or new booking/payment operation. Keep existing stable idempotency/double-submit and price-drift handling.

**Rationale**: FR-020/022 require a usable preserved selection journey; current auth gap must be addressed rather than asserted already working. This is bounded handoff recovery, not the later checkout redesign.

**Alternatives considered**: Wrapping checkout in unchanged AuthGuard drops query; generic error loses the chosen tour and does not satisfy the journey. A new auth flow or payment redesign is unnecessary.

## 8. Acceptance tooling and dependency baseline

**Evidence**: `frontend/playwright.config.ts` expects a running proxy, separates authenticated projects by filename, and excludes a11y from public projects. Existing tour-detail tests contain a production SSR/API-fixture harness. `.github/workflows/lighthouse.yml` explicitly uses lighthouserc.js; that file already requires pessimistic ≥0.9, but only its EN tour URL is collected. The legacy lighthouserc.cjs is weaker and the Windows local audit wrapper currently runs an EN-only set.

**Decision**: Reuse these harnesses, include real public API/checkout verification and twelve locale/width cases, and extend the strict JS Lighthouse configuration with ES/IT detail routes. No runtimes or seeded records are modified during planning. Origin/main ancestry and six retained shell commits are recorded as separate merged/unmerged states.

**Alternatives considered**: Browser-only route mocking misses SSR; weak/default Lighthouse selection does not meet SC-008; mock-only booking tests do not prove server revalidation. Dropping current shell commits would lose the agreed visual baseline.

All design unknowns needed for task generation are resolved by the documented choices. Runtime environment readiness, measured performance and release readiness remain future verification work, not unresolved product decisions.
