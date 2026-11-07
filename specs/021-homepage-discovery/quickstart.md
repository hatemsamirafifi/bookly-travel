# Quickstart & Verification: Homepage and Discovery (Spec 021)

**Spec**: Spec 021 — Homepage and Discovery  
**Baseline**: Branch `021-homepage-discovery`  
**Prerequisites**: Node 20+, PHP 8.2+, Composer, Docker (for e2e/PostgreSQL test environments).

---

## 1. Automated Verification Commands

### Frontend Unit & Component Tests (Jest)
Run unit tests for homepage sections, TourCard, wishlist hooks, and mobile filter drawer:
```bash
npm --prefix frontend test -- --testPathPattern="components/(home|search)|wishlist|homepage"
```

### End-to-End & Accessibility Tests (Playwright)
Run the discovery e2e test suite across desktop and mobile viewports:
```bash
npx playwright test frontend/tests/e2e/homepage.spec.ts frontend/tests/e2e/search.spec.ts
```

Run automated WCAG 2.1 AA accessibility checks with Axe-core:
```bash
npm --prefix frontend run test:a11y -- --grep="@a11y"
```

### TypeScript & Lint Verification
Ensure zero type errors and ESLint compliance:
```bash
npm --prefix frontend run typecheck
npm --prefix frontend run lint
```

### Backend API Verification (PHPUnit)
Test the extended homepage API action, curated rails serialization, and wishlist merge:
```bash
docker compose exec backend php artisan test --filter=HomepageTest
```

---

## 2. Key Acceptance Scenarios & Manual Verification

### Scenario 1: Full 10-Section Homepage Hierarchy
1. Open `http://localhost:3000/en`.
2. Verify all 10 sections appear in exact vertical sequence:
   - Hero “Where to?” search
   - Category chips
   - Bookly value panel
   - Popular categories
   - Featured tours rail
   - Top destinations
   - Themed/curated rails (e.g. Family-Friendly)
   - Blog inspiration
   - Partner acquisition CTA
   - Footer
3. Verify the first tour cover in the featured rail has `fetchpriority="high"`.

### Scenario 2: Unauthenticated Guest Wishlist
1. Open the homepage in an incognito browser window (signed out).
2. Click the heart icon on any tour card.
3. Verify the heart fills immediately and `localStorage.getItem('bookly_guest_wishlist')` contains the tour ID.
4. Refresh the page; verify the tour remains favorited.
5. Log in or register an account; verify the guest favorite is merged into the account wishlist.
6. Log out; verify client storage is purged.

### Scenario 3: Category Chips Direct Navigation
1. On the homepage, click the "Walking Tours" chip.
2. Confirm the browser navigates directly to `http://localhost:3000/en/search?category=walking-tours`.
3. Confirm matching tours appear and the category filter in the sidebar shows "Walking Tours" checked.

### Scenario 4: Mobile Filter Drawer Staged Application
1. Emulate an iPhone viewport ($390\times844\text{px}$).
2. Navigate to `http://localhost:3000/en/search`.
3. Tap the sticky "Filters" button.
4. Verify the full-height drawer opens with focus trapped.
5. Select a price range and category; verify the URL has NOT changed yet, and the bottom button displays "Show [N] Tours".
6. Tap "Show [N] Tours"; verify drawer closes, URL updates with parameters, and results refresh.
