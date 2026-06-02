# User Stories: Traveler Dashboard, Bookings, Profile & Wishlists

**Feature Branch**: `011-tour-management`
**Created**: 2026-05-20
**Status**: Draft
**Constitution**: v1.0.1
**Input**: User description: "Tour Management — traveler dashboard, bookings management, profile settings, wishlists, and authenticated user experience"

## User Scenarios & Testing _(mandatory)_

### User Story 1 - View My Bookings Dashboard (Priority: P1)

A traveler logs into their account and navigates to their dashboard. They see an overview of upcoming tours, a summary count of bookings by status, recent booking activity, and quick actions to manage their trips.

**Why this priority**: After booking, travelers need a central place to see their trips. The dashboard is the primary post-login landing page and the gateway to all traveler features.

**Independent Test**: Can be fully tested by logging in and navigating to `/{locale}/my-bookings` — verify upcoming bookings display, status filters work, empty state renders.

**Acceptance Scenarios**:

1. **Given** an authenticated traveler with 3 bookings (1 upcoming, 1 completed, 1 cancelled), **When** they visit `/en/my-bookings`, **Then** they see all 3 bookings displayed as cards with: tour name, booking reference, date, status badge, and price.
2. **Given** an authenticated traveler with no bookings, **When** they visit `/en/my-bookings`, **Then** they see an inviting empty state with a "Browse Tours" CTA that links to `/en/tours`.
3. **Given** a traveler is viewing their bookings, **When** they click the "Confirmed" filter tab, **Then** only confirmed bookings are shown and the URL updates to reflect the active filter.
4. **Given** a traveler is on the dashboard on mobile (≤ 780px), **When** the page renders, **Then** booking cards stack in a single column with horizontal-scrollable filter tabs.
5. **Given** an unauthenticated visitor navigates to `/en/my-bookings`, **When** the page loads, **Then** they are redirected to `/en/auth/login` with a return URL preserved.
6. **Given** the API request fails, **When** the dashboard loads, **Then** an error state with a "Try Again" button is displayed.

---

### User Story 2 - View Booking Detail & Receipt (Priority: P1)

A traveler clicks on a booking card and sees the full booking details: comprehensive trip information (tour name, date, time, participants, meeting point), payment receipt (amount, payment method, transaction date), booking status timeline, and cancellation options.

**Why this priority**: Travelers need detailed booking confirmation and receipt access before their trip. This is a critical post-booking touchpoint.

**Independent Test**: Can be tested by navigating to `/{locale}/my-bookings/{reference}` and verifying all detail sections render.

**Acceptance Scenarios**:

1. **Given** a traveler clicks a confirmed booking, **When** the detail page loads, **Then** they see: booking reference, status badge, tour name with link, date/time, number of participants, price per person, total amount, meeting point, inclusions, and special requests.
2. **Given** the booking has a payment record, **When** the traveler views the detail, **Then** they see payment status, amount, transaction date, and payment method (last 4 digits of card).
3. **Given** the booking is confirmed and the tour date is in the future, **When** the traveler views the detail, **Then** a "Cancel Booking" button is visible with clear cancellation policy text.
4. **Given** the booking is already cancelled, **When** the traveler views the detail, **Then** the status shows "Cancelled" with the cancellation date and the cancel button is hidden.
5. **Given** the page is viewed on mobile, **When** the layout renders, **Then** all detail sections are readable in a single column with the action button in a sticky footer.

---

### User Story 3 - Cancel a Booking (Priority: P2)

A traveler cancels an upcoming confirmed booking from their dashboard or booking detail page. They confirm the cancellation through a modal, see the cancellation policy, and receive confirmation of the cancellation.

**Why this priority**: Cancellation is a core booking lifecycle operation. It's P2 because bookings can also be managed through customer support, but self-service is expected.

**Independent Test**: Can be tested by clicking "Cancel Booking" on a confirmed booking, confirming in the modal, and verifying the status updates to "cancelled".

**Acceptance Scenarios**:

1. **Given** a traveler views a confirmed booking with a future date, **When** they click "Cancel Booking", **Then** a confirmation modal appears showing the booking reference, tour name, date, and cancellation policy text.
2. **Given** the cancellation modal is open, **When** the traveler clicks "Confirm Cancellation", **Then** the booking status updates to "cancelled", a success message appears, and the cancel button is removed.
3. **Given** the cancellation modal is open, **When** the traveler clicks "Keep Booking", **Then** the modal closes and no changes are made.
4. **Given** the cancellation API fails, **When** the traveler confirms cancellation, **Then** an error message is displayed and the booking remains unchanged.
5. **Given** the booking date is within 24 hours, **When** the traveler clicks "Cancel Booking", **Then** the modal shows a specific warning that late cancellation may incur fees per the tour's policy.

---

### User Story 4 - Manage Profile & Preferences (Priority: P2)

A traveler navigates to their profile settings page to view and edit their personal information (name, email, phone), change their password, set their preferred language and currency, and manage communication preferences.

**Why this priority**: Profile management is expected for any authenticated experience. It's P2 because it doesn't block the core booking flow, but is necessary for account ownership and personalization.

**Independent Test**: Can be tested by navigating to `/{locale}/profile`, editing fields, saving, and verifying changes persist.

**Acceptance Scenarios**:

1. **Given** an authenticated traveler navigates to `/en/profile`, **When** the page loads, **Then** they see pre-filled fields: first name, last name, email (read-only), and phone number.
2. **Given** a traveler updates their phone number and clicks "Save Changes", **When** the API responds successfully, **Then** a success toast appears and the updated value is reflected in the form.
3. **Given** a traveler enters an invalid phone number format, **When** they submit, **Then** inline validation errors appear and the form is not submitted.
4. **Given** a traveler navigates to the "Change Password" section, **When** they enter their current password, new password, and confirmation, **Then** the password is updated and a success message is shown.
5. **Given** a traveler enters an incorrect current password, **When** they submit the password change form, **Then** an error "Current password is incorrect" is displayed.
6. **Given** a traveler selects a preferred language from the settings, **When** they save, **Then** the site language updates and subsequent pages render in the chosen language.
7. **Given** a traveler toggles off marketing emails, **When** they save preferences, **Then** the preference is stored and the toggle reflects the saved state.
8. **Given** the profile page loads on mobile, **When** rendered, **Then** all form sections are accessible in a single-column layout.

---

### User Story 5 - Save & Manage Wishlist (Priority: P3)

A traveler browses tours and saves interesting ones to their wishlist via a heart/bookmark button on tour cards and detail pages. They can view and manage their wishlist from the dashboard.

**Why this priority**: Wishlists enhance user engagement and provide data for retargeting. P3 because they are additive — the booking flow works without them.

**Independent Test**: Can be tested by clicking the heart icon on a tour card, navigating to `/{locale}/wishlist`, and verifying the saved tour appears.

**Acceptance Scenarios**:

1. **Given** an authenticated traveler views a tour card or detail page, **When** they click the heart/bookmark icon, **Then** the icon fills/states "saved" and the tour is added to their wishlist.
2. **Given** an authenticated traveler views a tour already in their wishlist, **When** they click the filled heart icon, **Then** the icon returns to its outline state and the tour is removed from the wishlist.
3. **Given** an authenticated traveler navigates to `/en/wishlist`, **When** the page loads, **Then** they see a grid of saved tour cards with the same card format as the search listing.
4. **Given** a traveler with an empty wishlist visits the wishlist page, **When** the page loads, **Then** they see an empty state with "Explore Tours" CTA.
5. **Given** a guest traveler clicks the heart icon on a tour card, **When** they are not authenticated, **Then** they are prompted to log in or register with a modal message: "Sign in to save tours to your wishlist."
6. **Given** the wishlist page is viewed on mobile, **When** rendered, **Then** saved tours display in a single-column layout.

---

### User Story 6 - View My Reviews (Priority: P3)

A traveler views all reviews they've submitted, sees their rating and content for each, and can navigate to the associated tour page.

**Why this priority**: Review management provides transparency and encourages engagement. P3 because reviews can also be viewed on the tour pages themselves.

**Independent Test**: Can be tested by navigating to `/{locale}/my-reviews` and verifying previously submitted reviews display.

**Acceptance Scenarios**:

1. **Given** an authenticated traveler who has submitted 2 reviews, **When** they visit `/en/my-reviews`, **Then** they see both reviews with: tour name, star rating, review text, submission date, and a link to the tour detail page.
2. **Given** a traveler who has not submitted any reviews, **When** they visit the my-reviews page, **Then** they see an empty state with "Book a tour and share your experience" messaging.
3. **Given** a traveler views their reviews on mobile, **When** rendered, **Then** review cards stack in a single column.

---

### User Story 7 - Global Navigation for Authenticated Users (Priority: P1)

The site header adapts for authenticated travelers: it shows the traveler's name/avatar, a dropdown menu with links to Dashboard, My Bookings, Wishlist, My Reviews, Profile, and Logout. The header reflects authentication state across all pages.

**Why this priority**: Navigation is the backbone of the authenticated experience. Without it, travelers cannot discover or access the new features.

**Independent Test**: Can be tested by logging in and verifying the header changes from "Login/Register" to the user menu across multiple pages.

**Acceptance Scenarios**:

1. **Given** a traveler is authenticated, **When** they view any page, **Then** the header shows their first name and a dropdown menu with: Dashboard, My Bookings, Wishlist, My Reviews, Profile Settings, and Logout.
2. **Given** a traveler clicks "Logout" from the user menu, **When** the action completes, **Then** they are redirected to the homepage and the header reverts to "Login/Register" links.
3. **Given** a traveler is on mobile, **When** they tap the user menu, **Then** it opens as a slide-out panel or dropdown with all navigation items accessible.
4. **Given** a traveler's session expires, **When** they click a protected navigation item, **Then** they are redirected to login with the intended destination preserved.

---

### Edge Cases

- What happens when a traveler bookmarks a tour that is later unpublished? → Wishlist item shows "No longer available" with an option to remove it.
- What happens when a booking is cancelled but the traveler still sees it cached? → Data fetching must ensure freshness through appropriate cache invalidation; UI updates immediately on cancellation without requiring manual refresh.
- What happens when the traveler tries to access a booking detail not belonging to them? → API returns 404 which the frontend shows as "Booking not found".
- What happens when profile update conflicts with server state (e.g., email already taken by another account)? → Show the specific server validation error inline on the affected field.
- What happens during slow network on profile save? → Show loading state on the submit button; disable form fields to prevent double submission.
- What happens when the traveler opens multiple tabs with different auth states? → Use sessionStorage events to detect cross-tab auth state changes and force a re-render.
- How does the wishlist handle rate limiting on rapid toggle? → Debounce the toggle action to prevent rapid API calls; the UI should feel instant (optimistic update) while the API call proceeds in the background.

## Requirements _(mandatory)_

### Functional Requirements

- **FR-001**: The dashboard page MUST exist at `/{locale}/my-bookings` and display all bookings for the authenticated traveler with status filter tabs (All, Confirmed, Completed, Cancelled).
- **FR-002**: Booking cards on the dashboard MUST display: tour cover image, tour name, booking reference, booking date, status badge, and total price.
- **FR-003**: The booking detail page MUST exist at `/{locale}/my-bookings/{reference}` showing: full booking info, payment receipt, status timeline, and contextual actions (cancel if eligible).
- **FR-004**: The cancellation flow MUST present a confirmation modal with cancellation policy text before executing the cancellation API call.
- **FR-005**: Cancellation eligibility MUST be determined by tour date (future only) and booking status (confirmed only).
- **FR-006**: The profile settings page MUST exist at `/{locale}/profile` with sections for personal info (name, phone), password change, language preference, and communication preferences.
- **FR-007**: Profile forms MUST pre-fill with existing traveler data on load and validate inputs client-side before submission.
- **FR-008**: The password change form MUST require current password, new password (with strength requirements), and confirmation match.
- **FR-009**: The wishlist page MUST exist at `/{locale}/wishlist` showing saved tours in a grid format consistent with the tour listing cards (cover image, title, price, rating, location, duration).
- **FR-010**: The wishlist toggle (heart icon) MUST be present on tour cards (search, homepage, category, destination) and the tour detail page.
- **FR-011**: Wishlist toggle MUST prompt unauthenticated users to log in or register via a modal.
- **FR-012**: The my-reviews page MUST exist at `/{locale}/my-reviews` listing all reviews submitted by the traveler with: tour name (linked), star rating, review text, and date.
- **FR-013**: The global header MUST display an authenticated user menu (name + dropdown) replacing the "Login/Register" links when a valid session exists.
- **FR-014**: The user dropdown menu MUST include links to: Dashboard, My Bookings, Wishlist, My Reviews, Profile Settings, and Logout.
- **FR-015**: All authenticated pages MUST be protected via AuthGuard — unauthenticated access redirects to login with return URL preserved.
- **FR-016**: All pages MUST support three locales (EN, ES, IT) with full translation coverage.
- **FR-017**: All pages MUST be fully responsive with mobile-first design, tested at 390px and 780px breakpoints.
- **FR-018**: All pages MUST meet WCAG 2.1 AA accessibility standards (color contrast, keyboard navigation, semantic HTML, screen reader support).
- **FR-019**: All pages MUST render with appropriate SEO metadata (title, description) per locale.
- **FR-020**: The dashboard MUST fetch booking data efficiently with loading, error, and empty state handling; previously loaded data MUST be cached during the session to avoid unnecessary refetching.
- **FR-021**: Wishlist toggle MUST provide immediate visual feedback — the heart icon changes instantly on click; if the operation fails, the icon reverts and an error message is displayed.
- **FR-022**: All API client calls in authenticated contexts MUST include valid authentication credentials to identify and authorize the traveler.
- **FR-023**: Booking cancellation in the dashboard list MUST immediately reflect the new status without requiring a manual page refresh by the traveler.

### Key Entities

- **Booking Card**: Compact visual summary of a traveler's booking — tour image, name, reference, date, status badge, price. Used in the dashboard list.
- **Booking Detail**: Full booking information including participants, payment receipt, meeting point, inclusions, special requests, status timeline, and available actions.
- **Traveler Profile**: Personal account information including name, email (read-only), phone, preferred language, marketing email preference, and password credentials.
- **Wishlist Item**: A saved reference linking a traveler to a tour, displayed as a tour card. Persisted server-side and retrieved on login.
- **Review Record**: A traveler's submitted review — star rating, text content, tour reference, and submission date.
- **User Menu Dropdown**: Navigation component appearing in the global header when authenticated, providing access to all traveler-specific pages.

## Success Criteria _(mandatory)_

### Measurable Outcomes

- **SC-001**: An authenticated traveler can view their bookings dashboard within 2 seconds of navigation.
- **SC-002**: Booking cancellation, from click to confirmed status update, completes in under 3 seconds.
- **SC-003**: Profile changes (save) reflect in the UI within 1 second of server confirmation.
- **SC-004**: Wishlist toggle (heart icon) provides visual feedback within 100ms of click (optimistic update).
- **SC-005**: All 3 locales (EN, ES, IT) render correct translations across all new pages with no untranslated strings.
- **SC-006**: All new pages achieve a Lighthouse Performance score ≥ 90.
- **SC-007**: All new pages pass automated WCAG 2.1 AA accessibility audit (Lighthouse accessibility score ≥ 90).
- **SC-008**: 100% of authenticated routes redirect unauthenticated visitors to login with return URL preserved.
- **SC-009**: Travelers can navigate from dashboard to booking detail, cancel a booking, and return to the dashboard in under 30 seconds.
- **SC-010**: All new pages render correctly at 390px (mobile) and 1280px (desktop) without horizontal scrolling or layout breakage.

## Assumptions

- The Laravel backend APIs for authenticated traveler endpoints (my-bookings, profile, wishlist, my-reviews, password change) are operational or will be developed concurrently.
- The existing auth system (token-based authentication, login/register pages from spec 004 and 010) handles authentication state; this spec extends the UI for authenticated features only.
- The existing design system tokens (Navy #0A2540, Gold #FFB800, Inter typography, 8px grid, 12px radius) apply to all new components.
- The existing Stitch design system has screens or patterns for the dashboard, profile, and wishlist pages that serve as UI reference.
- The data fetching utilities, state management, and API client are already configured in the frontend project from Phase 1 (spec 010).
- The authentication state management already provides `isAuthenticated`, `user` object, and `logout` function from Phase 1 (spec 010).
- Existing tour card display patterns from spec 010 are reusable for wishlist display.
- The my-bookings page and its supporting components are partially scaffolded from spec 010 but need enhancement to meet full requirements described in this specification.
- The backend provides paginated endpoints for bookings list and reviews list.
- Wishlist data is stored server-side (not localStorage) and retrieved on login to persist across devices.
- The my-bookings route already exists with basic scaffolding from spec 010 and needs enhancement rather than creation from scratch.
