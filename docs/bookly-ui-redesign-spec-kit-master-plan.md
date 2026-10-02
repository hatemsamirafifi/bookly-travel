# Bookly Tripadvisor-Inspired UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign every Bookly web surface using Tripadvisor's information architecture and interaction density while preserving Bookly's navy/gold identity and existing tours-only business model.

**Architecture:** The public, traveler, auth, checkout, and partner surfaces remain Next.js 16 applications consuming the Laravel API. Filament remains the internal admin exception. Backend changes are additive and focus on structured itineraries, complete galleries, operator summaries, and related tours.

**Tech Stack:** Laravel 11, PHP 8.3+, PostgreSQL, Redis, Filament 3.3, Next.js 16, React 19, TypeScript 5, Tailwind CSS 4, next-intl, Jest, Playwright, Pest.

**Spec sequence:** This master plan governs the redesign program across Specs `018-027`. Spec `018-bookly-ui-redesign` retains the bootstrap, original program specification, and completed Phase 1 foundations. Phase 2 is Spec `019-tour-content-data`; subsequent phases own separate specifications as mapped below.

**Governing Constitution:** Bookly Constitution v2.1.0 (`.specify/memory/constitution.md`). Every generated Spec Kit artifact and implementation phase must pass its applicable principles and quality gates.

## Phase-to-Spec Numbering

| Master-plan phase | Spec | Canonical directory | Scope | Status |
|---|---|---|---|---|
| Phase 0 | `018` | `specs/018-bookly-ui-redesign/` | Spec Kit Bootstrap and Design Contracts | Existing baseline |
| Phase 1 | `018` | `specs/018-bookly-ui-redesign/` | Design System and Shared Application Shells | Implementation complete; CI/merge acceptance pending |
| Phase 2 | `019` | `specs/019-tour-content-data/` | Tour Content Data Model and API | Specification, design and tasks complete; implementation pending |
| Phase 3 | `020` | `specs/020-tour-detail-experience/` | Tripadvisor-Style Tour Detail Experience | Planned |
| Phase 4 | `021` | `specs/021-homepage-discovery/` | Homepage and Discovery | Planned |
| Phase 5 | `022` | `specs/022-supporting-web-pages/` | Blog, Legal, Verification, and Error Surfaces | Planned |
| Phase 6 | `023` | `specs/023-auth-traveler-checkout/` | Auth, Traveler, and Checkout | Planned |
| Phase 7 | `024` | `specs/024-partner-dashboard-editor/` | Partner Dashboard and Tour Editor | Planned |
| Phase 8 | `025` | `specs/025-filament-admin/` | Filament Admin | Planned |
| Phase 9 | `026` | `specs/026-redesign-quality-gate/` | Cross-Cutting Quality Gate | Planned |
| Phase 10 | `027` | `specs/027-redesign-deployment/` | Deployment and Compatibility | Planned |

Phase numbers and task numbers (for example Phase 2 / Task 2.1) remain unchanged. A generated `tasks.md` may have its own setup/foundation phase headings; those do not redefine this mapping. Specs `020-027` are reservations, not created or completed features.

Spec `018` and its existing artifacts MUST be preserved. Its original full-program requirements and contracts remain the design baseline; active Phase 2 requirements, contract revisions, and acceptance evidence belong to `specs/019-tour-content-data/`. Later specifications must reference their predecessor rather than overwrite its history. The former uncreated product-roadmap Specs `019-025` move to `028-034` in the PRD and product implementation roadmap.

**Phase 2 baseline:** `codex/019-tour-content-data` originally started from `codex/bookly-ui-redesign-all-work` at `84ae4e5`, retaining Spec 018 and Phase 1. Rechecked on 2026-10-02: [PR #27](https://github.com/hatemsamirafifi/bookly-travel/pull/27) is merged, while separate [Phase 1 PR #28](https://github.com/hatemsamirafifi/bookly-travel/pull/28) remains open. Fetched `origin/main` at `f1a1f37` contains the predecessor plus subsequent CI fixes and is the integration base for continued implementation. Preserve predecessor ancestry and do not duplicate overlapping Phase 1 work. Existing Phase 2 code still requires Spec 019 acceptance verification.

## Repository Readiness

- [x] Verified on 2026-09-21 after `git fetch --all --prune`.
- [x] Local `main` and `origin/main` both resolve to `11c534e` (`Merge pull request #26 from hatemsamirafifi/feature/stripe-payments-connect-invoicing`).
- [x] All 22 non-`main` branches currently published on `origin` are ancestors of `origin/main`; no local or remote branch has commits ahead of `origin/main`.
- [x] The Stripe payments prerequisite is satisfied: `feature/stripe-payments-connect-invoicing` resolves to `76b1f1e`, which is contained in `origin/main`.
- [x] Feature number `017` is assigned retrospectively to the merged Stripe Payments, Connect & Invoicing Foundation. The UI redesign intentionally uses `018`; this is not an accidental numbering gap.
- [x] Retrospective Spec Kit artifacts now exist under `specs/017-stripe-connect-invoicing/`; after Constitution v2 hardening, its PostgreSQL-backed feature suite passes 14 tests and 61 assertions.
- [x] Revalidated on 2026-09-25 after `git fetch origin --prune`: `HEAD`, local `main`, and `origin/main` resolve to `f3e8e25`; no remote branch is unmerged into `origin/main`. The existing `018-bookly-ui-redesign` branch was retained because it contains uncommitted feature work.

## Global Constraints

- The original Spec 018 bootstrap starts from updated `main` at or after verified baseline `11c534e`; its Phase 0 branch check applies to that fresh bootstrap.
- Continue an existing or dependent phase from its verified predecessor when required to preserve approved work. Record the branch, base commit, and pending PR dependencies before editing. Existing redesign branches ahead of `main` are dependencies to document, not grounds to discard their specifications.
- Keep each phase on its mapped specification and a separate `codex/` branch; preserve earlier spec directories and evidence when choosing a checkout.
- Preserve EN/ES/IT; Arabic and RTL are out of scope.
- Partners author English (EN) as the mandatory source of truth for tour content. The platform generates Spanish (ES) and Italian (IT) with AI; missing or outdated derived content does not block publishing and temporarily shows the current English source with a visible fallback notice. This decision is independent of live-guide languages.
- Live-guide/spoken languages are separate tour metadata with stable language codes (for example `de`, `en`, `es`), and their display names are localized for the page language.
- Redesign all web screens, including public, auth, traveler, checkout, partner, Filament admin, legal, blog, errors, and voucher verification.
- Emails, generated PDFs, native apps, hotels, flights, restaurants, forums, ads, rewards, and AI planning are out of scope.
- Preserve authentication, authorization, booking, payment, review-integrity, and publishing rules.
- Public and partner screens remain API-first; Filament remains internal-only.
- Keep all existing route URLs, canonical behavior, and locale prefixes.
- Target WCAG 2.1 AA and Lighthouse Performance >=90 on primary public pages.
- Do not copy Tripadvisor trademarks, source code, logo, proprietary text, or advertising surfaces.
- Use Bookly navy `#0A2540`, gold `#FFB800`, neutral white/off-white surfaces, and green only for trust/success states.
- Use `Plus Jakarta Sans` through `next/font`; do not retain Inter as the final product font.
- Support responsive layouts at 390px, 768px, 1024px, and 1440px.

## Confirmed Baseline at Original Spec 018 Bootstrap

- The current public tour contract does not expose structured itinerary, operator summary, difficulty, or related tours.
- `tours.itinerary` exists as legacy JSONB but is not consistently persisted or returned publicly.
- `tour_media` exists, but public gallery construction currently relies primarily on `cover_image_url`.
- Partner wizard itinerary is modeled as a string while Laravel expects an array.
- The current UI contains extensive hard-coded navy/gold values instead of consistently consuming semantic tokens.
- Existing booking creation charges the tour's base per-person price; the redesigned booking card must not fabricate fees or unsupported tier calculations.

---

## Phase 0 - Spec Kit Bootstrap and Design Contracts

### Task 0.1: Revalidate the base and create the feature workspace

For a fresh workspace, run from the repository root. The guard fails before feature creation if a newly published branch contains commits not present in `origin/main`. If `018-bookly-ui-redesign` already exists with uncommitted work, verify the base and branch in place; do not switch branches or recreate it:

```powershell
git fetch origin --prune
git switch main
git pull --ff-only origin main

$unmergedBranches = @(
  git branch -r --no-merged origin/main |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_ -like 'origin/*' -and $_ -notlike 'origin/HEAD*' }
)

if ($unmergedBranches.Count -gt 0) {
  throw "Branches not merged into origin/main: $($unmergedBranches -join ', ')"
}

& '.specify/scripts/powershell/create-new-feature.ps1' `
  -Json `
  -Number 18 `
  -ShortName 'bookly-ui-redesign' `
  'Redesign all Bookly web surfaces using Tripadvisor-inspired information architecture while preserving Bookly identity and tours-only behavior'
```

Expected branch and directory:

```text
018-bookly-ui-redesign
specs/018-bookly-ui-redesign/
```

**2026-09-25 result:** Base and remote-merge guard passed on the existing feature branch. Branch creation was correctly skipped because the workspace already exists; see `specs/018-bookly-ui-redesign/research.md` for the observed baseline.

### Task 0.2: Generate the standard Spec Kit artifacts

- [x] Create and fully populate the following artifacts:

```text
specs/018-bookly-ui-redesign/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   |-- tour-detail-api.md
|   `-- partner-tour-content-api.md
`-- tasks.md
```

- [x] Populate `spec.md` from this master plan.
- [x] Original Phase 0 check: cite Bookly Constitution v2.0.0 in `spec.md` and record a principle-by-principle Constitution Check in `plan.md`. New phases and release acceptance evaluate v2.1.0; the original checked item is historical evidence.
- [x] Verify the existing `/speckit.plan` deliverables: `plan.md`, `research.md`, `data-model.md`, both API contracts, and `quickstart.md`. The generator was not rerun over the dirty worktree during this Phase 0 audit.
- [x] Verify the existing `/speckit.tasks` deliverable against the Constitution Check. The generator was not rerun over the dirty worktree during this Phase 0 audit.
- [x] Compare `tasks.md` against every phase below and append explicit tasks for previously implicit media, operator, related-tour, auth, partner-editor, admin and supporting-page work. Existing task IDs stay stable.

### Task 0.3: Produce design documentation

`research.md` must document:

- Current route and component inventory.
- Existing reusable UI patterns and technical debt.
- Tripadvisor desktop/mobile information hierarchy.
- Bookly-specific design differentiation.
- Responsive gallery, sticky navigation, filtering, and booking CTA behavior.
- Accessibility and performance risks.
- A do-not-copy boundary for trademarks, wording, advertisements, and unsupported features.

Create visual acceptance matrices for:

- Homepage.
- Search/listing.
- Tour detail.
- Checkout.
- Traveler dashboard.
- Partner dashboard.
- Filament admin.

**2026-09-25 result:** `research.md` records the current route/component baseline, observed technical debt, reference-site hierarchy and originality boundary. `quickstart.md` contains the seven page-family matrices plus four-width, EN/ES/IT, keyboard, reduced-motion, data-integrity and production-build performance checks. The reference detail URL could be verified through search indexing but not fetched directly, so mobile behavior is documented as a Bookly proposal rather than a claimed observation.

**Checkpoint:** Specification and implementation artifacts contain no `NEEDS CLARIFICATION`, `TBD`, or placeholder sections.

**Commit:**

```bash
git add specs/018-bookly-ui-redesign
git commit -m "docs: define Bookly UI redesign specification"
```

**2026-09-25 commit scope:** Commit only `.specify/feature.json`, this master plan, and `specs/018-bookly-ui-redesign/` for Phase 0. Keep the ongoing backend/frontend translation implementation and the ignored local secret outside this documentation commit.

---

## Phase 1 - Design System and Shared Application Shells

**Status: Implementation complete (2026-10-01).** Tasks 1.1-1.3 are complete. The isolated Phase 1 branch passed 206 tests, lint, typecheck and production build. [PR #28](https://github.com/hatemsamirafifi/bookly-travel/pull/28) remains open; merge/CI acceptance and release-wide gates are still pending.

### Task 1.1: Establish semantic design tokens

**Files:**

- Modify: `frontend/src/app/globals.css`
- Modify: `frontend/src/lib/design-tokens.ts`
- Modify: `frontend/src/app/[locale]/layout.tsx`
- Test: `frontend/src/components/ui/__tests__/design-system.test.tsx`

**Produces:** Semantic color, spacing, typography, elevation, radius, focus, motion, and layout tokens consumed by all later phases.

- [x] Write tests asserting that primary interactive components consume semantic token classes and expose visible focus states.
- [x] Run the focused test and verify it fails against the current implementation.
- [x] Define tokens for brand, text, border, surface, overlay, trust, error, warning, focus, content widths, responsive gutters, spacing, radius, shadows, z-index, and motion.
- [x] Configure `Plus Jakarta Sans` in the locale root layout through `next/font`.
- [x] Add `prefers-reduced-motion` behavior.
- [x] Run the focused test, typecheck, and lint.

### Task 1.2: Build reusable primitives

**Files:**

- Create/refactor under `frontend/src/components/ui/`:
  - `Container.tsx`
  - `Button.tsx`
  - `IconButton.tsx`
  - `Chip.tsx`
  - `RatingBadge.tsx`
  - `SectionHeading.tsx`
  - `Breadcrumbs.tsx`
  - `Accordion.tsx`
  - `Tabs.tsx`
  - `Drawer.tsx`
  - `Dialog.tsx`
  - `CarouselRail.tsx`
  - `EmptyState.tsx`
  - `ErrorState.tsx`
  - `LoadingSkeleton.tsx`
- Test: colocated `__tests__` files for interactive primitives.

**Consumes:** Phase 1.1 semantic tokens.

**Produces:** The only approved primitives for later public and dashboard work.

- [x] Add failing keyboard, ARIA, loading, disabled, and focus-management tests.
- [x] Implement each primitive with the minimum client-side boundary required.
- [x] Verify keyboard navigation, focus restoration, accessible names, and reduced-motion behavior.
- [x] Run Jest, typecheck, and lint.

### Task 1.3: Redesign shared layouts

**Files:**

- Modify: `frontend/src/components/layout/Header.tsx`
- Modify: `frontend/src/components/layout/Footer.tsx`
- Modify: `frontend/src/components/layout/MobileNavPanel.tsx`
- Modify: public, auth, traveler, and partner route-group layouts.
- Test: `frontend/tests/e2e/auth-navigation.spec.ts`
- Test: shell-specific Axe tests under `frontend/tests/e2e/a11y/`.

Public header behavior:

- Desktop row: Bookly wordmark, expandable search, primary navigation, locale, wishlist/account.
- Secondary row: tour discovery categories and contextual navigation.
- Mobile: compact logo/search/account bar and navigation drawer.
- Do not render hotels, restaurants, rewards, AI planning, or other unsupported links.

- [x] Add failing guest, traveler, partner, mobile-menu, and keyboard navigation tests.
- [x] Implement the public, auth, traveler, and partner shells with shared primitives.
- [x] Verify existing auth guards and redirect semantics remain unchanged.
- [x] Capture visual snapshots at 390px and 1440px.

**Phase commands:**

```bash
cd frontend
npm test -- --runInBand
npm run typecheck
npm run lint
```

**Commit:**

```bash
git add frontend/src frontend/tests
git commit -m "feat: establish Bookly UI design system"
```

---

## Phase 2 - Tour Content Data Model and API

**Spec:** `019-tour-content-data` - [specification](../specs/019-tour-content-data/spec.md), [implementation plan](../specs/019-tour-content-data/plan.md), [implementation tasks](../specs/019-tour-content-data/tasks.md). Tasks 2.1-2.4 and their new contracts are owned by Spec 019; Spec 018 remains the reference baseline. Design and task generation completed on 2026-10-02; implementation acceptance remains pending.

### Task 2.1: Add localized structured itineraries

**Files:**

- Create: a timestamped migration adding `itinerary` to `tour_translations`.
- Modify: `backend/app/Models/TourTranslation.php`
- Modify: partner tour Form Requests and `TourService.php`.
- Test: `backend/tests/Feature/Search/TourDetailTest.php`
- Test: partner tour create/update/draft test suites.

**Canonical interfaces:**

```ts
interface ItineraryDay {
  day: number;
  title: string;
  description: string | null;
  stops: ItineraryStop[];
}

interface ItineraryStop {
  title: string;
  description: string | null;
  duration_minutes: number | null;
}
```

Validation:

- Maximum 30 days.
- `day`: integer 1-30.
- Day and stop titles: required, maximum 160 characters.
- Descriptions: nullable, maximum 2,000 characters.
- Maximum 20 stops per day.
- `duration_minutes`: nullable integer 1-1,440.

Migration behavior:

- Backfill valid `tours.itinerary` values into the English translation.
- Invalid or empty legacy values become `[]`.
- Keep `tours.itinerary` for backward compatibility.
- Select itinerary content by page locale; if only its translation is missing, disclose an English fallback rather than treating guide-language codes as translation availability.
- Stop writing new content to the legacy column.
- Do not drop the legacy column in this feature.
- Treat English as the canonical source for title, description, itinerary, inclusions/exclusions, important information, and all other traveler-facing tour fields. Partners are not required to author ES/IT.
- Track whether each derived translation is current, pending, outdated, or failed relative to the English source revision; queue generation after English creation or change, and reject late results for superseded revisions.
- Require the existing English title and description before publication; never gate publication on ES/IT translation readiness.
- Select the actual current content locale for public display and SEO. A missing, outdated, or failed ES/IT translation uses the current English source with a visible notice; ready content replaces it automatically.
- Gemini is the approved backend-only AI provider in the Spec Kit plan. Its model and key are configured through backend environment variables; the local key is ignored by Git and must be rotated before production use.

- [ ] Write failing migration, validation, locale-selection, and fallback tests.
- [ ] Add nullable JSONB `itinerary` to `tour_translations`.
- [ ] Add fillable/cast configuration.
- [ ] Update Form Requests, service synchronization, and draft serialization.
- [ ] Add translation-state, retry, source-revision, stale-result, and English-only publishing tests, including frontend and browser-level fallback/ready transitions.
- [ ] Run focused backend tests.

### Task 2.2: Persist difficulty and gallery media correctly

Guide/spoken languages are persisted as a separate tour-level code list and exposed independently of the localized tour translation records.

**Files:**

- Modify: `backend/app/Models/Tour.php`
- Modify: `backend/app/Domains/Partner/Services/TourService.php`
- Modify: partner tour request validation.
- Test: partner tour create/update tests and public tour detail tests.

- [ ] Add tests for difficulty persistence, cover selection, media ordering, URL deduplication, and cover fallback.
- [ ] Add `difficulty_level` to the authoritative Tour model fillable fields.
- [ ] Map partner `is_cover` UI state to `tour_media.type = cover|gallery`.
- [ ] Synchronize media transactionally during create/update.
- [ ] Build public images from sorted `tour_media`, placing the cover first.
- [ ] Use `cover_image_url` only as a backward-compatible fallback.
- [ ] Generate localized alt text from the tour title when no specific alt text exists.

### Task 2.3: Add operator and related-tour actions

**Files:**

- Create: `backend/app/Domains/Search/Actions/BuildPublicOperatorSummaryAction.php`
- Create: `backend/app/Domains/Search/Actions/GetRelatedToursAction.php`
- Test: focused action and tour-detail feature tests.

**Produces:**

```php
BuildPublicOperatorSummaryAction::execute(Partner $partner): ?array
GetRelatedToursAction::execute(Tour $tour, string $locale, int $limit = 4): array
```

```ts
interface OperatorSummary {
  name: string;
  description: string | null;
  logo_url: string | null;
  verified: boolean;
  average_rating: number | null;
  review_count: number;
  tour_count: number;
}
```

Rules:

- Never expose contact email, phone, tax, payout, address, user ID, or Stripe data.
- Public operator field names preserve the existing Spec 018 contract (`name`, `description`, `logo_url`, aggregates); `verified` is additive and means approved and active. Return no operator summary for an ineligible profile, and use a null average when no eligible reviews exist.
- Aggregates include only visible/flagged reviews on published, currently bookable operator tours, preserving the existing eligibility rule.
- Related tours must be published, currently bookable, and exclude the current tour.
- Rank same destination first, then same category, then rating.
- Preserve the existing default of four related tours; eight is the absolute ceiling, not a requirement to expand the response. Use the existing TourCard contract.

- [ ] Write failing privacy, aggregation, ranking, exclusion, and query-count tests.
- [ ] Implement both actions without controller or model business logic.
- [ ] Verify eager loading prevents N+1 queries.

### Task 2.4: Extend the public tour contract

**Files:**

- Modify: `backend/app/Domains/Search/Actions/GetTourDetailAction.php`
- Modify: `frontend/src/lib/api/types.ts`
- Modify: `frontend/src/components/seo/StructuredData.tsx`
- Create/update during planning: `specs/019-tour-content-data/contracts/tour-detail-api.md` and `partner-tour-content-api.md`, referencing the original Spec 018 contracts.

Add optional/additive fields:

```ts
interface TourDetail {
  difficulty_level?: 'easy' | 'moderate' | 'challenging' | null;
  itinerary: ItineraryDay[];
  operator?: OperatorSummary | null;
  related_tours: TourCard[];
  guide_languages: string[]; // spoken/live-guide language codes, not content locales
  content_locale: 'en' | 'es' | 'it'; // actual displayed tour-content language
  itinerary_locale: 'en' | 'es' | 'it'; // actual displayed itinerary language
}
```

- [ ] Add contract tests for the exact response shape.
- [ ] Eager-load translations, category, media, availability, and partner profile.
- [ ] Preserve every current response field.
- [ ] Keep the existing `languages` response field compatible with its documented spoken-language meaning; never populate it from available content translations.
- [ ] Emit real itinerary data as an ordered JSON-LD `ItemList`.
- [ ] Retain meeting point as a `Place` and include all gallery image URLs.
- [ ] Omit empty aggregate-rating structured data when no reviews exist.

**Phase commands:**

```bash
cd backend
php artisan test --filter=TourDetailTest
php artisan test --filter=Partner
php artisan test
```

**Commit:**

```bash
git add backend frontend/src/lib/api/types.ts frontend/src/components/seo specs/019-tour-content-data/contracts
git commit -m "feat: add structured tour content contract"
```

---

## Phase 3 - Tripadvisor-Style Tour Detail Experience

**Spec:** `020-tour-detail-experience` (planned; create through Spec Kit when this phase starts).

### Task 3.1: Decompose the detail page

**Files:**

- Refactor: `frontend/src/components/tour/TourDetail.tsx`
- Create:

```text
frontend/src/components/tour/
|-- TourHeader.tsx
|-- TourGallery.tsx
|-- TourSectionNav.tsx
|-- TourOverview.tsx
|-- TourFacts.tsx
|-- TourHighlights.tsx
|-- TourItinerary.tsx
|-- TourInclusions.tsx
|-- TourMeetingPoint.tsx
|-- OperatorSummary.tsx
|-- TourReviews.tsx
|-- RelatedTours.tsx
|-- BookingCard.tsx
`-- MobileBookingBar.tsx
```

Page order:

1. Breadcrumbs.
2. Title, rating, review link, location, and wishlist.
3. Mosaic gallery.
4. Sticky section navigation.
5. Overview and read-more control.
6. Duration, group size, languages, and difficulty.
   Here, languages means available live-guide languages, with names localized for EN/ES/IT.
7. Highlights.
8. Structured itinerary.
9. Included/excluded accordions.
10. Meeting point and cancellation policy.
11. Operator summary.
12. Full reviews.
13. Related experiences.

Optional empty sections must disappear with their navigation tabs.

### Task 3.2: Implement the responsive gallery

Desktop:

- One large cover image and up to four supporting tiles.
- “See all photos” overlay with total count.
- Full-screen lightbox with arrows, Escape, focus trap, and captions.

Mobile:

- Swipeable full-width carousel.
- Image counter.
- No thumbnail strip.
- Fixed aspect ratio to avoid layout shift.

- [ ] Write failing gallery keyboard, ordering, fallback, and mobile tests.
- [ ] Implement the mosaic and lightbox.
- [ ] Verify focus returns to the triggering element after closing.

### Task 3.3: Implement sticky section navigation

- [ ] Write tests for active-section changes and missing sections.
- [ ] Scroll tabs to page sections with fixed-header offset.
- [ ] Use IntersectionObserver to update the active tab.
- [ ] Support horizontal mobile overflow and keyboard tab navigation.

### Task 3.4: Implement desktop and mobile booking CTAs

- Display the base price per adult/person.
- Select only API-provided available dates.
- Restrict travelers to the tour group-size limits.
- Show estimated total as `base_price x travelers`.
- Do not invent fees or unsupported tier pricing.
- Pass date and travelers to the existing checkout query.
- Show cancellation language only when a policy exists.
- Render unavailable state without enabling checkout.
- Use a bottom safe-area booking bar on mobile.

- [ ] Write failing total, validation, date, deep-link, and unavailable-state tests.
- [ ] Implement the booking components.
- [ ] Verify the existing checkout receives the selected date and travelers.

### Task 3.5: Tour-detail verification

- [ ] Add unit tests for every new section.
- [ ] Add E2E: search -> tour -> date/travelers -> checkout.
- [ ] Add Axe coverage.
- [ ] Capture 390px, 768px, 1024px, and 1440px snapshots.

**Commit:**

```bash
git add frontend/src/components/tour frontend/src/app frontend/tests
git commit -m "feat: rebuild tour detail experience"
```

---

## Phase 4 - Homepage and Discovery

**Spec:** `021-homepage-discovery` (planned; create through Spec Kit when this phase starts).

### Task 4.1: Rebuild the homepage

Order:

1. “Where to?” heading and search.
2. Tour-category chips.
3. Promotional Bookly value panel without external advertisements.
4. Interest/category cards.
5. Featured or traveler-loved tour rail.
6. Featured destinations.
7. Family/adventure/seasonal rails when matching data exists.
8. Blog inspiration.
9. Partner acquisition panel.
10. Expanded footer.

- [ ] Add tests for complete, partial, empty, loading, and API-error states.
- [ ] Implement sections using existing homepage and blog data.
- [ ] Ensure missing data hides only the affected section.

### Task 4.2: Redesign tour cards

Cards display:

- 4:3 image.
- Wishlist action.
- Optional Bookly badge such as Featured.
- Location/category context.
- Two-line title.
- Gold rating and review count.
- Duration.
- From price.
- Visible keyboard focus.

- [ ] Add failing grid, rail, navigation, and wishlist tests.
- [ ] Implement shared grid and horizontal-rail variants without duplicating card logic.

### Task 4.3: Redesign search, categories, and destinations

Desktop:

- Search summary and result count.
- Left filter sidebar.
- Sort control aligned with results.
- Dense grid/list based on the existing route behavior.

Mobile:

- Filter and sort buttons.
- Full-height filter drawer.
- Applied-filter chips.
- Preserve query parameters and browser history.

- [ ] Add tests for query persistence, filter drawer, pagination, sorting, and empty results.
- [ ] Implement the redesigned listing shell without changing search API semantics.
- [ ] Add visual and Axe coverage.

**Commit:**

```bash
git add frontend/src/components/home frontend/src/components/search frontend/src/app frontend/tests
git commit -m "feat: redesign public discovery surfaces"
```

---

## Phase 5 - Blog, Legal, Verification, and Error Surfaces

**Spec:** `022-supporting-web-pages` (planned; create through Spec Kit when this phase starts).

**Files:** Existing blog, legal, not-found, error, rate-limit, and voucher-verification components/routes.

- [ ] Add focused visual and accessibility tests for each layout family.
- [ ] Apply shared typography, containers, cards, breadcrumbs, and loading states to blog listing/detail/category/preview.
- [ ] Preserve preview `noindex` behavior and article structured data.
- [ ] Restyle privacy, terms, not-found, gone, rate-limit, offline/error boundaries, and voucher verification.
- [ ] Keep legal content semantics unchanged.
- [ ] Ensure every error surface provides an actionable recovery route.

**Commit:**

```bash
git add frontend/src frontend/tests
git commit -m "feat: unify supporting public surfaces"
```

---

## Phase 6 - Auth, Traveler, and Checkout

**Spec:** `023-auth-traveler-checkout` (planned; create through Spec Kit when this phase starts).

### Task 6.1: Redesign authentication

Apply a shared responsive auth shell to:

- Login.
- Registration.
- Forgot/reset password.
- Email verification.
- Partner registration and invitations.

Requirements:

- Correct validation and server-error placement.
- Password visibility controls.
- Mobile single-column layout.
- Preserve guards and redirects.

### Task 6.2: Redesign traveler pages

Apply a shared traveler account shell to:

- Profile and preferences.
- Wishlist.
- Booking list and filters.
- Booking details and timeline.
- Review list/submission.

### Task 6.3: Redesign checkout

- Clear numbered steps.
- Sticky order summary on desktop.
- Collapsible summary on mobile.
- Maintain Zustand session behavior.
- Preserve Stripe Elements, idempotency, price drift, cancellation, confirmation, and error handling.
- Style Stripe Elements only through supported Stripe appearance options.

### Task 6.4: Verify critical journeys

- [ ] Run existing auth, booking, payment, wishlist, review, and profile tests before changes.
- [ ] Add visual and accessibility coverage before implementing each screen family.
- [ ] Implement auth, traveler, and checkout redesigns.
- [ ] Test refresh and back-navigation during checkout.
- [ ] Test price-change, rate-limit, validation, cancellation, and payment-failure states.
- [ ] Re-run all existing critical-flow tests.

**Commit:**

```bash
git add frontend/src frontend/tests
git commit -m "feat: redesign traveler and checkout journeys"
```

---

## Phase 7 - Partner Dashboard and Tour Editor

**Spec:** `024-partner-dashboard-editor` (planned; create through Spec Kit when this phase starts).

### Task 7.1: Redesign partner dashboard shell

- Replace the solid sidebar with a refined operational shell.
- Add responsive collapse and mobile drawer behavior.
- Standardize page headers, filters, cards, tables, pagination, empty states, and status badges.

Apply to:

- Overview.
- Analytics.
- Tours.
- Pricing.
- Availability.
- Bookings and booking detail.
- Reviews.
- Profile/settings.
- Onboarding states.

Analytics compatibility requirements:

- Convert backend revenue integers from minor units before currency formatting;
  never render `1450000` cents as `€1,450,000.00`.
- Treat the current `conversion_rate: 0.0` as unavailable placeholder data, not
  as a measured 0% conversion result. Spec `025` owns the real tour-view/funnel
  denominator.
- Keep `review_count` and `upcoming_bookings` optional until the backend emits
  them; the UI must not fabricate either metric.

### Task 7.2: Build structured itinerary editing

The editor must support:

- Add/remove/reorder days.
- Add/remove/reorder stops.
- Per-day and per-stop validation.
- EN/ES/IT content tabs.
- Translation completeness indicators.
- Gallery cover selection and ordering.
- Preview matching the public tour detail.
- Existing autosave and draft recovery.
- Exact serialization to the Phase 2 contract.

New fields remain optional for existing tours. Their absence must not introduce a new publication gate.

### Task 7.3: Partner verification

- [ ] Add store/reducer tests for itinerary editing.
- [ ] Add payload serialization and API validation tests.
- [ ] Add autosave and draft-restore tests.
- [ ] Preserve ownership and authorization tests.
- [ ] Add analytics component coverage for minor-unit revenue formatting,
  placeholder conversion-rate handling, and absent optional metrics.
- [ ] Add E2E: partner creates tour -> itinerary/media -> submit for review.
- [ ] Implement and verify all partner screen families.

**Commit:**

```bash
git add frontend/src backend/app/Domains/Partner backend/tests frontend/tests
git commit -m "feat: redesign partner dashboard and tour editor"
```

---

## Phase 8 - Filament Admin

**Spec:** `025-filament-admin` (planned; create through Spec Kit when this phase starts).

### Task 8.1: Create the admin theme

**Files:**

- Create: `backend/resources/css/filament/admin/theme.css`
- Modify: `backend/app/Providers/Filament/AdminPanelProvider.php`
- Modify: backend Vite configuration only as required to compile the theme.

- [ ] Register the Vite theme.
- [ ] Apply Bookly branding, typography, colors, navigation, focus, and table styles.

### Task 8.2: Redesign admin resources

Apply consistent tables, filters, badges, forms, infolists, empty states, and actions across admin resources.

Tour moderation must display:

- Structured itinerary.
- Full media gallery.
- Operator identity and status.
- Pricing and availability.
- Public preview link.
- Existing approve/reject/archive actions.

Governed actions must continue using Actions/Services and audit logs. Business logic must not move into Filament callbacks.

### Task 8.3: Admin verification

- [ ] Add or extend admin authentication and authorization tests.
- [ ] Test tour review with and without itinerary/operator data.
- [ ] Verify approval/rejection audit behavior.
- [ ] Run Filament resource smoke tests.
- [ ] Capture desktop and tablet visual checks.

**Commit:**

```bash
git add backend/app/Filament backend/app/Providers/Filament backend/resources backend/tests
git commit -m "feat: apply Bookly design system to admin"
```

---

## Phase 9 - Cross-Cutting Quality Gate

**Spec:** `026-redesign-quality-gate` (planned; create through Spec Kit when this phase starts).

### Internationalization

- [ ] Move all new visible strings into `messages/en.json`, `messages/es.json`, and `messages/it.json`.
- [ ] Run the existing completeness script.
- [ ] Use locale-aware date, number, price, and plural formatting.
- [ ] Remove English literals from reusable user-facing components.

### Accessibility

- [ ] Verify WCAG 2.1 AA contrast.
- [ ] Verify logical heading order.
- [ ] Complete primary journeys using keyboard only.
- [ ] Verify dialog focus trapping and restoration.
- [ ] Verify gallery, carousel, tabs, accordions, filters, and menus.
- [ ] Verify 44x44px mobile hit targets.
- [ ] Verify reduced-motion behavior.
- [ ] Confirm no serious or critical Axe violations.

### Performance

- [ ] Use server components by default.
- [ ] Isolate client components to interactive controls.
- [ ] Configure accurate `next/image` sizes and priority.
- [ ] Lazy-load below-the-fold rails and lightboxes.
- [ ] Avoid unnecessary gallery and review JavaScript.
- [ ] Prevent layout shifts with image ratios and skeletons.
- [ ] Maintain Lighthouse Performance >=90 on homepage, search, tour detail, and blog detail.

### Full regression commands

```bash
cd frontend
npm test -- --runInBand
npm run typecheck
npm run lint
npm run build
npm run test:e2e
npm run test:a11y
```

```bash
cd backend
php artisan test
vendor/bin/phpstan analyse
```

Run migration coverage against PostgreSQL, not SQLite alone.

### Visual acceptance matrix

Capture at 390px, 768px, 1024px, and 1440px:

- Homepage.
- Search results.
- Tour detail.
- Checkout.
- Traveler bookings.
- Partner dashboard.
- Partner tour editor.
- Admin dashboard.

Compare information hierarchy and interaction density with the Tripadvisor references while ensuring Bookly remains visually distinct.

**Commit:**

```bash
git add frontend backend specs/026-redesign-quality-gate
git commit -m "test: complete UI redesign quality gates"
```

---

## Phase 10 - Deployment and Compatibility

**Spec:** `027-redesign-deployment` (planned; create through Spec Kit when this phase starts).

Deployment order:

1. Deploy additive backend migration and API changes.
2. Run itinerary backfill.
3. Verify the old frontend remains functional with the new response.
4. Deploy design-system and public frontend changes.
5. Deploy traveler and checkout changes.
6. Deploy partner and Filament changes.
7. Run production smoke tests.
8. Monitor Sentry, API latency, booking errors, and client exceptions.

Rollback rules:

- Frontend phases can roll back independently because API additions are backward-compatible.
- Do not roll back the itinerary column after new data has been written.
- Disable new rendering by reverting the frontend deployment, not by deleting migrated data.
- Legacy `tours.itinerary` removal requires a separate future feature after adoption is verified.

---

## Dependencies and Execution Order

```text
Repository baseline verified
main >= 11c534e; no origin branch ahead
        |
        v
Phase 0 Spec Kit artifacts
        |
        v
Phase 1 Design system
        |
        v
Phase 2 Data/API
        |
        v
Phase 3 Tour detail
        |
        +--> Phase 4 Public discovery --+
        +--> Phase 5 Supporting pages  +--> Phase 9 Quality gate
        +--> Phase 6 Traveler/checkout +
        |
        v
Phase 7 Partner
        |
        v
Phase 8 Admin
        |
        v
Phase 10 Deployment
```

Phases 4, 5, and 6 may overlap only after Phase 1 is stable. Phase 7 requires the Phase 2 contract. Phase 8 requires Phase 2 data and Phase 1 tokens.

## Definition of Done

- Every Bookly web route uses the new design system.
- Tour detail matches the approved Tripadvisor-inspired information hierarchy.
- Structured itinerary, gallery, operator, and related tours work end-to-end.
- Partners can author and admins can review all new tour content.
- Booking, payment, auth, publishing, and review behavior remains correct.
- EN/ES/IT messages are complete.
- No serious accessibility violations remain.
- Primary public pages score at least 90 in Lighthouse Performance.
- Full frontend and backend suites pass.
- No private partner data appears in public responses.
- Live-guide language codes remain independent from EN/ES/IT tour content translations and display with localized names on tour detail pages.
- Generated Spec Kit requirements, contracts, tasks, and implementation remain traceable to this master plan.

## Explicit Assumptions

- Tripadvisor is a structural and interaction reference, not a pixel-perfect or trademark clone.
- Existing URLs and core workflows remain stable.
- The redesign ships incrementally through the mapped phase specifications and separate branches. Dependent branches document unmerged prerequisites; each phase remains independently testable, and release acceptance stays separate from implementation completion.
- Emails, PDFs, Arabic/RTL, native applications, AI planning, and non-tour travel verticals remain outside this feature.
