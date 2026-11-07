# Implementation Plan: Homepage and Discovery Redesign (Spec 021)

**Branch**: `021-homepage-discovery` | **Date**: 2026-10-29 (Africa/Cairo) | **Spec**: [spec.md](spec.md)  
**Spec ID / Master-plan phase**: Spec 021 / Phase 4 — Homepage and Discovery  
**Governing Constitution**: [Bookly Constitution v2.1.0](../../.specify/memory/constitution.md)  
**Baseline / Dependencies**: Branched from `claude/dreamy-lamport-8417b8` at `6fcdbb83578f1beede7bf2512d60b1ad76054f0f` (incorporating accepted Spec 020 Tour Detail Experience verification, Jest 857/857 pass, and strict Lighthouse reports). Builds upon Spec 018 (Design System and Shells), Spec 019 (Tour Content Data Model and API), and Spec 006 (Public Search and Discovery API). Phase 3 is accepted; Phase 4 is active; Specs 022–027 remain subsequent planned phases.  
**Input**: `specs/021-homepage-discovery/spec.md`  
**Status**: Planning and design complete; ready for task generation (`/speckit-tasks`).

---

## Summary

Rebuild the public homepage discovery hierarchy, standardize tour cards, and redesign search and filtering across desktop and mobile while preserving authentic marketplace contracts and Core Web Vitals. The implementation introduces the 10-section vertical hierarchy on the homepage with section-level error resilience, standardizes on a unified 4:3 `TourCard` with an unauthenticated `localStorage` guest wishlist lifecycle, extends the homepage backend endpoint to supply server-aggregated `curated_rails` without request waterfalls, and introduces an accessible mobile filter drawer with staged selections and live count confirmation.

Detailed research and decisions: [research.md](research.md). Complete TypeScript and entity schema: [data-model.md](data-model.md). API contract: [contracts/homepage-discovery-api.md](contracts/homepage-discovery-api.md). UI and component contracts: [contracts/discovery-ui-contracts.md](contracts/discovery-ui-contracts.md). Testing and verification procedures: [quickstart.md](quickstart.md).

---

## Technical Context

**Language/Version**: TypeScript 5+; Next.js 16.2.3, React 19.2.4; PHP 8.2+ / Laravel 11.  
**Primary Dependencies**: next-intl 4, Tailwind CSS 4, Lucide React, next/image, React Server Components.  
**Storage**:
- Backend: PostgreSQL (`tours`, `categories`, `destinations`, `tour_translations`, `wishlists`).
- Cache: Redis cache for `homepage.{locale}` (600s TTL).
- Client: Browser `localStorage` (`bookly_guest_wishlist`) for guest favorites with automated merge upon login/registration.  
**Testing**: Jest 30 & React Testing Library (frontend unit/isolation), Playwright 1.60 (e2e, desktop/mobile, Axe WCAG 2.1 AA), PHPUnit (backend Laravel actions and controllers).  
**Target Platform**: Desktop and mobile web browsers across viewports (390px, 768px, 1024px, 1280px, 1440px) via Next.js App Router and nginx reverse proxy.  
**Project Type**: Multi-partner tours marketplace public web application.  
**Performance Goals**:
- Initial homepage SSR response $< 2.0\text{s}$ under mobile 3G.
- First visible rail cover preloaded with `priority={true}` (`fetchpriority="high"`).
- Cumulative Layout Shift (CLS) $\le 0.05$.
- Lighthouse Performance $\ge 90$ and Accessibility $\ge 90$ in production build.  
**Constraints**: Bookly Constitution v2.1.0; authentic prices and ratings only (zero invented data); zero third-party ads; full localization in `en`, `es`, and `it`; mobile touch targets $\ge 44\times44\text{px}$; URL query persistence across Back/Forward navigation.  
**Scale/Scope**: Phase 4 Discovery scope (homepage root route, reusable tour card atom, search and filter drawer). Does not alter checkout, partner dashboard, or payment gateways.

---

## Constitution Check

*Gates reviewed before research and confirmed post-design.*

| Principle / Gate | Pre-Design | Post-Design Decision / Evidence |
|:-----------------|:----------:|:--------------------------------|
| **I. Marketplace Scope** | PASS | Discovery and search dedicated purely to authorized partner tours; no extraneous travel verticals. |
| **II. Marketplace Integrity** | PASS | Authentic pricing, reviews, and ratings directly from database records; zero fabricated items or fake badges. |
| **III. Auditable Commerce** | PASS | All prices displayed in integer cents with locale formatting; preserves existing checkout and booking parameters without modification. |
| **IV. API-First Authorization** | PASS | Public read endpoints; guest wishlist persisted strictly on client device until authenticated; zero secret leakage. |
| **V. Original Design & Tokens** | PASS | Built with Bookly design tokens, Tailwind CSS 4, and approved 10-section hierarchy; zero external ads. |
| **VI. Accessibility & Localization** | PASS | WCAG 2.1 AA compliant; keyboard focus indicators; $\ge 44\times44\text{px}$ touch targets; full next-intl localization (en/es/it). |
| **VII. Evidence & Regression Safety** | PASS | Automated test matrix covering complete, partial, empty, and error states across Jest, Playwright, and PHPUnit. |
| **Public Contracts & SEO** | PASS | Preserves `/api/public/search` contracts; extends `/api/public/homepage` backwards-compatibly; retains structured data. |
| **Identity & Traceability** | PASS | Spec 021 directly traces to Master Plan Phase 4; preserves preceding Specs 018, 019, and 020. |
| **Delivery Governance** | PASS | Strictly phased planning; tasks mapped to requirements before implementation begins. |

**Post-design result**: **PASS** — Zero constitution exceptions.

---

## Project Structure

### Documentation (this feature)

```text
specs/021-homepage-discovery/
├── spec.md                              # Specification with 4 user stories, 24 FRs, and 8 SCs
├── plan.md                              # This implementation plan
├── research.md                          # Technical decisions, guest wishlist lifecycle, and resilience
├── data-model.md                        # TypeScript models, database schema, and storage keys
├── quickstart.md                        # Verification scripts and acceptance scenarios
├── checklists/requirements.md           # Specification quality validation checklist
└── contracts/
    ├── homepage-discovery-api.md        # Public API endpoints (homepage, search, wishlist merge)
    └── discovery-ui-contracts.md        # UI component matrix, TourCard spec, and mobile drawer
```

### Source Code Targets (repository root)

```text
frontend/
├── src/
│   ├── app/[locale]/(public)/
│   │   ├── page.tsx                     # Rebuilt homepage server component (10-section hierarchy)
│   │   └── search/page.tsx              # Redesigned search listing page
│   ├── components/
│   │   ├── home/
│   │   │   ├── HeroSection.tsx          # "Where to?" search hero
│   │   │   ├── CategoryChips.tsx        # Rapid-filter category pills
│   │   │   ├── BooklyValuePanel.tsx     # Brand trust & guarantees panel
│   │   │   ├── CategoryGrid.tsx         # Popular categories showcase
│   │   │   ├── FeaturedTours.tsx        # Horizontal featured tour rail
│   │   │   ├── DestinationShowcase.tsx  # Top destination grid
│   │   │   ├── ThemedTourRails.tsx      # Dynamic non-empty curated rails
│   │   │   ├── BlogInspiration.tsx      # Editorial articles preview
│   │   │   └── PartnerAcquisitionPanel.tsx # Secondary operator CTA
│   │   ├── search/
│   │   │   ├── TourCard.tsx             # Standardized 4:3 card (wishlist, badges, ratings)
│   │   │   ├── SearchBar.tsx            # Search input with query persistence
│   │   │   ├── FilterSidebar.tsx        # Desktop left filter column
│   │   │   ├── MobileFilterDrawer.tsx   # Mobile staged modal drawer (touch targets >= 44px)
│   │   │   ├── ActiveFilterChips.tsx    # Dismissible active filter chips
│   │   │   └── SearchResults.tsx        # Dense results grid with empty state
│   │   └── shared/
│   │       └── WishlistHeartButton.tsx  # Interactive wishlist toggle atom
│   ├── lib/
│   │   ├── api/
│   │   │   ├── types.ts                 # Updated HomepageData, CuratedRail, TourCard
│   │   │   └── homepage.ts              # Homepage data client
│   │   └── wishlist/
│   │       ├── guestWishlist.ts         # localStorage guest favorites manager
│   │       └── useWishlist.ts           # Unified hook syncing guest & auth wishlists
│   └── messages/
│       ├── en.json / es.json / it.json / ar.json # Localized UI copy
backend/
├── app/Domains/Search/
│   ├── Actions/GetHomepageDataAction.php # Extended to aggregate curated_rails
│   └── Controllers/Public/HomepageController.php
└── routes/api/public.php                # Added /api/public/wishlist/merge endpoint
tests/
├── frontend/
│   ├── components/home/*.test.tsx       # Section isolation, empty/partial/error state tests
│   ├── components/search/*.test.tsx     # TourCard, FilterSidebar, MobileFilterDrawer tests
│   └── e2e/
│       ├── homepage.spec.ts             # 10-section layout, category navigation, performance tests
│       └── search.spec.ts               # Filter drawer, chips, URL sync tests
└── backend/
    └── Feature/Public/HomepageTest.php  # Curated rails serialization & cache test
```

---

## Planned Implementation Phases

1. **Phase 1: Setup & Foundational Infrastructure**:
   - Establish baseline Jest and Playwright discovery test suites.
   - Implement `guestWishlist.ts` (`localStorage` CRUD, deduplication, error-safe fallback).
   - Update `types.ts` with `CuratedRail` and standardized `TourCard` fields.
2. **Phase 2: Backend Curated Rails & API Compatibility**:
   - Extend `GetHomepageDataAction.php` to aggregate `curated_rails` (Family, Adventure, Seasonal).
   - Add `POST /api/public/wishlist/merge` for post-login guest wishlist synchronization.
   - Validate response contracts with PHPUnit tests.
3. **Phase 3: Standardized TourCard & Wishlist Interaction**:
   - Redesign `TourCard.tsx` (4:3 aspect ratio, wishlist heart button, badges, ratings, 2-line title).
   - Implement unified `useWishlist` hook with immediate optimistic UI updates and screen reader announcements.
   - Add unit tests for wishlist toggling, keyboard focus, and badge variants.
4. **Phase 4: Homepage 10-Section Rebuild & Resilience**:
   - Build `CategoryChips.tsx`, `BooklyValuePanel.tsx`, and `ThemedTourRails.tsx`.
   - Update `HeroSection.tsx`, `FeaturedTours.tsx`, `CategoryGrid.tsx`, and `DestinationShowcase.tsx`.
   - Recompose `frontend/src/app/[locale]/(public)/page.tsx` with section isolation (hiding empty/failed optional sections).
   - Ensure LCP high priority on first visible rail cover.
5. **Phase 5: Search & Filter Redesign**:
   - Build `FilterSidebar.tsx` (desktop) and `MobileFilterDrawer.tsx` (mobile modal with staged filters and "Show [N] Tours" button).
   - Build `ActiveFilterChips.tsx` with individual dismiss and clear-all actions.
   - Synchronize all filter and sort selections with URL search parameters and browser history.
6. **Phase 6: Verification & Quality Gates**:
   - Run complete Jest suite, Playwright e2e discovery tests, Axe WCAG 2.1 AA accessibility audits, and TypeScript typechecks.
   - Conduct production build verification for mobile 3G SSR ($<2.0\text{s}$) and CLS ($\le 0.05$).
