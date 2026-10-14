# Implementation Plan: Tour Detail Experience

**Branch**: `codex/020-tour-detail-experience` | **Date**: 2026-10-14 (Africa/Cairo) | **Spec**: [spec.md](spec.md)  
**Spec ID / Master-plan phase**: Spec 020 / Phase 3  
**Governing Constitution**: [v2.1.0](../../.specify/memory/constitution.md)  
**Baseline / Dependencies**: `4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27`, retaining six header/footer/static-asset commits from `codex/header-navigation-polish` above fetched `origin/main` at `9e241b9377d42f904081cb7b2bca1871e3b4388d` (Spec 019 PR #29 merge). Main ancestry verified on 2026-10-14; retained shell commits are an explicit unmerged dependency, not evidence of release readiness.  
**Input**: `specs/020-tour-detail-experience/spec.md`  
**Status**: Planning/design complete; [execution tasks](tasks.md) generated on 2026-10-14; implementation pending. No runtime, CI, merge or release gates executed by documentation generation.

## Summary

Rebuild the public tour detail hierarchy, desktop mosaic/mobile gallery, reading navigation and booking preparation while preserving Spec 019 data selection and existing commerce. Keep the route and static content server-rendered; use small client boundaries for gallery, disclosures, section observation and a single booking-selection controller. Reuse the shared design system, existing reviews/wishlist/cards, fresh public detail reads and structured content. Add no database schema, endpoint, payment behavior or new dependency.

Decisions/evidence: [research](research.md). Transient state: [data model](data-model.md). Consumer/checkout compatibility: [contract](contracts/tour-detail-consumer.md). Observable layouts and interaction rules: [UI contract](contracts/tour-detail-ui.md). Validation: [quickstart](quickstart.md).

## Technical Context

**Language/Version**: TypeScript 5; Next.js 16.2.3, React 19.2.4. Existing backend is Laravel 11 on PHP `^8.2`; CI tests PHP 8.3/8.4/8.5. Versions verified in frontend/package.json, backend/composer.json and existing workflows; do not infer PHP 8.3 minimum from stale AGENTS summary.  
**Primary Dependencies**: next-intl 4, Tailwind CSS 4, next/image, shared UI primitives, native React state/context, browser IntersectionObserver/ResizeObserver. No added library/global store.  
**Storage**: No persistent model changes; existing PostgreSQL/Redis remain backend-owned. Booking/gallery/navigation state is page-local; no localStorage.  
**Testing**: Jest 30/Testing Library, Playwright 1.60, axe, Lighthouse CI; existing PostgreSQL-backed backend suites cover actual contracts and critical booking/payment/auth behavior.  
**Target Platform**: Existing Next app in supported desktop/mobile browsers through the current nginx/API boundary; production build required for performance/SSR acceptance.  
**Project Type**: API-first tours marketplace web application.  
**Performance Goals**: Lighthouse Performance ≥90 for detail pages in EN/ES/IT, pessimistic minimum across three recorded runs per locale; fixed gallery geometry prevents gallery-induced layout shift. Keep existing EN home/search/blog regression audits.  
**Constraints**: Constitution v2.1.0; truthful data; EN/ES/IT and widths 390/768/1024/1440; WCAG 2.1 AA; integer cents; existing checkout remains authoritative; preserve URLs, status handling and independent fallback languages.  
**Scale/Scope**: One detail-route family and its reusable tour components. Existing partner/admin/content generation and later program phases remain separate; no speculative user-volume target.

## Constitution Check

Gates reviewed before research and again after design. PASS below is a design assessment, not a runtime or release result.

| Principle / gate | Pre-design | Post-design decision / evidence |
|---|---|---|
| I. Tours marketplace scope | PASS | Published tour reading/booking preparation only; no additional travel vertical. |
| II. Booking/publication/review integrity | PASS | Supplied facts and unbookable guards, existing review authorization, no inventory hold; consumer contract. |
| III. Auditable commerce | PASS | Integer cents estimate; checkout/server revalidation, Stripe and idempotency untouched; integrated regression coverage required. |
| IV. API-first authorization | PASS | Existing public detail read; protected wishlist/booking retain server controls; no client database access or secret serialization. |
| V. Design/originality | PASS | Shared primitives/tokens and approved Bookly hierarchy; no third-party media/text or copied trademarks; UI contract. |
| VI. Accessibility/localization/responsiveness | PASS | Explicit dialog/nav/selection/disclosure behavior, twelve viewport/locale combinations and state matrix. |
| VII. Evidence/regression safety | PASS | FR/SC verification map, actual API plus SSR/browser fixtures, manual keyboard and production performance gates. |
| Public contracts/SEO/source locales | PASS | No endpoint/schema evolution; selected source language/empty itinerary preserved, no-store reads and existing schema regression retained. |
| Identity/baseline/preservation | PASS | 020 maps to Phase 3; active pointer resolves here; 018/019 preserved; six retained shell commits documented as dependency. |
| Delivery/release governance | PASS | This step creates design artifacts only; complete execution ledger required before implementation; recheck constitution and dependency merge readiness before release. |

**Post-design result**: PASS, no constitution exception. A real contract discrepancy discovered during implementation must update the analysis/contract and its backend/frontend tests before work relies on it; no invented backend behavior.

## Project Structure

### Documentation (this feature)

```text
specs/020-tour-detail-experience/
  spec.md
  plan.md
  research.md
  data-model.md
  quickstart.md
  checklists/requirements.md
  contracts/tour-detail-consumer.md
  contracts/tour-detail-ui.md
```

Execution ledger [tasks.md](tasks.md) was subsequently generated by `$speckit-tasks` on 2026-10-14. Evidence directories are created during actual verification, with source commit, fixture, command/result, locale/width and retained report paths.

### Source Code (repository root)

Existing files to reuse/refactor:

```text
frontend/src/app/[locale]/(public)/tours/[slug]/page.tsx
frontend/src/components/tour/{TourDetail,ImageGallery,TourSectionNav,BookingCTA,
  AvailabilityCalendar,MobileBookingAction,OperatorSummary,RelatedTours}.tsx
frontend/src/components/reviews/ReviewList.tsx
frontend/src/components/wishlist/WishlistButton.tsx
frontend/src/components/booking/BookingForm.tsx
frontend/src/components/ui/
frontend/src/components/seo/StructuredData.tsx
frontend/src/lib/api/{tours,types,client}.ts
frontend/src/lib/utils.ts
frontend/src/app/globals.css
frontend/src/lib/design-tokens.ts
frontend/messages/{en,es,it}.json
frontend/tests/e2e/{tour-detail,booking,checkout,payment,wishlist}.spec.ts
frontend/tests/e2e/a11y/tour-detail-a11y.spec.ts
frontend/playwright.config.ts
frontend/lighthouserc.js
```

Planned new files (not yet implemented): tour section files `TourHeader`, `TourGallery`, `TourOverview`, `TourFacts`, `TourHighlights`, `TourItinerary`, `TourInclusions`, `TourMeetingPoint`, `TourReviews`, `BookingCard`, `MobileBookingBar`, `TourBookingController`; pure helper `frontend/src/lib/tours/detail-presentation.ts` and its tests; route-local `loading.tsx`/`error.tsx` where needed. Existing operator/related/nav remain their canonical names. Compatibility wrappers for ImageGallery/BookingCTA/MobileBookingAction may bridge old tests/consumers until migrated; do not keep duplicate live implementations.

**Structure Decision**: Retain the existing route/data loader and server-compatible TourDetail compositor. Static sections receive narrow selected-content props; interactive islands receive only required serializable public facts. TourBookingController owns both card/bar state in one persistent mounted boundary, independently of CSS visibility. Do not clientify the entire page or export the whole TourDetail response to a global store.

## Delivery Design and Sequence

These are work packages for later task generation, not completed implementation phases. Their numbers do not change master-plan Phase 3.

| Package | Scope / approach | Requirement coverage | Evidence required |
|---|---|---|---|
| A. Baseline and presentation model | Verify ancestry/API/route conventions; pure section visibility/language/booking eligibility helpers; contract fixtures from real endpoint. | FR-001,004,006–007,016–022,027–028,034 | Unit boundaries, real API assertions; retain prior behavior tests. |
| B. Ordered server sections | Header/gallery/nav order; overview/facts/itinerary/practical sections; use shared Container/Breadcrumbs/Accordion/state primitives, preserve important information. | FR-002–008,023–025,032–033 | Component content/order/empty/lang/SEO cases and real SSR output. |
| C. Gallery | Shared Dialog; desktop sparse mosaic, mobile scroll-snap and controls; broken-photo states, descriptions, measured responsive image sizes. | FR-009–012,029–030 | Unit keyboard/order/state checks, browser touch/focus/inertness/layout evidence. |
| D. Reading navigation | Anchor semantics, visible-section observation, measured sticky offsets, horizontal entry reveal; no tabpanel hiding. | FR-013–014,029–030 | Sparse/scroll/activation/no-focus-theft component and browser cases. |
| E. Shared booking | Controlled future-UTC offered-date selector and count; safe integer cents estimate; common query builder; shared desktop/mobile state; unavailable guards; bounded auth continuation/401 recovery preserving full query. | FR-015–022,031 | Non-default date/count, >30 offered dates, UTC midnight/today rejection, bounds/invalid currency/overflow, resize, login/register return and checkout rejection cases. |
| F. Localization/states/regression | Localized fallback/state/metadata, wishlist/reviews, route-local recovery, all twelve visual combinations; performance/CI coverage. | FR-001,026–034 | Focus/axe/manual, integrated API → page → checkout, preserved status/privacy/SEO and ≥90 audits. |

All FR-001–034 are covered above; SC-001–009 map to quickstart acceptance rows. Write meaningful failing behavioral tests before changes to critical selection/checkout integration; shared primitives remain governed by existing tests plus focused affected cases.

## Verification and Performance Design

- Run lint/typecheck, focused and full frontend Jest, production build, affected public/mobile/authenticated browser suites and a11y projects. Run actual PostgreSQL backend contract/booking/payment/auth regression suites using disposable test services; do not run them against development data.
- Retain existing SSR harness for controlled empty/fallback/error/photo cases. Browser route mocks cannot intercept server-side fetching. Controlled fixtures supplement integrated real-public-response checks, not replace them.
- Extend `frontend/lighthouserc.js` collection with ES/IT detail URLs, retaining EN home/search/tour/blog and pessimistic ≥0.9 performance assertion. Three runs on six URLs produce eighteen expected reports. The legacy `.cjs` warning-only 0.7 config is not acceptance; the local audit script currently covers EN only and cannot prove this spec's locale gate unmodified.
- Performance runs use production code, documented cold-navigation/resource conditions and serial audits without competing builds/suites. Keep all results/traces; do not select the fastest run, hide failures or present unreachable pages as passing. Explicitly choose `--config=lighthouserc.js`.
- Add any test filename requiring signed-in storage to Playwright's authenticated project selectors; public filenames currently run logged out. Reuse the existing authenticated `booking.spec.ts` for final handoff/auth-return assertions or explicitly update selection rules.

## Rollout and Compatibility

This phase needs no migration or backend deployment change. Confirm Spec 019 is deployed in the target environment before enabling the new consumer, and resolve the retained shell branch's merge dependency. Deploy the compatible frontend with unchanged routes/query shape. Roll back the frontend image/source to the verified compatible predecessor if necessary; do not remove stored content, media or translations. Observe existing booking/payment/API/client-error monitoring; release is not complete until all required gates and manual evidence pass.

## Complexity Tracking

No constitution violations or architecture exceptions. A local booking controller is necessary to fix three independent states; a new global store, backend endpoint, gallery package or full-page client boundary is rejected as unnecessary scope/weight.
