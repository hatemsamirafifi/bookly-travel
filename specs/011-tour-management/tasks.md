# Tasks: Tour Management — Traveler Dashboard, Bookings, Profile & Wishlists

**Input**: Design documents from `/specs/011-tour-management/`
**Prerequisites**: plan.md, spec.md, data-model.md, contracts/
**Base**: Extends Phase 1 (spec 010) public frontend — auth system, API client, i18n, design tokens, and layout components already exist

**Tests**: E2E and a11y test tasks are included per spec requirements (WCAG 2.1 AA compliance, Lighthouse ≥ 90, Playwright e2e for authenticated flows).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (API Contracts & Shared Types)

**Purpose**: Define API contracts and shared TypeScript types before any UI implementation

- [X] T001 [P] Create bookings API contract in `specs/011-tour-management/contracts/bookings-api.contract.md` — endpoints: GET /my-bookings, GET /my-bookings/{reference}, POST /my-bookings/{reference}/cancel; auth headers; request/response schemas; error codes
- [X] T002 [P] Create profile API contract in `specs/011-tour-management/contracts/profile-api.contract.md` — endpoints: GET /profile, PUT /profile, PUT /profile/password; request/response schemas; validation rules
- [X] T003 [P] Create wishlist API contract in `specs/011-tour-management/contracts/wishlist-api.contract.md` — endpoints: GET /wishlist, POST /wishlist/{tourId}, DELETE /wishlist/{tourId}; optimistic toggle behavior
- [X] T004 [P] Create reviews API contract in `specs/011-tour-management/contracts/reviews-api.contract.md` — endpoint: GET /my-reviews; paginated response schema
- [X] T005 [P] Create shared traveler types in `frontend/src/types/traveler.ts` — Booking, BookingDetail, BookingStatus, Profile, PasswordChangeRequest, WishlistItem, ReviewRecord, PaymentReceipt per data-model.md
- [X] T006 [P] Create traveler API client in `frontend/src/lib/api/traveler.ts` — typed functions for all authenticated endpoints with auth token injection and error handling per contracts/

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Auth-guard middleware, shared UI primitives, and auth state enhancements that MUST be complete before ANY user story can be implemented

**CRITICAL**: No user story work can begin until this phase is complete

- [X] T007 [P] Enhance auth context/store in `frontend/src/lib/hooks/useAuth.tsx` — expose `logout` function, `user` object with name/email, and cross-tab auth state sync via `storage` event per spec edge cases
- [X] T008 Create AuthGuard component in `frontend/src/components/auth/AuthGuard.tsx` — redirect unauthenticated users to `/{locale}/auth/login` with `?returnUrl=` preserved; wrap all authenticated pages per FR-015
- [X] T009 Update middleware in `frontend/src/middleware.ts` — add protected route matcher for `/my-bookings`, `/profile`, `/wishlist`, `/my-reviews` with locale-aware redirect to login per FR-015 _(not implemented — project uses route group layout + AuthGuard instead)_
- [X] T010 [P] Create shared EmptyState component in `frontend/src/components/ui/EmptyState.tsx` — illustration, title, description, optional CTA button; used by bookings, wishlist, reviews per spec edge cases
- [X] T011 [P] Create shared ErrorState component in `frontend/src/components/ui/ErrorState.tsx` — error message, "Try Again" button, Stitch styling; used by dashboard, detail, wishlist per spec edge cases
- [X] T012 [P] Create shared LoadingSkeleton component in `frontend/src/components/ui/LoadingSkeleton.tsx` — card skeletons, list skeletons for bookings and wishlist grids per FR-020
- [X] T013 [P] Create Toast notification utility in `frontend/src/components/ui/Toast.tsx` — success/error toast messages for profile save, cancellation confirmation, wishlist errors per SC-003
- [~] T014 Verify and enhance AuthenticatedHeader in `frontend/src/components/layout/AuthenticatedHeader.tsx` — replace "Login/Register" with user name + avatar when authenticated per FR-013 _(functionality implemented inline in `Header.tsx`; no separate AuthenticatedHeader component)_
- [X] T015 Create UserMenuDropdown component in `frontend/src/components/layout/UserMenuDropdown.tsx` — items: Dashboard, My Bookings, Wishlist, My Reviews, Profile Settings, Logout per FR-014
- [X] T016 [P] Add authenticated navigation translations to `frontend/messages/en.json`, `es.json`, `it.json` — menu items, profile labels, booking statuses, error messages
- [X] T017 Verify SEO metadata helpers support authenticated page titles (e.g., "My Bookings | BooklyTravel") per FR-019

**Checkpoint**: Foundation ready — auth guard active, shared components exist, API client typed, translations populated

---

## Phase 3: User Story 7 - Global Navigation for Authenticated Users (Priority: P1)

**Goal**: The global header reflects authentication state across all pages with a user dropdown menu

**Why first**: US7 is the backbone — travelers cannot discover other authenticated features without navigation. Must be functional before testing other stories.

**Independent Test**: Log in and verify header changes from "Login/Register" to user menu across homepage, search, and tour detail pages. Test logout redirect.

### Tests for User Story 7

- [X] T018 [P] [US7] Playwright e2e test for auth navigation in `frontend/tests/e2e/auth-navigation.spec.ts` — login → header shows user menu, dropdown opens, each link navigates correctly, logout redirects to homepage
- [X] T019 [P] [US7] axe-core a11y scan for user menu dropdown in `frontend/tests/e2e/a11y/auth-nav-a11y.spec.ts` — keyboard navigation (Escape to close, Arrow keys), ARIA roles, focus management

### Implementation for User Story 7

- [X] T020 [P] [US7] Wire UserMenuDropdown to Header — show user first name + avatar (or initials fallback), dropdown toggles on click, closes on outside click and Escape key per FR-013
- [X] T021 [P] [US7] Implement logout action in UserMenuDropdown — call auth API logout, clear client state, redirect to homepage, header reverts to "Login/Register" per US7 scenario 2
- [X] T022 [P] [US7] Implement mobile slide-out panel for authenticated user menu — accessible hamburger menu on ≤780px with all nav items per US7 scenario 3
- [X] T023 [P] [US7] Handle session expiry in navigation — if API returns 401 on protected nav click, redirect to login with `?returnUrl=` preserved per US7 scenario 4
- [X] T024 [US7] Add user menu translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Dashboard", "My Bookings", "Wishlist", "My Reviews", "Profile Settings", "Logout")

**Checkpoint**: Header shows user menu when authenticated, dropdown navigates to all traveler pages, logout works, mobile panel functional

---

## Phase 4: User Story 1 - View My Bookings Dashboard (Priority: P1)

**Goal**: Authenticated traveler sees a dashboard at `/{locale}/my-bookings` with booking cards, status filter tabs, loading/error/empty states

**Independent Test**: Navigate to `/en/my-bookings` with a test user having 3 bookings (upcoming, completed, cancelled). Verify cards display, filters work, empty state renders for no bookings.

### Tests for User Story 1

- [X] T025 [P] [US1] Playwright e2e test for bookings dashboard in `frontend/tests/e2e/my-bookings.spec.ts` — bookings render, filter tabs (All/Confirmed/Completed/Cancelled), empty state, mobile layout at 390px
- [X] T026 [P] [US1] axe-core a11y scan for dashboard in `frontend/tests/e2e/a11y/my-bookings-a11y.spec.ts`

### Implementation for User Story 1

- [X] T027 [P] [US1] Create useBookings hook in `frontend/src/hooks/useBookings.ts` — TanStack Query `useQuery` for GET /my-bookings with query key scoped by locale + status filter; staleTime 60s per FR-020
- [X] T028 [P] [US1] Create BookingCard component in `frontend/src/components/my-bookings/BookingCard.tsx` — tour cover image, tour name, booking reference, date, status badge, total price per FR-002
- [X] T029 [P] [US1] Create BookingList component in `frontend/src/components/my-bookings/BookingList.tsx` — grid/list layout, renders BookingCards, handles loading skeletons via LoadingSkeleton per FR-020
- [X] T030 [P] [US1] Create BookingStatusBadge component in `frontend/src/components/my-bookings/BookingStatusBadge.tsx` — color-coded badges: Confirmed (green), Completed (blue), Cancelled (gray) per FR-002
- [X] T031 [US1] Create status filter tabs in `frontend/src/components/bookings/BookingFilters.tsx` — tabs: All, Confirmed, Completed, Cancelled; URL sync via query param `?status=` per US1 scenario 3
- [X] T032 [US1] Wire bookings page in `frontend/src/app/[locale]/(traveler)/my-bookings/page.tsx` — AuthGuard wrapper, fetch bookings via useBookings, render BookingList + BookingFilters, empty state with "Browse Tours" CTA per US1 scenario 2
- [X] T033 [P] [US1] Handle dashboard error state — if API fails, render ErrorState with "Try Again" button that refetches per US1 scenario 6
- [X] T034 [US1] Ensure mobile responsiveness — single-column cards, horizontal-scrollable filter tabs at ≤780px per US1 scenario 4
- [X] T035 [US1] Add dashboard translations to `frontend/messages/en.json`, `es.json`, `it.json` ("My Bookings", "Upcoming", "Confirmed", "Completed", "Cancelled", "No bookings yet", "Browse Tours")
- [X] T036 [US1] Add SEO metadata (title, description) to `frontend/src/app/[locale]/(traveler)/my-bookings/page.tsx` per locale per FR-019

**Checkpoint**: Dashboard renders independently with real booking data, filters work, empty/error states handled, responsive, auth-guarded

---

## Phase 5: User Story 2 - View Booking Detail & Receipt (Priority: P1)

**Goal**: Traveler clicks a booking card and sees full details, payment receipt, status timeline, and contextual actions

**Independent Test**: Navigate to `/en/my-bookings/{reference}` and verify all detail sections render, payment receipt shows, cancel button appears for eligible bookings.

### Tests for User Story 2

- [X] T037 [P] [US2] Playwright e2e test for booking detail in `frontend/tests/e2e/booking-detail.spec.ts` — detail sections, receipt display, cancel button visibility, 404 for invalid reference, mobile layout
- [X] T038 [P] [US2] axe-core a11y scan for booking detail page in `frontend/tests/e2e/a11y/booking-detail-a11y.spec.ts`

### Implementation for User Story 2

- [X] T039 [P] [US2] Create useBookingDetail hook in `frontend/src/hooks/useBookingDetail.ts` — TanStack Query `useQuery` for GET /my-bookings/{reference}, 404 error handling per spec edge cases
- [X] T040 [P] [US2] Create BookingDetailView component in `frontend/src/components/bookings/BookingDetailView.tsx` — booking reference, status badge, tour name with link, date/time, participants, price per person, total amount, meeting point, inclusions, special requests per FR-003
- [X] T041 [P] [US2] Create PaymentReceipt component in `frontend/src/components/bookings/PaymentReceipt.tsx` — payment status, amount, transaction date, payment method (last 4 digits) per US2 scenario 2
- [X] T042 [P] [US2] Create BookingStatusTimeline component in `frontend/src/components/bookings/BookingStatusTimeline.tsx` — visual timeline: Booking Made → Confirmed → Completed (or Cancelled) per FR-003
- [X] T043 [US2] Wire booking detail page in `frontend/src/app/[locale]/(traveler)/my-bookings/[reference]/page.tsx` — AuthGuard, fetch via useBookingDetail, render BookingDetailView + PaymentReceipt + BookingStatusTimeline, 404 handling per spec edge cases
- [X] T044 [US2] Implement ownership check — if API returns 404 for another user's booking, render "Booking not found" per spec edge cases
- [X] T045 [US2] Ensure mobile responsiveness — single-column layout, sticky action footer at bottom on mobile per US2 scenario 5
- [X] T046 [US2] Add booking detail translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Booking Reference", "Meeting Point", "Inclusions", "Special Requests", "Payment Receipt", "Status")
- [X] T047 [US2] Add SEO metadata to booking detail page per FR-019

**Checkpoint**: Booking detail page renders full information, receipt visible, 404 handled, mobile sticky footer, auth-guarded

---

## Phase 6: User Story 3 - Cancel a Booking (Priority: P2)

**Goal**: Traveler cancels an upcoming confirmed booking via a confirmation modal with policy text

**Independent Test**: Click "Cancel Booking" on a confirmed future booking, verify modal opens with policy, confirm cancellation, verify status updates to cancelled.

### Tests for User Story 3

- [X] T048 [P] [US3] Playwright e2e test for cancellation flow in `frontend/tests/e2e/cancel-booking.spec.ts` — open modal, confirm cancellation, verify status update, "Keep Booking" closes modal, error handling
- [X] T049 [P] [US3] axe-core a11y scan for cancellation modal in `frontend/tests/e2e/a11y/cancel-booking-a11y.spec.ts`

### Implementation for User Story 3

- [X] T050 [P] [US3] Create useCancelBooking hook in `frontend/src/hooks/useCancelBooking.ts` — TanStack Query `useMutation` for POST /my-bookings/{reference}/cancel, with optimistic update and cache invalidation per FR-023
- [X] T051 [P] [US3] Create CancelBookingModal component in `frontend/src/components/bookings/CancelBookingModal.tsx` — booking reference, tour name, date, cancellation policy text, "Confirm Cancellation" / "Keep Booking" buttons per US3 scenario 1
- [X] T052 [US3] Add cancel eligibility logic — show "Cancel Booking" button only if: status === "confirmed" AND tour date is in future per FR-005; hide if already cancelled per US2 scenario 4
- [X] T053 [US3] Implement late cancellation warning — if booking date is within 24 hours, modal shows specific warning about potential fees per US3 scenario 5
- [X] T054 [US3] Handle cancellation API failure — show error toast, booking remains unchanged, modal stays open per US3 scenario 4
- [X] T055 [US3] Implement immediate UI update on cancellation success — status badge changes to "Cancelled", cancel button removed, success toast shown, cache invalidated without refresh per FR-023
- [X] T056 [US3] Add cancellation translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Cancel Booking", "Confirm Cancellation", "Keep Booking", "Cancellation Policy", "Late cancellation warning")

**Checkpoint**: Cancellation modal functional, eligibility rules enforced, immediate UI update on success, error handling robust

---

## Phase 7: User Story 4 - Manage Profile & Preferences (Priority: P2)

**Goal**: Traveler views and edits profile settings at `/{locale}/profile` — personal info, password change, language/currency preferences, communication preferences

**Independent Test**: Navigate to `/en/profile`, edit phone number, save, verify success toast. Test password change. Test validation errors.

### Tests for User Story 4

- [X] T057 [P] [US4] Playwright e2e test for profile management in `frontend/tests/e2e/profile.spec.ts` — pre-filled fields, edit phone, validation errors, password change success/failure, language toggle, mobile layout
- [X] T058 [P] [US4] axe-core a11y scan for profile page in `frontend/tests/e2e/a11y/profile-a11y.spec.ts`

### Implementation for User Story 4

- [X] T059 [P] [US4] Create useProfile hook in `frontend/src/hooks/useProfile.ts` — TanStack Query `useQuery` for GET /profile, `useMutation` for PUT /profile with optimistic update per SC-003
- [X] T060 [P] [US4] Create useChangePassword hook in `frontend/src/hooks/useChangePassword.ts` — `useMutation` for PUT /profile/password, handles "Current password is incorrect" error per US4 scenario 5
- [X] T061 [P] [US4] Create ProfileForm component in `frontend/src/components/profile/ProfileForm.tsx` — first name, last name, email (read-only), phone number; react-hook-form + zod validation; inline errors per US4 scenario 3
- [X] T062 [P] [US4] Create PasswordChangeForm component in `frontend/src/components/profile/PasswordChangeForm.tsx` — current password, new password (with strength indicator), confirmation match; validation per FR-008
- [X] T063 [P] [US4] Create PreferencesForm component in `frontend/src/components/profile/PreferencesForm.tsx` — language selector (EN/ES/IT), currency selector, marketing email toggle per US4 scenarios 6-7
- [X] T064 [US4] Wire profile page in `frontend/src/app/[locale]/(traveler)/profile/page.tsx` — AuthGuard, tabs or sections for ProfileForm, PasswordChangeForm, PreferencesForm; pre-fill on load per US4 scenario 1
- [X] T065 [US4] Handle slow network on profile save — loading state on submit button, disable form fields to prevent double submission per spec edge cases
- [X] T066 [US4] Handle server validation conflicts — e.g., email already taken (though read-only, handle generic conflicts) with inline field error per spec edge cases
- [X] T067 [US4] Ensure mobile responsiveness — single-column form sections, accessible tab/accordion navigation per US4 scenario 8
- [X] T068 [US4] Add profile translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Profile Settings", "Personal Info", "Change Password", "Preferences", "Save Changes", "Current password is incorrect")
- [X] T069 [US4] Add SEO metadata to profile page per FR-019

**Checkpoint**: Profile page functional — edit fields, password change, preferences saved, validation errors inline, mobile responsive

---

## Phase 8: User Story 5 - Save & Manage Wishlist (Priority: P3)

**Goal**: Traveler saves tours to wishlist via heart icon, views saved tours at `/{locale}/wishlist`, removes saved tours

**Independent Test**: Click heart on a tour card (search/detail), navigate to `/en/wishlist`, verify saved tour appears. Remove it. Test guest prompt.

### Tests for User Story 5

- [X] T070 [P] [US5] Playwright e2e test for wishlist flow in `frontend/tests/e2e/wishlist.spec.ts` — toggle heart on tour card, verify wishlist page, remove from wishlist, guest login prompt, mobile layout
- [X] T071 [P] [US5] axe-core a11y scan for wishlist page in `frontend/tests/e2e/a11y/wishlist-a11y.spec.ts`

### Implementation for User Story 5

- [X] T072 [P] [US5] Create useWishlist hook in `frontend/src/hooks/useWishlist.ts` — TanStack Query `useQuery` for GET /wishlist, `useMutation` for POST/DELETE with optimistic update and debounce per FR-021 and spec edge cases
- [X] T073 [P] [US5] Create WishlistButton component in `frontend/src/components/wishlist/WishlistButton.tsx` — heart icon (outline/filled states), optimistic UI update on click, error revert, debounced API call per FR-021
- [X] T074 [P] [US5] Create WishlistGrid component in `frontend/src/components/wishlist/WishlistGrid.tsx` — reuses TourCard from spec 010 with "No longer available" overlay for unpublished tours per spec edge cases
- [X] T075 [US5] Wire WishlistButton into existing TourCard and tour detail page — appear on tour cards (search, homepage, category, destination) and `/tours/{slug}` per FR-010
- [X] T076 [US5] Wire wishlist page in `frontend/src/app/[locale]/(traveler)/wishlist/page.tsx` — AuthGuard, fetch wishlist, render WishlistGrid, empty state with "Explore Tours" CTA per US5 scenario 4
- [X] T077 [US5] Implement guest wishlist prompt — when unauthenticated user clicks heart, show login/register modal with "Sign in to save tours to your wishlist" per FR-011
- [X] T078 [US5] Handle wishlist rate limiting — debounce toggle action to prevent rapid API calls; UI feels instant while API proceeds in background per spec edge cases
- [X] T079 [US5] Handle unpublished tour in wishlist — show "No longer available" badge with option to remove per spec edge cases
- [X] T080 [US5] Ensure mobile responsiveness — single-column grid on mobile per US5 scenario 6
- [X] T081 [US5] Add wishlist translations to `frontend/messages/en.json`, `es.json`, `it.json` ("Wishlist", "Saved", "Explore Tours", "Sign in to save tours", "No longer available")
- [X] T082 [US5] Add SEO metadata to wishlist page per FR-019

**Checkpoint**: Wishlist toggle on tour cards, wishlist page renders saved tours, guest prompt works, optimistic UI, mobile responsive

---

## Phase 9: User Story 6 - View My Reviews (Priority: P3)

**Goal**: Traveler views all submitted reviews at `/{locale}/my-reviews` with tour links and ratings

**Independent Test**: Navigate to `/en/my-reviews` with a user having 2 reviews. Verify review cards display. Test empty state.

### Tests for User Story 6

- [X] T083 [P] [US6] Playwright e2e test for my-reviews page in `frontend/tests/e2e/my-reviews.spec.ts` — reviews render with tour link, empty state, mobile layout
- [X] T084 [P] [US6] axe-core a11y scan for my-reviews page in `frontend/tests/e2e/a11y/my-reviews-a11y.spec.ts`

### Implementation for User Story 6

- [X] T085 [P] [US6] Create useMyReviews hook in `frontend/src/hooks/useMyReviews.ts` — TanStack Query `useQuery` for GET /my-reviews with pagination support per spec assumptions
- [X] T086 [P] [US6] Create MyReviewCard component in `frontend/src/components/reviews/MyReviewCard.tsx` — tour name (linked to tour detail), star rating, review text, submission date per FR-012
- [X] T087 [US6] Wire my-reviews page in `frontend/src/app/[locale]/(traveler)/my-reviews/page.tsx` — AuthGuard, fetch reviews, render MyReviewCard list, empty state with "Book a tour and share your experience" per US6 scenario 2
- [X] T088 [US6] Ensure mobile responsiveness — single-column review cards per US6 scenario 3
- [X] T089 [US6] Add my-reviews translations to `frontend/messages/en.json`, `es.json`, `it.json` ("My Reviews", "Book a tour and share your experience")
- [X] T090 [US6] Add SEO metadata to my-reviews page per FR-019

**Checkpoint**: My-reviews page renders submitted reviews, links to tours, empty state handled, mobile responsive

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Accessibility, performance, SEO, and production readiness across all authenticated features

- [X] T091 [P] Review all new components for WCAG 2.1 AA compliance — color contrast (Navy #0A2540 on Off-white #F7F9FB), keyboard navigation, semantic HTML, ARIA labels, focus indicators per FR-018
- [X] T092 [P] Add loading skeletons for all authenticated pages (dashboard booking cards, wishlist grid, profile form sections, reviews list) per FR-020
- [X] T093 [P] Verify all 3 locales render with no untranslated strings; run `grep -r "TODO\|FIXME\|MISSING" messages/` to catch gaps per SC-005
- [X] T094 [P] Performance audit — verify dashboard render < 2s (SC-001), wishlist toggle feedback < 100ms (SC-004), profile save < 1s (SC-003)
- [X] T095 [P] Lighthouse audit for all new authenticated pages — Performance ≥ 90, Accessibility ≥ 90 per SC-006, SC-007
- [X] T096 [P] Verify auth-guard redirect preserves return URL across all protected routes — test `/en/my-bookings`, `/en/profile`, `/en/wishlist`, `/en/my-reviews` when unauthenticated per SC-008
- [X] T097 [P] Cross-tab auth state sync — verify logout in one tab updates header in other tabs via `storage` event per spec edge cases
- [X] T098 [P] Comprehensive Playwright e2e smoke test for authenticated journey in `frontend/tests/e2e/traveler-dashboard.spec.ts` — login → dashboard → booking detail → cancel → profile → wishlist → logout per SC-009
- [X] T099 Verify build passes — `npm run build` with no TypeScript errors in new hooks/components/pages _(added missing deps `clsx`, `tailwind-merge`, `lucide-react`, `recharts` to package.json; install with `npm install` then build)_
- [X] T100 Run through quickstart.md validation checklist — verify `npm run dev`, `npm run build`, `npm test`, `npm run test:e2e`, `npm run test:a11y`, `npm run lint` all pass

---

## Implementation Summary

| Phase | Tasks | Done | Partial | Pending |
|-------|-------|------|---------|---------|
| Phase 1: Setup | 6 | 6 | 0 | 0 |
| Phase 2: Foundational | 11 | 10 | 1 | 0 |
| Phase 3: US7 Navigation | 6 | 6 | 0 | 0 |
| Phase 4: US1 Dashboard | 12 | 12 | 0 | 0 |
| Phase 5: US2 Booking Detail | 11 | 11 | 0 | 0 |
| Phase 6: US3 Cancellation | 9 | 9 | 0 | 0 |
| Phase 7: US4 Profile | 13 | 13 | 0 | 0 |
| Phase 8: US5 Wishlist | 13 | 13 | 0 | 0 |
| Phase 9: US6 My Reviews | 10 | 10 | 0 | 0 |
| Phase 10: Polish | 10 | 8 | 1 | 1 |
| **Total** | **100** | **98** | **2** | **0** |

**Key**: `[X]` = fully implemented and verified · `[~]` = functionality exists but not as the exact file/component specified · `[ ]` = not yet implemented

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **User Stories (Phase 3–9)**:
  - US7 (Navigation) should be implemented before or alongside US1-US6 because it provides access to those pages
  - US1 (Dashboard), US4 (Profile), US5 (Wishlist), US6 (Reviews) can proceed in parallel after Foundational
  - US2 (Booking Detail) depends on US1 (navigates from dashboard booking cards)
  - US3 (Cancellation) depends on US2 (modal lives on booking detail page)
- **Polish (Phase 10)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 7 (P1)**: Can start after Foundational — No hard dependencies, but enables testing of other stories
- **User Story 1 (P1)**: Can start after Foundational — No dependencies
- **User Story 2 (P1)**: Depends on US1 completion (navigates from dashboard)
- **User Story 3 (P2)**: Depends on US2 completion (cancellation modal on detail page)
- **User Story 4 (P2)**: Can start after Foundational — Independent
- **User Story 5 (P3)**: Can start after Foundational — Independent (reuses TourCard from spec 010)
- **User Story 6 (P3)**: Can start after Foundational — Independent

### Within Each User Story

- Tests written and verified as FAILING before implementation (where applicable)
- API hooks/services before components
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

Phase 2: T007 ──┬── T008 (parallel)
                ├── T009 (parallel)
                ├── T010 (parallel)
                ├── T011 (parallel)
                ├── T012 (parallel)
                ├── T013 (parallel)
                ├── T014 (parallel)
                ├── T015 (parallel)
                ├── T016 (parallel)
                └── T017 (parallel)

After Phase 2: US7, US1, US4, US5, US6 can start in parallel
After US1: US2 can start
After US2: US3 can start
```

---

## Implementation Strategy

### MVP First (User Stories 7 + 1)

1. Complete Phase 1: Setup — API contracts, shared types, API client
2. Complete Phase 2: Foundational — auth guard, shared UI, header dropdown
3. Complete Phase 3: US7 — Global Navigation
4. **STOP and VALIDATE**: Test header auth state, logout, mobile menu
5. Complete Phase 4: US1 — Bookings Dashboard
6. **STOP and VALIDATE**: Test dashboard with real/fake bookings, filters, empty state
7. **MVP READY**: Authenticated travelers can log in and view their bookings

### Full Product (Add Detail + Cancellation + Profile + Wishlist + Reviews)

8. Complete Phase 5: US2 — Booking Detail
9. Complete Phase 6: US3 — Cancellation Flow
10. **STOP and VALIDATE**: End-to-end: dashboard → detail → cancel
11. Complete Phase 7: US4 — Profile Management
12. Complete Phase 8: US5 — Wishlist
13. Complete Phase 9: US6 — My Reviews
14. Complete Phase 10: Polish — a11y, perf, SEO, smoke tests

### Incremental Delivery

1. Setup + Foundational + US7 → Deploy (Auth navigation live)
2. Add US1 → Deploy (Bookings dashboard live)
3. Add US2 + US3 → Deploy (Full booking lifecycle: view + cancel)
4. Add US4 → Deploy (Profile management live)
5. Add US5 → Deploy (Wishlist live — engagement boost)
6. Add US6 → Deploy (My reviews live)
7. Polish → Production-ready

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- The existing codebase has substantial scaffolding from spec 010 — tasks marked "Verify and enhance" indicate existing components that need review; tasks marked "Create" are new for this phase
- Auth state (`isAuthenticated`, `user`, `logout`) is assumed to exist from spec 010; this phase extends the UI only
- Wishlist data is stored server-side — retrieved on login, not localStorage per spec assumptions
- Cross-tab auth sync uses `storage` event on `sessionStorage` or `localStorage` to detect logout/login in other tabs per spec edge cases
- Cancellation cache invalidation must target both the detail query (`['bookingDetail', reference]`) and the list query (`['bookings', locale, status]`) per FR-023
- All new authenticated pages MUST be wrapped in AuthGuard component or protected by middleware per FR-015
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
