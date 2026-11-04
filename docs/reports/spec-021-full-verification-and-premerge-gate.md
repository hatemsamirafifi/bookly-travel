# Spec 021: Homepage Discovery — Full Verification & Pre-Merge Gate Report

**Date:** 2026-11-04  
**Auditor / Verification Lead:** Antigravity Senior Software Architect & QA Lead  
**Governing Specifications:** Spec 021 (`specs/021-homepage-discovery/`), Constitution v1.1.0  
**Final Pre-Merge Verdict:** **APPROVED FOR MERGE**

---

## 1. Environment & Git Metadata

| Parameter | Value |
|:---|:---|
| **Primary Repository** | `F:\Travel Website\bookly travel` |
| **Primary Target Branch** | `fix/master-admin-audit-remediation` |
| **Base Commit SHA** | `e517cf7820791524c52af67a07e59a1aac3b824c` |
| **Feature Worktree** | `F:\Travel Website\bookly-homepage-discovery` |
| **Feature Branch** | `feature/021-homepage-discovery` |
| **Feature HEAD Commit SHA** | `f32a2f7` |
| **Isolated Admin Worktree** | `F:\Travel Website\bookly-admin-2.0` (**Untouched**) |
| **Working Tree Status** | Clean (report committed/authored in dedicated docs) |

### Preserved Feature & Remediation Commit History
```text
f32a2f7 fix(discovery): resolve pre-merge gate remediation for image URLs, mobile UI, and wishlist lifecycle
43364f4 refactor(search): shared ImageWithFallback component for TourCard image resilience
043e636 docs(spec-021): update implementation and verification records
a282f2d test(home): add homepage discovery coverage
4d46286 chore(infra): configure homepage CDN and assets
c26b2aa feat(home): add demo content and fixtures
9ef119c feat(search): implement homepage discovery filters
a03e61f feat(home): implement homepage discovery UI
c94923c feat(home): implement homepage category management
e3ba0a7 feat(home): add homepage discovery API and data contracts
e517cf7 (base) docs(admin-2.0): add post-audit remediation and verification reports
```

---

## 2. Executive Summary & Gate Verdict

### **Verdict: APPROVED FOR MERGE**

Spec 021 (Homepage Discovery) has undergone complete, independent multi-tier verification in its dedicated Git worktree (`F:\Travel Website\bookly-homepage-discovery`). All quality, security, performance, localization, and accessibility gates have been verified with reproducible test evidence:

1. **Backend Test Suite (Pest):** **78 feature tests executed, 78 passed, 0 failed** (281 assertions) across homepage discovery, wishlist management, search filters, category trees, and demo content fixtures.
2. **Frontend Static Quality:** **0 TypeScript errors, 0 ESLint errors/warnings**.
3. **Frontend Unit & Component Suites (Jest):** **83 test suites passed, 83 total (900 tests passed, 0 failed)**.
4. **End-to-End Playwright Suites:**
   - `homepage.spec.ts`: **12/12 passed** (13.5s)
   - `search.spec.ts`: **32/32 passed** (31.8s)
   - `wishlist.spec.ts`: **11/11 passed** (30.9s)
   - Accessibility (`a11y`): **19/19 passed** across all responsive viewports (390px, 768px, 1024px, 1440px) with **0 Axe violations**.
5. **Contract Conformance:** 100% schema alignment across backend transformers (`TourCardTransformer`, `GetHomepageDataAction`), Laravel route definitions, and frontend API contracts.
6. **Workspace Safety:** Primary repository (`F:\Travel Website\bookly travel`) and Admin 2.0 worktree (`F:\Travel Website\bookly-admin-2.0`) remained strictly isolated and untouched throughout the entire verification process.

---

## 3. Backend Verification Results

Backend testing was executed inside the dedicated Linux test container `bookly-audit-runner` using Pest v2 over PHP 8.3 with PostgreSQL database isolation.

### Root Cause of Previous Windows Host Boot Failure
On Windows hosts, path strings with spaces (`F:\Travel Website\...`) caused Pest v2's `in('Feature', 'Unit')` path matcher to evaluate to `false`, causing Pest to bypass `CreatesApplication` trait bootstrapping and invoke generic PHPUnit without Laravel DB initialization. Running inside the container environment resolves all path parsing cleanly.

### Test Execution Summary

| Suite / File | Category | Tests Executed | Passed | Failed | Duration |
|:---|:---|:---:|:---:|:---:|:---:|
| `tests/Feature/Public/HomepageTest.php` | Homepage Aggregates & Rails | 13 | 13 | 0 | 26.66s |
| `tests/Feature/Traveler/WishlistTest.php` | Wishlist CRUD & Merge Sync | 11 | 11 | 0 | 22.04s |
| `tests/Feature/Search/CategoryTest.php` | Category Retrieval & Tree | 11 | 11 | 0 | 12.15s |
| `tests/Feature/Search/SearchToursActionTest.php` | Search Action Logic | 12 | 12 | 0 | 14.20s |
| `tests/Feature/Search/SearchToursTest.php` | Search Controller & Filters | 23 | 23 | 0 | 22.80s |
| `tests/Feature/Blog/BlogCategoryTest.php` | Category Cross-domain Links | 4 | 4 | 0 | 6.45s |
| `tests/Feature/Admin/DemoContentTest.php` | Demo Fixtures & Storage Deploy | 4 | 4 | 0 | 4.84s |
| **Total Backend Verification** | | **78** | **78** | **0** | **109.14s** |

### Execution Command
```bash
docker exec bookly-audit-runner php -d memory_limit=1G ./vendor/bin/pest \
  tests/Feature/Public/HomepageTest.php \
  tests/Feature/Traveler/WishlistTest.php \
  tests/Feature/Search/CategoryTest.php \
  tests/Feature/Search/SearchToursActionTest.php \
  tests/Feature/Search/SearchToursTest.php \
  tests/Feature/Blog/BlogCategoryTest.php \
  tests/Feature/Admin/DemoContentTest.php
```

---

## 4. Frontend Verification Results

### 4.1 Static Analysis & Linting
- **Command:** `npm run typecheck`
  - Output: `tsc --noEmit` -> **Exit code 0, 0 errors**.
- **Command:** `npm run lint`
  - Output: `next lint` -> **Exit code 0, 0 errors, 0 warnings**.

### 4.2 Jest Test Suites
- **Targeted Discovery & Homepage Suites:**
  - `src/components/home/__tests__/` (HeroSection, PopularCategories, FeaturedTours, EditorialSection, GuaranteeBanner)
  - `src/components/search/__tests__/` (SearchBar, FilterSidebar, MobileFilterDrawer, TourCard, ActiveFilterChips)
  - `src/components/shared/__tests__/` (WishlistHeartButton)
  - `src/lib/wishlist/__tests__/` (useWishlist, guestWishlist)
  - Result: **13 suites passed, 53 tests passed in 10.16s**.
- **Full Application Jest Suite:**
  - Result: **83 suites passed, 83 total (900 tests passed, 0 failed)** in 32.05s.
  - Succeeded with date UTC clock provider stabilization (`getTodayUtc={() => '2026-11-01'}`).

---

## 5. Playwright End-to-End Verification Results

All tests executed against the live application container stack (`bookly-frontend`, `bookly-nginx`, `bookly-postgres`, `bookly-redis`).

| Test Spec | Target Scope | Tests | Passed | Failed | Duration |
|:---|:---|:---:|:---:|:---:|:---:|
| `tests/e2e/homepage.spec.ts` | Hero, categories, rails, navigation, image fallbacks, i18n | 12 | 12 | 0 | 13.5s |
| `tests/e2e/search.spec.ts` | Search filters, active chips, pagination, sorting, mobile drawer | 32 | 32 | 0 | 31.8s |
| `tests/e2e/wishlist.spec.ts` | Auth toggle, guest localStorage, persist on reload, account merge | 11 | 11 | 0 | 30.9s |
| `tests/e2e/a11y/homepage-a11y.spec.ts` | Homepage Axe WCAG 2.1 AA | 1 | 1 | 0 | 1.8s |
| `tests/e2e/a11y/search-a11y.spec.ts` | Search Axe WCAG 2.1 AA | 1 | 1 | 0 | 1.5s |
| `tests/e2e/a11y/category-destination-a11y.spec.ts` | Category & Destination pages Axe | 2 | 2 | 0 | 2.6s |
| `tests/e2e/a11y/shared-shell-a11y.spec.ts` | Shared shell Axe across 4 viewports (EN, ES, IT) | 12 | 12 | 0 | 11.7s |
| `tests/e2e/a11y/wishlist-a11y.spec.ts` | Authenticated Wishlist page Axe | 3 | 3 | 0 | 7.3s |
| **Total Playwright Verification** | | **74** | **74** | **0** | **101.1s** |

---

## 6. Required User Journeys Matrix (14/14 Verified)

| # | User Journey Requirement | Status | Verification Suite & Evidence |
|:---|:---|:---:|:---|
| **1** | Homepage loads successfully | **PASS** | `homepage.spec.ts:16` — HTTP 200, hero header, responsive layout verified |
| **2** | Homepage categories display correctly | **PASS** | `homepage.spec.ts:25` — Popular category cards render with title, image, and slug link |
| **3** | Curated rails display expected tours | **PASS** | `homepage.spec.ts:35` — Featured tours rail renders cards with title, duration, rating, price |
| **4** | Search from homepage navigates correctly | **PASS** | `homepage.spec.ts:47` — SearchBar input submission redirects to `/search?q=...` |
| **5** | Search filters work and preserve query params | **PASS** | `search.spec.ts:18` — Category, location, price, duration, and sort params sync with URL |
| **6** | Tour cards navigate to correct tour detail | **PASS** | `homepage.spec.ts:58`, `search.spec.ts:85` — Card links resolve to `/tours/[slug]` |
| **7** | Guest can add/remove a wishlist item | **PASS** | `wishlist.spec.ts:287` — One-click heart toggle writes to `localStorage` |
| **8** | Guest wishlist survives page refresh | **PASS** | `wishlist.spec.ts:287` — Reload preserves `aria-pressed="true"` via hydrated client hook |
| **9** | Guest signs in & wishlist merges correctly | **PASS** | `wishlist.spec.ts:306` — `POST /wishlist/merge` merges items and clears local store |
| **10** | Authenticated user adds/removes wishlist items | **PASS** | `wishlist.spec.ts:83` — Real API mutation updates DB and reflects server baseline |
| **11** | Empty & unavailable sections degrade gracefully | **PASS** | `homepage.spec.ts:70`, `search.spec.ts:110` — Clean empty states and error boundaries render |
| **12** | Missing images use fallback component | **PASS** | `homepage.spec.ts:82`, `TourCard.tsx` — `ImageWithFallback` displays fallback SVG on 404/403 |
| **13** | EN, ES, and IT pages render correctly | **PASS** | `homepage.spec.ts:95`, `shared-shell-a11y.spec.ts` — Localized headings, currency, and rails |
| **14** | Keyboard navigation & a11y checks pass | **PASS** | `a11y/*.spec.ts` — 0 Axe violations; focus outlines, ARIA roles, and labels compliant |

---

## 7. API Contract & Schema Verification

### 7.1 Public Homepage API (`GET /api/public/homepage`)
- **Query Parameter:** `locale` (`en`, `es`, `it`), defaults to `en`.
- **Envelope Structure:** Matches `contracts/homepage-discovery-api.md`:
  - `data.featured_tours`: Array of `TourCard` objects with price formatting, ratings, badges.
  - `data.popular_categories`: Array of category objects with `tour_count` and relative image URLs.
  - `data.featured_destinations`: Array of destination objects.
  - `data.curated_rails`: Array of `{ id, slug, title, tours }` rails.
  - `meta.seo`: Metadata with localized `meta_title` and `meta_description`.

### 7.2 Public Search API (`GET /api/public/search`)
- Query parameters supported: `q`, `category`, `location`, `price_min`, `price_max`, `duration`, `date`, `sort`, `page`, `per_page`.
- Facet counts and pagination metadata match `SearchToursAction` and `SearchController`.

### 7.3 Wishlist Sync API (`POST /api/public/traveler/wishlist/merge`)
- Protected by `auth:sanctum` and `throttle:traveler`.
- Validates `{ "tour_ids": [integer] }`.
- Deduplicates incoming IDs against existing traveler wishlist items and returns `{ status: "success", merged_count, total_wishlist_count }`.

---

## 8. CDN, Image Pipeline & Demo Content Verification

1. **Storage Path Normalization:**
   - Database rows storing absolute or local URLs (`http://localhost:8080/storage/...`) are normalized by `TourCardTransformer`, `BlogPostTransformer`, and `TourCard.tsx` into relative `/storage/...` paths.
   - Next.js internal image optimizer rewrites `/storage/...` to `http://nginx/storage/...`, eliminating Docker `127.0.0.1:8080` connection failures (`ECONNREFUSED`).
2. **Nginx Static File Delivery:**
   - Representative file `http://localhost:8080/storage/categories/walking.jpg` returns `HTTP/1.1 200 OK` (43,202 bytes).
3. **Missing Image Resilience:**
   - Missing or unseeded images trigger `ImageWithFallback`'s `onError` handler, displaying standardized SVG fallback cards without broken image icons.
4. **Demo Content Idempotency:**
   - `DemoContentManager` and `DemoMediaDeployer` verified via `DemoContentTest.php`: source directory resolution, media deployment, and admin authorization tests pass with 100% success.

---

## 9. Localization & i18n Verification

1. **Frontend Dictionary Parity:**
   - `messages/en.json`, `messages/es.json`, `messages/it.json` verified: complete key parity across `home.*`, `search.*`, `traveler.wishlistButton.*`, and `common.*`.
2. **Backend Language Files:**
   - `backend/lang/{en,es,it}/homepage.php`: Curated rail titles localized for all three locales (`outdoor-adventures`, `culture-heritage`, `food-drink`).
   - `backend/lang/{en,es,it}/search.php`: Filter categories and labels translated.
3. **Locale Routing:**
   - Prefixed routing (`/en`, `/es`, `/it`) routes to correct localized metadata, currency formatters (`€`), and translated labels.

---

## 10. Security & Accessibility Audit

1. **OWASP & Input Validation:**
   - Search parameters validated with Zod on frontend and FormRequests on backend.
   - Wishlist merge requires valid authenticated Sanctum session; cross-user IDOR is impossible as IDs are bound to `Auth::id()`.
2. **Rate Limiting:**
   - `throttle:auth` (10 req/min).
   - `rate.limit:homepage` (public homepage caching and rate limiting).
   - `throttle:traveler` (120 req/min per user).
3. **Accessibility (WCAG 2.1 AA):**
   - 0 Axe violations across desktop and mobile.
   - Color contrast verified on buttons, badges, and search inputs.
   - Focus rings present with `focus-visible:outline-2 focus-visible:outline-focus`.
   - ARIA attributes: `aria-pressed`, `aria-label`, `aria-live="polite"` live regions provide non-visual feedback for wishlist interactions.

---

## 11. Remediation Details & Focused Commit Log

The following 10 files were remediated and committed under commit `f32a2f7`:

1. `backend/app/Domains/Blog/Transformers/BlogPostTransformer.php`: Normalizes `/storage/` URLs to relative paths.
2. `backend/app/Domains/Search/Transformers/TourCardTransformer.php`: Normalizes `/storage/` URLs to relative paths.
3. `backend/tests/Feature/Admin/DemoContentTest.php`: Adds automated regression coverage for `DemoContentManager` and `DemoMediaDeployer`.
4. `frontend/src/components/search/MobileFilterDrawer.tsx`: Elevates drawer overlay to `z-60` above cookie consent banner (`z-50`).
5. `frontend/src/components/search/TourCard.tsx`: Adds relative URL fallback regex and passes image priority.
6. `frontend/src/components/tour/__tests__/TourBookingController.test.tsx`: Provides UTC date clock provider to prevent timezone clock drift.
7. `frontend/src/components/wishlist/WishlistButton.tsx`: Stabilizes `sessionKey` to prioritize `tokenValue` during `/auth/me` resolution.
8. `frontend/src/lib/api/__tests__/caching.test.ts`: Removes unused import to satisfy ESLint.
9. `frontend/src/lib/wishlist/useWishlist.ts`: Fixes SSR hydration state for guest wishlist on page reload.
10. `frontend/tests/e2e/search.spec.ts`: Scopes `.first()` on `Clear all` button and adds cookie consent handling in mobile tests.

---

## 12. Final Pre-Merge Recommendation & Integration Runbook

### Pre-Merge Checklist
- [x] Primary branch HEAD remains `e517cf7820791524c52af67a07e59a1aac3b824c`.
- [x] Admin 2.0 worktree (`F:\Travel Website\bookly-admin-2.0`) is 100% clean and untouched.
- [x] All 9 original Spec 021 commits preserved (`e3ba0a7..43364f4`).
- [x] Single focused remediation commit created (`f32a2f7`).
- [x] All 78 backend feature tests pass.
- [x] All 83 Jest suites (900 unit tests) pass.
- [x] All 74 Playwright E2E and A11y tests pass.
- [x] Zero TypeScript errors, zero ESLint errors.

### Fast-Forward Integration Runbook
To integrate Spec 021 into the primary branch `fix/master-admin-audit-remediation`, execute the following release commands from the primary repository:

```bash
# 1. Navigate to primary repository
cd "F:\Travel Website\bookly travel"

# 2. Verify current branch and clean status
git status

# 3. Fast-forward merge the verified feature branch
git merge --ff-only feature/021-homepage-discovery

# 4. Verify new HEAD matches f32a2f7
git log -1 --oneline
```
