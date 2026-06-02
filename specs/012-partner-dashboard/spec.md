# User Stories: Partner Dashboard — Tour Creation, Booking Management & Partner Profile

**Feature Branch**: `012-partner-dashboard`
**Created**: 2026-05-28
**Status**: Draft
**Constitution**: v1.0.1
**Input**: User description: "Partner Dashboard — tour creation, booking management, and partner profile for marketplace partners"

## User Scenarios & Testing _(mandatory)_

### User Story 1 - Partner Dashboard Overview (Priority: P1)

A partner logs into their account and navigates to their dashboard. They see an overview of key metrics (total bookings, total revenue, average rating, upcoming bookings), a bookings-over-time chart, recent notifications, and quick actions to create tours or manage bookings.

**Why this priority**: The dashboard is the primary post-login landing page for partners and the gateway to all partner features. It provides the at-a-glance business intelligence partners need daily.

**Independent Test**: Can be fully tested by logging in as a partner and navigating to `/{locale}/partner` — verify analytics summary displays, chart renders, notifications appear.

**Acceptance Scenarios**:

1. **Given** an authenticated partner with tours and bookings, **When** they visit `/en/partner`, **Then** they see summary cards: total bookings, total revenue (formatted), average rating, and upcoming bookings count.
2. **Given** a partner has bookings over the last 30 days, **When** the dashboard loads, **Then** a bookings-over-time chart displays with date labels and booking counts.
3. **Given** a partner has 3 unread notifications, **When** the dashboard loads, **Then** a notification badge shows "3" and recent notifications are listed.
4. **Given** a newly onboarded partner with no tours, **When** they visit the dashboard, **Then** they see an onboarding CTA: "Create Your First Tour" linking to the tour creation page.
5. **Given** an unauthenticated visitor navigates to `/en/partner`, **When** the page loads, **Then** they are redirected to `/en/auth/login` with a return URL preserved.
6. **Given** a non-partner user accesses the partner dashboard, **When** the page loads, **Then** they receive a 403 Forbidden response.

---

### User Story 2 - Tour Creation & Management (Priority: P1)

A partner creates a new tour through a multi-step wizard (basic info, media upload, pricing tiers, availability rules, SEO metadata), saves drafts, and submits for admin review. They can view, edit, and archive their existing tours.

**Why this priority**: Tour creation is the core value proposition for partners — without tours, there is no marketplace content. Multi-step wizard ensures data quality.

**Independent Test**: Can be tested by navigating to `/{locale}/partner/tours/create`, completing the wizard, submitting for review, and verifying the tour appears in the tours list with "pending_review" status.

**Acceptance Scenarios**:

1. **Given** a partner navigates to `/en/partner/tours/create`, **When** the page loads, **Then** they see a multi-step wizard with steps: Basic Info, Media, Pricing, Availability, and SEO.
2. **Given** a partner fills in the Basic Info step (title, description, category, destination, location, duration, meeting point, highlights, inclusions, exclusions, cancellation policy, languages), **When** they click "Next", **Then** the form validates required fields and advances to the Media step.
3. **Given** a partner uploads tour media (images), **When** they upload files, **Then** files are uploaded via signed URL, thumbnails are generated, and they can set a cover image and reorder images via drag-and-drop.
4. **Given** a partner configures pricing tiers, **When** they add a tier, **Then** they specify name, price, currency, min/max participants per tier.
5. **Given** a partner sets availability rules, **When** they add a rule, **Then** they configure frequency (daily/weekly/monthly/once), days of week, start/end time, capacity, and date range. They can also add availability exceptions (blackout dates, special dates with overrides).
6. **Given** a partner completes all steps and clicks "Submit for Review", **When** the submission succeeds, **Then** the tour status changes to "pending_review" and a confirmation message is shown.
7. **Given** a partner has not completed all required fields, **When** they click "Submit for Review", **Then** validation errors highlight the incomplete steps.
8. **Given** a partner wants to save progress, **When** they click "Save Draft" at any step, **Then** the draft is saved and they can resume later from `/en/partner/tours/drafts`.
9. **Given** a partner views their tours list at `/en/partner/tours`, **When** the page loads, **Then** they see tours with status badges (draft, pending_review, published, rejected, archived), pagination, and status filter tabs.
10. **Given** a partner's tour is rejected, **When** they view the tour, **Then** they see the admin rejection reason and can edit and resubmit.

---

### User Story 3 - Booking Management (Priority: P1)

A partner views all bookings for their tours, filters by status/tour/date, views booking details including traveler information, and can mark bookings as completed or request cancellation with evidence.

**Why this priority**: Booking management is essential for day-to-day operations. Partners need to track who is coming, when, and manage booking lifecycle.

**Independent Test**: Can be tested by navigating to `/{locale}/partner/bookings`, viewing bookings, filtering by status, and marking a booking as completed.

**Acceptance Scenarios**:

1. **Given** a partner has bookings across multiple tours, **When** they visit `/en/partner/bookings`, **Then** they see a paginated list of bookings with: reference, tour name, traveler name, date, status badge, and total amount.
2. **Given** a partner clicks on a booking, **When** the detail view loads, **Then** they see: booking reference, status, tour details (title, slug, cover image), traveler info (name, email, phone), booking date/time, participant breakdown by tier, total amount, currency, special requests, and payment status.
3. **Given** a partner filters bookings by "confirmed" status, **When** the filter is applied, **Then** only confirmed bookings are displayed.
4. **Given** a partner filters by date range, **When** the filter is applied, **Then** only bookings within the specified dates are shown.
5. **Given** a partner filters by a specific tour, **When** the filter is applied, **Then** only bookings for that tour are displayed.
6. **Given** a partner has a confirmed booking for a completed tour, **When** they click "Mark as Completed", **Then** the booking status updates to "completed".
7. **Given** a partner needs to cancel a booking, **When** they click "Request Cancellation", **Then** a modal appears requiring a cancellation reason and optional evidence upload. On submit, the booking status changes to "cancellation_requested".
8. **Given** a partner searches bookings by reference or traveler name, **When** they type in the search field, **Then** results are filtered in real-time.

---

### User Story 4 - Review Management (Priority: P2)

A partner views all reviews received for their tours, reads traveler feedback, and can respond to reviews. They can also edit their existing responses.

**Why this priority**: Review responses build trust with potential travelers. Partners need to manage their reputation actively.

**Independent Test**: Can be tested by navigating to `/{locale}/partner/reviews`, reading reviews, and submitting a response.

**Acceptance Scenarios**:

1. **Given** a partner has received reviews, **When** they visit `/en/partner/reviews`, **Then** they see a paginated list of reviews with: tour name, traveler name, star rating, review text, submission date, and booking reference.
2. **Given** a review has no response, **When** the partner clicks "Respond", **Then** a text area appears for them to write a response.
3. **Given** a partner submits a response, **When** the API succeeds, **Then** the response is saved and displayed below the review.
4. **Given** a review has an existing response, **When** the partner clicks "Edit Response", **Then** the response text is editable and can be updated.
5. **Given** the reviews page is viewed on mobile, **When** rendered, **Then** review cards stack in a single column.

---

### User Story 5 - Partner Profile & Settings (Priority: P2)

A partner manages their business profile (company name, description, contact info, address, logo, tax ID), configures notification preferences, and manages payout information (bank details).

**Why this priority**: Profile completeness builds trust with travelers. Settings control operational notifications and financial payouts.

**Independent Test**: Can be tested by navigating to `/{locale}/partner/profile`, editing fields, saving, and verifying changes persist.

**Acceptance Scenarios**:

1. **Given** a partner navigates to `/en/partner/profile`, **When** the page loads, **Then** they see pre-filled fields: company name, description, email, phone, website, logo, address (street, city, state, postal code, country), tax ID, preferred language, and preferred currency.
2. **Given** a partner updates their company name and clicks "Save", **When** the API responds successfully, **Then** a success toast appears and the updated value is reflected.
3. **Given** a partner uploads a new logo, **When** the upload succeeds, **Then** the logo preview updates and the URL is saved.
4. **Given** a partner navigates to the Settings tab, **When** the page loads, **Then** they see notification toggles: new booking alert, cancellation alert, daily summary email, review received alert, tour status change alert.
5. **Given** a partner toggles off "Daily Summary Email", **When** they save settings, **Then** the preference is persisted and reflected on reload.
6. **Given** a partner navigates to the Payout section, **When** the page loads, **Then** they see fields for: account holder name, bank name, account number, IBAN, SWIFT/BIC, and country.
7. **Given** a partner enters an invalid IBAN, **When** they submit, **Then** inline validation errors appear.

---

### User Story 6 - Partner Analytics (Priority: P2)

A partner views detailed analytics for their business: total bookings, revenue, conversion rate, average rating, and a bookings-over-time chart. They can filter by date range and specific tour.

**Why this priority**: Analytics help partners make data-driven decisions about pricing, availability, and tour improvements.

**Independent Test**: Can be tested by navigating to `/{locale}/partner/analytics`, selecting a date range, and verifying the metrics update.

**Acceptance Scenarios**:

1. **Given** a partner navigates to `/en/partner/analytics`, **When** the page loads, **Then** they see summary cards: total bookings, total revenue (formatted with currency), average rating, review count, conversion rate (%), and upcoming bookings.
2. **Given** a partner selects a custom date range, **When** the filter is applied, **Then** all metrics recalculate for the selected period.
3. **Given** a partner filters by a specific tour, **When** the filter is applied, **Then** analytics show data only for that tour.
4. **Given** a partner has bookings data, **When** the chart renders, **Then** it shows bookings count and revenue over time as a line or bar chart.

---

### User Story 7 - Partner Navigation & Layout (Priority: P1)

The partner dashboard has a dedicated layout with a sidebar navigation and header. The sidebar includes links to: Dashboard, Tours, Bookings, Reviews, Analytics, Profile, and a notification bell. The layout is responsive with a collapsible sidebar on mobile.

**Why this priority**: Navigation is the backbone of the partner experience. Without it, partners cannot discover or access features.

**Independent Test**: Can be tested by logging in as a partner and verifying sidebar navigation works across all pages, collapses on mobile, and highlights the active route.

**Acceptance Scenarios**:

1. **Given** a partner is authenticated, **When** they view any partner page, **Then** the sidebar shows navigation items: Dashboard, Tours, Bookings, Reviews, Analytics, Profile.
2. **Given** a partner is on the Tours page, **When** the sidebar renders, **Then** the "Tours" item is highlighted as active.
3. **Given** a partner is on mobile (≤ 780px), **When** the layout renders, **Then** the sidebar collapses into a hamburger menu.
4. **Given** a partner has unread notifications, **When** the header renders, **Then** a notification bell with a badge count is visible.
5. **Given** a partner clicks the notification bell, **When** the panel opens, **Then** they see recent notifications with the ability to mark as read or mark all as read.

---

### Edge Cases

- What happens when a partner uploads a file that exceeds the size limit? → The upload is rejected with a clear error message showing the max allowed size.
- What happens when a partner tries to edit a published tour? → The tour can be edited; changes create a new draft version that must be re-submitted for review.
- What happens when a partner's tour has bookings and they try to archive it? → Warning: "This tour has active bookings. Archiving will prevent new bookings but existing ones remain valid."
- What happens when a partner tries to access another partner's data? → API returns 403; frontend shows "Access Denied".
- What happens during concurrent draft saves? → Last-write-wins with `last_saved_at` timestamp conflict detection.
- What happens when availability rules overlap? → Backend validates and rejects conflicting rules; frontend shows inline errors.

## Requirements _(mandatory)_

### Functional Requirements

- **FR-001**: The partner dashboard MUST exist at `/{locale}/partner` with analytics summary cards, bookings-over-time chart, and recent notifications.
- **FR-002**: The tour creation wizard MUST support multi-step flow: Basic Info, Media, Pricing, Availability, SEO with draft save capability.
- **FR-003**: Tour media upload MUST use signed URLs for direct-to-storage upload with preview and reordering support.
- **FR-004**: The tours list MUST exist at `/{locale}/partner/tours` with status filter tabs (draft, pending_review, published, rejected, archived) and pagination.
- **FR-005**: Tour editing MUST exist at `/{locale}/partner/tours/{id}/edit` with pre-populated form fields.
- **FR-006**: The bookings list MUST exist at `/{locale}/partner/bookings` with filters for status, tour, date range, and search.
- **FR-007**: Booking detail MUST show traveler info, participant breakdown by tier, payment status, and action buttons (mark completed, request cancellation).
- **FR-008**: Booking cancellation MUST require a reason and optional evidence upload before processing.
- **FR-009**: The reviews page MUST exist at `/{locale}/partner/reviews` with response/edit capabilities.
- **FR-010**: The profile page MUST exist at `/{locale}/partner/profile` with sections for business info, notification settings, and payout info.
- **FR-011**: The analytics page MUST exist at `/{locale}/partner/analytics` with date range and tour filters.
- **FR-012**: All partner pages MUST be protected via PartnerAuthGuard — non-partner users receive 403, unauthenticated users redirect to login.
- **FR-013**: The partner layout MUST include a sidebar navigation and header with notification bell.
- **FR-014**: All pages MUST support three locales (EN, ES, IT) with full translation coverage.
- **FR-015**: All pages MUST be fully responsive with mobile-first design, tested at 390px and 780px breakpoints.
- **FR-016**: All pages MUST meet WCAG 2.1 AA accessibility standards.
- **FR-017**: Real-time notifications MUST be supported for new bookings, cancellations, reviews, and tour status changes.
- **FR-018**: All API client calls MUST include valid authentication credentials via Bearer token.

### Key Entities

- **Partner**: Business entity linked to a user account with company_name, slug, email, verified status.
- **Tour**: Partner-owned tour listing with full content (description, media, pricing, availability, SEO), status lifecycle (draft → pending_review → published/rejected → archived).
- **TourDraft**: Partial tour data saved during multi-step creation with current_step tracking and submitted_for_review flag.
- **PartnerBooking**: Booking record from the partner's perspective including traveler info, participant breakdown by tier, payment status.
- **PartnerReview**: Traveler review with partner response capability.
- **Notification**: System notification with type, priority, read/unread status.
- **PartnerProfile**: Business profile with address, tax ID, preferred language/currency.
- **PartnerSettings**: Notification preferences and payout information.
- **AnalyticsSummary**: Aggregated metrics: total bookings, revenue, rating, conversion rate, bookings over time.

## Success Criteria _(mandatory)_

### Measurable Outcomes

- **SC-001**: A partner can create a tour from start to submission in under 10 minutes.
- **SC-002**: The partner dashboard loads analytics data within 2 seconds.
- **SC-003**: Tour draft auto-save completes within 1 second.
- **SC-004**: Booking list with filters renders within 2 seconds.
- **SC-005**: All partner pages achieve a Lighthouse Performance score ≥ 90.
- **SC-006**: All partner pages pass automated WCAG 2.1 AA accessibility audit (Lighthouse accessibility score ≥ 90).
- **SC-007**: 100% of partner routes enforce PartnerAuthGuard with proper 403/redirect behavior.
- **SC-008**: All 3 locales (EN, ES, IT) render correct translations across all partner pages.
- **SC-009**: All partner pages render correctly at 390px (mobile) and 1280px (desktop).
- **SC-010**: Notification badge updates in real-time when new notifications arrive.

## Assumptions

- The Laravel backend Partner domain APIs are operational (controllers, services, and models exist in `backend/app/Domains/Partner/`).
- The existing auth system handles partner role identification via `PartnerRoleMiddleware`.
- The existing design system tokens (Navy #0A2540, Gold #FFB800, Inter typography, 8px grid, 12px radius) apply to all partner components.
- The partner layout uses a sidebar navigation pattern distinct from the public/traveler layout.
- File uploads use signed URLs to upload directly to cloud storage (S3/equivalent).
- The backend provides paginated endpoints for tours, bookings, reviews, and notifications.
- Analytics data is computed server-side and returned as aggregated summaries.
- Real-time notifications use WebSockets or Server-Sent Events (SSE) via the `usePartnerRealtime` hook.
