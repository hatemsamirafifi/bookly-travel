# Frontend Implementation Plan - Stitch to Spec-Kit Pipeline

> **Goal**: Map every Stitch UI screen (~47 total; 34 Next.js + 13 Filament reference) to Spec-Kit specs and build the Bookly frontend through `specify -> clarify -> plan -> tasks -> implement`.
> **Status**: Ready for task generation after applying the traceability rules below.

## Resolved Decisions

| # | Question | Decision |
|---|----------|----------|
| D1 | Wishlist and Blog screens? | Create `016-blog-travel-insights`; wishlist absorbed into `011-tour-management` (already implemented). |
| D2 | Frontend implementation order? | Finish Public Frontend spec `010`, then Tour Management `011`, Reviews `009`, and other remaining specs. |
| D3 | Stitch export strategy? | Use Stitch as visual scaffolding only; rebuild as reusable Next.js components. |
| D4 | Admin dashboard approach? | Keep Laravel Filament; use Stitch admin screens as design reference only. |
| D5 | Existing spec update strategy? | Use `010-public-frontend` and `011-tour-management` as the consolidated specifications for public website and traveler area. |

## Spec-Kit Strategy

### Spec Directory Mapping

The plan uses logical spec IDs. This table maps each to the actual directory and required action.

| Spec ID | Plan Label | Actual Directory | Action |
|---------|-----------|-----------------|--------|
| `001-005` | Traveler Auth | `001-traveler-auth` through `005-brute-force-protection` | Amend `001-traveler-auth` with frontend login/register tasks |
| `006-008` | Public, Booking & Payments | Map to `010-public-frontend` | Reconcile with frontend implementation in Spec `010` |
| `009` | Reviews and Ratings | `009-reviews-ratings` | Amend with frontend requirements |
| `010` | Public Frontend | `010-public-frontend` | Completed (frontend search, checkout & payment) |
| `011` | Tour Management (Traveler Account) | `011-tour-management` | In progress (frontend traveler dashboard, profile & wishlists) |
| `012` | Partner Dashboard | `012-partner-dashboard` | Already exists (spec, plan, tasks, data-model) |
| `013` | Admin Moderation | `013-admin-moderation` | Completed — Filament panel, governance audit, permissions, 13 ST-013 resources |
| `014` | Notifications and Vouchers | Create `014-notifications-vouchers` | Create new directory |
| `015` | Partner Onboarding | Create `015-partner-onboarding` | Create new directory |
| `016` | Blog and Travel Insights | Create `016-blog-travel-insights` | Create new directory |
| `017` | Wishlist / Saved Tours | N/A — absorbed into `011-tour-management` | Frontend implemented; no separate spec needed |

### Existing Specs `001-015`

- Keep the existing feature directory as the source of truth.
- Add frontend requirements to the existing `spec.md`.
- Update `plan.md` with routes, components, APIs, state, i18n, and verification.
- Add frontend tasks to `tasks.md` with Stitch screen IDs.
- If backend tasks are complete, add a new frontend phase instead of rewriting backend history.

### New Specs

- Create a new feature directory for `016-blog-travel-insights`.
- Spec `017-wishlist-saved-tours` is **no longer needed** — wishlist functionality has been absorbed into `011-tour-management` and is already implemented.
- Each new spec must define product requirements, data/API ownership, frontend routes, admin or partner touchpoints, and verification gates.

### Required Task Traceability

Every implementation task generated from this plan must include: Spec ID, Stitch screen ID, route or component path, API endpoint or data source where applicable, and verification command or acceptance check.

```text
T006-FE-014 Build tour detail gallery from ST-006-005 for /tours/[slug]
```

## Current State

### Backend Specs Implemented

| Spec | Feature | Backend | Frontend |
|------|---------|---------|----------|
| `001-005` | Traveler Auth, Registration, Sign-in, Brute Force | Done | Partial |
| `006-008` | Public, Booking & Payments | Done | Done via `010` |

### Remaining Specs

| Spec | Feature | Status |
|------|---------|--------|
| `009` | Reviews and Ratings | Frontend components done; spec needs backend endpoint verification |
| `010` | Public Frontend | Done |
| `011` | Tour Management (Traveler Account) | Done (includes wishlist) |
| `012` | Partner Dashboard | Spec done; frontend substantially implemented |
| `013` | Admin Moderation | Done |
| `014` | Notifications and Vouchers | Not started |
| `015` | Partner Onboarding | Not started |
| `016` | Blog and Travel Insights | New spec needed |
| `017` | Wishlist / Saved Tours | Absorbed into `011`; frontend done |

## Stitch Screen Inventory

### Screen Count Summary

| Surface | Next.js Screens | Filament Reference | Total |
|---------|----------------|-------------------|-------|
| Public Traveler Website | 18 | 0 | 18 |
| Customer / Traveler Dashboard | 8 | 0 | 8 |
| Partner Dashboard | 8 | 0 | 8 |
| Admin Dashboard | 0 | 13 | 13 |
| **Total** | **34** | **13** | **47** |

Desktop + mobile variants of the same page count as separate screens because each has its own Stitch design and requires independent visual verification.

### Public Traveler Website - Next.js SSR/SSG

| Stitch ID | Stitch Screen | Spec | Device | Strategy |
|-----------|---------------|------|--------|----------|
| ST-006-001 | BooklyTravel Marketplace Home | `010` | Desktop | Homepage |
| ST-006-002 | BooklyTravel Home Mobile | `010` | Mobile | Responsive homepage |
| ST-006-003 | Explore Tours and Experiences A | `010` | Desktop | Tour listing |
| ST-006-004 | Explore Tours and Experiences B | `010` | Desktop | Listing variant / filter state |
| ST-006-005 | Explore Tours Mobile | `010` | Mobile | Responsive listing |
| ST-006-006 | Tour Details - BooklyTravel A | `010` | Desktop | Tour detail |
| ST-006-007 | Tour Details - BooklyTravel B | `010` | Desktop | Detail variant / booking panel |
| ST-006-008 | Tour Details Mobile | `010` | Mobile | Responsive detail |
| ST-006-009 | Explore Destination - Rome A | `010` | Desktop | Destination landing |
| ST-006-010 | Explore Destination - Rome B | `010` | Desktop | Destination variant |
| ST-006-011 | Explore Rome Mobile | `010` | Mobile | Responsive destination |
| ST-006-012 | History and Culture Desktop Category | `010` | Desktop | Category page |
| ST-006-013 | History and Culture Mobile Category | `010` | Mobile | Responsive category |
| ST-007-001 | Booking Detail Mobile | `011` (primary), `010` (booking data) | Mobile | Booking detail and voucher touchpoint |
| ST-001-001 | Login to BooklyTravel A | `001-traveler-auth` | Desktop | Auth page |
| ST-001-002 | Login to BooklyTravel B | `001-traveler-auth` | Desktop | Auth variant / error state |
| ST-001-003 | Login Mobile | `001-traveler-auth` | Mobile | Responsive auth |
| ST-016-001 | Travel Insights Desktop Blog | `016` | Desktop | Blog listing |
| ST-016-002 | Travel Insights Mobile Blog | `016` | Mobile | Responsive blog listing |

### Customer / Traveler Dashboard - Next.js CSR

| Stitch ID | Stitch Screen | Spec | Device | Strategy |
|-----------|---------------|------|--------|----------|
| ST-015-001 | Customer Dashboard Desktop | `011` | Desktop | Dashboard layout |
| ST-015-002 | Customer Dashboard Mobile | `011` | Mobile | Responsive dashboard |
| ST-015-003 | My Bookings - Customer Dashboard | `011` | Desktop | Booking list |
| ST-015-004 | My Bookings Mobile | `011` | Mobile | Responsive booking list |
| ST-017-001 | My Wishlist - BooklyTravel | `011` | Desktop | Wishlist page |
| ST-017-002 | My Wishlist Mobile | `011` | Mobile | Responsive wishlist |
| ST-015-005 | Account Settings - BooklyTravel | `011` | Desktop | Account settings |
| ST-015-006 | Profile Settings Mobile | `011` | Mobile | Responsive profile settings |

### Partner Dashboard - Next.js CSR

| Stitch ID | Stitch Screen | Spec | Device | Strategy |
|-----------|---------------|------|--------|----------|
| ST-010-001 | Partner Dashboard Overview A | `010` | Desktop | Partner home |
| ST-010-002 | Partner Dashboard Overview B | `010` | Desktop | Overview variant / data state |
| ST-010-003 | Partner Dashboard Mobile | `010` | Mobile | Responsive partner home |
| ST-011-001 | My Tours - Partner Portal | `011` | Desktop | Tour list |
| ST-011-002 | My Tours Partner Mobile | `011` | Mobile | Responsive tour list |
| ST-011-003 | Create New Tour Desktop | `011` | Desktop | Tour creation form |
| ST-011-004 | Tour Editor Dashboard | `011` | Desktop | Tour editor |
| ST-012-001 | Availability Slots Management | `012` | Desktop | Availability calendar |

### Admin Dashboard - Laravel Filament Reference Only

Admin screens use Filament server-rendered views, not Next.js routes.

| Stitch ID | Stitch Screen | Filament Resource | Purpose |
|-----------|---------------|------------------|---------|
| ST-013-001 | Platform Overview Dashboard A | Custom Filament Widget | Dashboard layout |
| ST-013-002 | Platform Overview Dashboard B | Custom Filament Widget | Dashboard variant |
| ST-013-003 | Marketplace Admin Home A | Filament Dashboard | Navigation and stats |
| ST-013-004 | Marketplace Admin Home B | Filament Dashboard | Dashboard variant |
| ST-013-005 | Partner Approvals Admin | PartnerResource | Approval workflow |
| ST-013-006 | Tours Moderation Admin | TourResource | Moderation actions |
| ST-013-007 | Booking Management Admin A | BookingResource | Booking table |
| ST-013-008 | Booking Management Admin B | BookingResource | Booking variant |
| ST-013-009 | Reviews Moderation Admin | ReviewResource | Review moderation |
| ST-013-010 | Availability and Slots Admin | AvailabilityResource | Calendar/slots |
| ST-013-011 | Admin Settings Dashboard | Settings Page | Settings layout |
| ST-013-012 | Content Management Admin | CMS Resource | Content editing |
| ST-013-013 | Site Pages CMS Admin | PageResource | Static pages |

## Execution Plan - 7 Phases

### Phase 1: Public Frontend - Spec `010`

**Objective**: Complete public discovery, booking, and payment frontend work against implemented backend APIs.

```text
1. /speckit.specify - Create or amend Spec 010 with public-facing requirements.
   Target directory: specs/010-public-frontend/ (completed).
2. /speckit.clarify - Resolve component structure, breakpoints, API contracts, and states.
3. /speckit.plan - Define routes, component tree, state, API integration, and verification.
4. /speckit.tasks - Generate frontend tasks with Stitch screen IDs.
5. /speckit.implement - Build components from Stitch scaffolding.
```

**Deliverables**: Homepage, listing, tour detail, category, destination, checkout, confirmation, Stripe Elements, payment success/failure states.

**API Endpoints Consumed**:
- `GET /api/public/tours` - Tour listing with search/filter
- `GET /api/public/tours/{slug}` - Tour detail
- `GET /api/public/tours/{slug}/availability` - Availability calendar
- `GET /api/public/tours/{slug}/reviews` - Tour reviews
- `POST /api/public/bookings` - Create booking
- `GET /api/public/bookings/{reference}` - Booking confirmation
- Stripe Payment Intents API via `@stripe/react-stripe-js`

**Verification**:
- Visual diff of `ST-006-*` screens against Stitch at 390px, 780px, 1280px
- Visual diff of `ST-007-001` against Stitch at 390px
- E2E test: homepage -> search -> tour detail -> checkout -> payment -> confirmation
- `npm run build` - No TypeScript errors
- `npm run lint` - No lint errors
- `npm run test:e2e` (Playwright) - Booking flow passes
- Lighthouse Performance >= 90 for `/`, `/tours`, `/tours/[slug]`

**Done When**: All `ST-006-*` and `ST-007-001` screens are implemented, E2E booking flow passes, and Lighthouse >= 90 on public pages.

### Phase 2: Tour Management (Traveler Account) - Spec `011`

**Objective**: Build authenticated traveler dashboard, booking history, booking detail, profile settings, and voucher access.

```text
1. /speckit.specify - Create or amend Spec 011 with dashboard and booking-management requirements.
   Target directory: specs/011-tour-management/ (completed).
2. /speckit.clarify - Resolve account states, empty states, pagination, voucher permissions, and mobile nav.
3. /speckit.plan - Define dashboard routes, protected layout, API clients, forms, and verification.
4. /speckit.tasks - Generate tasks for ST-015-* and ST-007-001.
5. /speckit.implement - Build account frontend.
```

**Dependencies**: `001-005` auth (done), `010` public frontend (done).

**Deliverables**: Dashboard, bookings list, booking detail with voucher download, profile/settings, wishlist save/remove, loading/error/empty states.

**API Endpoints Consumed**:
- `GET /api/public/my-bookings` - Booking list with status filter
- `GET /api/public/my-bookings/{reference}` - Booking detail
- `POST /api/public/my-bookings/{reference}/cancel` - Cancel booking
- `GET /api/public/profile` - Traveler profile
- `PUT /api/public/profile` - Update profile
- `POST /api/public/profile/password` - Change password
- `GET /api/public/wishlist` - Wishlist items
- `POST /api/public/wishlist/{tour_slug}` - Add to wishlist
- `DELETE /api/public/wishlist/{tour_slug}` - Remove from wishlist
- `GET /api/public/my-bookings/{reference}/voucher` - Voucher PDF download

**Verification**:
- Guest visiting `/[locale]/my-bookings` redirects to `/[locale]/auth/login?returnUrl=...`
- Voucher download returns 403 for non-owner
- Profile form validates phone format and shows field-level errors
- Wishlist heart icon optimistic update within 100ms
- Mobile dashboard navigation tested at 390px
- `npm run test:e2e` - Dashboard and cancellation flows pass

**Done When**: All `ST-015-*` and `ST-017-*` screens implemented, auth guard redirects work, voucher download authorized, profile save persists, and wishlist toggling is functional.

### Phase 3: Reviews and Ratings - Spec `009`

**Objective**: Complete the reviews and ratings frontend for travelers, partners, and admin moderation. Covers review display on tour detail, submission and editing from booking history, My Reviews dashboard, partner review analytics, and Filament moderation reference (ST-013-009).

```text
1. /speckit.specify - Amend Spec 009 with frontend review display, eligibility, and moderation requirements.
   Target directory: specs/009-reviews-ratings/ (amend existing).
2. /speckit.clarify - Resolve verified-booking rules, 48-hour edit window, rating scale, and empty states.
3. /speckit.plan - Define tour detail review components, booking detail review CTA, My Reviews dashboard, partner view, and Filament references.
4. /speckit.tasks - Generate frontend tasks with ST-009-* and ST-013-009 references.
5. /speckit.implement - Build and wire review UI, E2E tests, and a11y coverage.
```

**Dependencies**: `006-008` (done), `010` (Phase 1), `011` (Phase 2).

**Deliverables**:
- Review aggregate rating and paginated list on tour detail page
- Review submission form on completed booking detail (eligible within 30 days)
- Review editing within 48-hour window with "Edited" indicator
- My Reviews dashboard with pagination, edit links, and empty states
- Partner reviews dashboard (read-only analytics)
- Filament moderation reference (ST-013-009)
- E2E and a11y test coverage for all review surfaces

**API Endpoints Consumed**:
- GET /api/public/tours/{slug}/reviews - Paginated tour reviews with aggregate stats (unauthenticated)
- POST /api/public/reviews - Submit review (authenticated, requires booking_reference, rating, comment, locale)
- PUT /api/public/reviews/{review} - Edit review within 48h window (authenticated, owner-only)
- GET /api/traveler/reviews - Traveler's own reviews with pagination
- GET /api/partner/reviews - Partner's tour reviews (read-only)
- GET /api/admin/reviews - Admin moderation queue
- POST /api/admin/reviews/{review}/hide - Hide review
- POST /api/admin/reviews/{review}/reinstate - Reinstate review

**Frontend Components & Routes**:

| Stitch ID | Component / Route | Spec | Purpose |
|-----------|------------------|------|---------|
| ST-009-001 | components/reviews/ReviewList.tsx | 009 | Paginated review list on tour detail |
| ST-009-002 | components/reviews/AggregateRating.tsx | 009 | Average rating + review count display |
| ST-009-003 | components/reviews/ReviewCard.tsx | 009 | Individual review item with name, rating, date, comment |
| ST-009-004 | components/reviews/StarRating.tsx | 009 | Interactive 5-star rating input |
| ST-009-005 | components/reviews/ReviewForm.tsx | 009 | Submit / edit review form with validation |
| ST-009-006 | app/[locale]/my-bookings/[reference]/client.tsx | 009 | Booking detail with review CTA and existing review display |
| ST-015-004 | app/[locale]/my-reviews/page.tsx | 009 | My Reviews dashboard (SSR shell + CSR list) |
| ST-015-004 | components/reviews/MyReviewsList.tsx | 009 | Paginated list of user's reviews with edit links |
| ST-010-00* | app/[locale]/partner/reviews/page.tsx | 009 | Partner reviews dashboard (new route) |
| ST-013-009 | Filament ReviewResource | 009 | Admin moderation UI (design reference only) |

**Task Traceability**:

| Task ID | Description | Spec | Stitch | Route / Component | API | Verification |
|---------|-------------|------|--------|------------------|-----|------------|
| T009-FE-001 | Integrate ReviewList into tour detail sidebar | 009 | ST-009-001 | /tours/[slug] | GET /api/public/tours/{slug}/reviews | Unit + visual diff |
| T009-FE-002 | Wire AggregateRating in tour header | 009 | ST-009-002 | TourDetail.tsx | Tour detail API meta | Unit test |
| T009-FE-003 | Enhance ReviewForm with edit mode and 48h guard | 009 | ST-009-005 | BookingDetailClient | PUT /api/public/reviews/{review} | E2E: edit within 48h |
| T009-FE-004 | Show existing review in booking detail with "Edited" badge | 009 | ST-009-006 | BookingDetailClient | GET /api/public/my-bookings/{reference} | E2E: read-only after 48h |
| T009-FE-005 | Add pagination to MyReviewsList | 009 | ST-015-004 | /my-reviews | GET /api/traveler/reviews | Unit test |
| T009-FE-006 | Add edit links to MyReviewsList within 48h window | 009 | ST-015-004 | /my-reviews | PUT /api/public/reviews/{review} | E2E: navigate and edit |
| T009-FE-007 | Create partner reviews page | 009 | ST-010-00* | /partner/reviews | GET /api/partner/reviews | Visual diff + unit |
| T009-FE-008 | Add review a11y tests | 009 | - | Review components | - | npm run test:a11y |
| T009-FE-009 | Add review E2E tests (submit, display, edit, eligibility) | 009 | - | All review routes | All review APIs | npm run test:e2e -- --grep "review" |
| T009-FE-010 | Add i18n keys for review flows | 009 | - | messages/en.json, es.json, it.json | - | All three locales complete |

**Backend Dependencies for Frontend Completion**:
- BookingResponseDTO must include review relation so the booking detail page knows whether a review exists and whether it is still editable.
- GET /api/traveler/reviews endpoint must exist and return paginated reviews with tour.slug, rating, text, submitted_at, and edited flags.

**Verification**:
- `npm run build` - No TypeScript errors in review components
- `npm run lint` - No ESLint errors in review components
- `npm run test` - Review component unit tests pass (ReviewForm, ReviewList, StarRating, AggregateRating)
- `npm run test:e2e -- --grep "review"` - Review submission, display, and edit flows pass
- `npm run test:a11y` - Review components pass axe-core checks (star rating keyboard accessibility, form labels)
- Review eligibility enforced: only completed bookings within 30 days show the "Write a Review" CTA
- 48-hour edit window: edit button visible within window, hidden after expiry; "Edited" badge shown on updated reviews
- Rate limiting: backend enforces 10 reviews/hour per traveler; frontend surfaces 429 error with retry messaging
- Review appears on tour detail within 2 seconds of submission (optimistic UI or refetch)
- Empty states render correctly when no reviews exist ("No reviews yet. Be the first!")
- Mobile layout verified at 390px for tour detail reviews, booking detail review form, and My Reviews dashboard


**Done When**: Review display integrated on tour detail, submission and editing flows work from booking detail, My Reviews dashboard has pagination and edit links, partner review page exists, all E2E and a11y tests pass, and Filament moderation resources match ST-013-009 reference.

### Phase 4: Partner Dashboard — Spec `012`

> **Status**: Substantially implemented. Routes, layout, auth guard, tour CRUD, availability calendar, image upload, pricing, analytics, and partner profile all exist. This phase focuses on polish, visual verification, and E2E coverage.

**Objective**: Complete and polish the partner dashboard — verify visual alignment with Stitch screens and fill remaining gaps in validation, i18n, and test coverage.

```text
For spec 012 (012-partner-dashboard — already exists):
1. /speckit.clarify - Resolve remaining edge cases: draft/archive states, calendar conflicts, image reordering.
2. /speckit.plan - Identify gaps between existing implementation and Stitch screens.
3. /speckit.tasks - Generate tasks only for unimplemented or incomplete features.
4. /speckit.implement - Complete remaining work and polish.
```

**Dependency chain**: `010 -> 011 -> 012`.

**Already Implemented**:
- Partner layout: `PartnerSidebar`, `PartnerHeader`, `MobileDrawer` with `PartnerAuthGuard`
- Tour management: `TourList`, `TourCard`, `TourWizard` (create/edit), `ImageUploader`
- Availability: `AvailabilityCalendar` at `partner/tours/[id]/availability`
- Pricing: `PricingTierForm` at `partner/tours/[id]/pricing`
- Analytics: `AnalyticsSummary`, `BookingsChart` (using Recharts)
- Partner bookings, reviews, and profile pages
- API client: `lib/api/partner.ts` (comprehensive endpoint coverage)
- State: `lib/stores/tourWizard.ts` (Zustand store for multi-step tour creation)
- Real-time hook: `lib/hooks/usePartnerRealtime.ts`

**Remaining Work**:
- Visual diff of `ST-010-*`, `ST-011-*`, `ST-012-*` against Stitch at 390px, 780px, 1280px
- Tour form validation completeness (title, description, duration, location, category)
- Tour creation with EN/ES/IT translations round-trip verification
- Image upload constraints enforcement (max 10 images, 5MB per image, JPEG/PNG/WebP)
- Availability calendar empty/full/partially-booked visual states
- Partner approval status UI polish
- Add Zod validators for partner forms (`lib/validators/partner.ts`)
- E2E test coverage for partner flows

**API Endpoints Consumed**:
- `GET /api/partner/profile` - Partner profile and approval status
- `PUT /api/partner/profile` - Update partner profile
- `GET /api/partner/tours` - Partner tour list
- `POST /api/partner/tours` - Create tour
- `GET /api/partner/tours/{id}` - Tour detail
- `PUT /api/partner/tours/{id}` - Update tour
- `POST /api/partner/tours/{id}/submit` - Submit for review
- `POST /api/partner/tours/{id}/archive` - Archive tour
- `POST /api/partner/tours/{id}/images` - Upload images
- `DELETE /api/partner/tours/{id}/images/{imageId}` - Delete image
- `PUT /api/partner/tours/{id}/pricing` - Set pricing
- `GET /api/partner/tours/{id}/availability` - Get availability
- `POST /api/partner/tours/{id}/availability` - Add slots
- `PUT /api/partner/tours/{id}/availability/{slotId}` - Update slot
- `DELETE /api/partner/tours/{id}/availability/{slotId}` - Remove slot

**Verification**:
- Non-partner users cannot access `/partner/*` routes
- Tour form validates all required fields (title, description, duration, location, category)
- Tour creation with EN/ES/IT translations saves correctly
- Image upload respects max 10 images, 5MB per image, JPEG/PNG/WebP
- Availability calendar shows empty/full/partially booked states
- `npm run test:e2e` - Partner tour creation flow passes

**Done When**: All `ST-010-*`, `ST-011-*`, `ST-012-*` screens match Stitch designs at all viewports, remaining validation gaps filled, E2E tests pass, and visual diff is clean.

### Phase 5: Admin Moderation - Spec `013`

> **Status**: Done. All 13 ST-013 Filament counterparts exist, governance audit logging works, admin permissions enforced, approval/rejection workflows functional. All 65 tasks in `specs/013-admin-moderation/tasks.md` are complete.

**Objective**: Customize Filament admin resources using Stitch admin screens as reference.

```text
1. /speckit.specify - Define moderation workflows, resources, permissions, and admin UX.
   Target directory: specs/013-admin-moderation/ (create new).
2. /speckit.clarify - Resolve statuses, bulk actions, audit requirements, and dashboard metrics.
3. /speckit.plan - Map Stitch admin IDs to Filament widgets/resources/actions.
4. /speckit.tasks - Generate Filament-specific implementation tasks.
5. /speckit.implement - Build or customize Filament resources.
```

**Deliverables**: Dashboard widgets, partner approvals, tour moderation, booking management, review moderation, settings/CMS/static page resources.

**Integrations**: Laravel models, policies, Filament resources/widgets, audit logs.

**Verification**:
- Admin permissions enforced (non-admin users cannot access Filament panel)
- Status changes on partners and tours produce audit log entries
- Filament screens are usable on desktop at 1280px+
- All `ST-013-*` screens have corresponding Filament resources

**Done When**: All 13 admin screens have Filament counterparts, audit logging works, approval/rejection workflows functional.

### Phase 6: Notifications and Vouchers - Spec `014`

**Objective**: Build notification and voucher backend work with the frontend voucher download touchpoint.

```text
1. /speckit.specify - Define email, notification, voucher, and frontend download requirements.
   Target directory: specs/014-notifications-vouchers/ (create new).
2. /speckit.clarify - Resolve template languages, resend rules, voucher permissions, and failure states.
3. /speckit.plan - Define backend jobs/templates and frontend download integration.
4. /speckit.tasks - Generate tasks for backend delivery plus the Spec 015 frontend touchpoint.
5. /speckit.implement - Build notifications, voucher generation, and download action.
```

**Deliverables**: Notification templates, voucher PDF generation, download endpoint/action, missing/expired/unauthorized states.

**Integrations**: Booking events, mail/notification queue (Redis), PDF service (DomPDF or Browsershot), voucher storage and authorization.

**Verification**:
- Voucher download returns 403 for unauthorized users
- EN/ES/IT email templates render correctly
- Booking confirmation email triggers on successful payment
- Voucher PDF contains booking reference, QR code, tour details
- Download action in booking detail page works end-to-end

**Done When**: All notification templates exist in 3 locales, voucher PDF generates correctly, download authorization enforced.

### Phase 7: Blog and Travel Insights — Spec `016`

> **Note**: Wishlist / Saved Tours (originally Spec `017`) has been absorbed into Spec `011` and is already implemented under `(traveler)/wishlist/` with `WishlistButton` and `WishlistGrid` components. No separate spec is needed.

**Objective**: Build the blog and travel insights section for content marketing, SEO, and tour discovery integration.

```text
/speckit.specify Create the blog and travel insights specification for Bookly.
Target directory: specs/016-blog-travel-insights/ (create new).
Reference screens: ST-016-001, ST-016-002.

The spec MUST define:
- Blog listing with categories, featured posts, pagination, and empty states.
- Blog post page with rich content, author metadata, related tours, and related posts.
- SEO: structured data, canonical URLs, meta tags, Open Graph, and sitemap inclusion.
- Content ownership: Filament CMS resource or markdown-based content, chosen explicitly.
- EN, ES, and IT support.
- Integration with tour discovery through related tour links.
- Backend model for posts, categories, authors, translations, slugs, publication status, and related tours.
- API/data contract for listing, detail, category filtering, search, and preview.

Backend implementation follows the same Laravel patterns as specs 006-008.
```

**Deliverables**: Blog listing page (`/blog`), blog post detail page (`/blog/[slug]`), category filtering, related tours integration, SEO metadata, i18n support in EN/ES/IT.

**Verification**:
- Blog SEO metadata present (Lighthouse SEO audit on `/blog`, `/blog/[slug]`)
- Blog listing pagination works
- Blog post renders rich content correctly
- Related tours display and link correctly
- EN/ES/IT content renders correctly
- Visual diff of `ST-016-001` and `ST-016-002` at 390px, 780px, 1280px

**Done When**: Blog listing and detail pages implemented, SEO metadata passes Lighthouse audit, i18n verified, and visual diff matches Stitch screens.

## Frontend Architecture

### Route Structure

Routes are nested under `[locale]/` for i18n (handled by `next-intl` middleware). Route groups `(public)`, `(auth)`, `(traveler)`, and `(partner)` organize layouts and middleware.

```text
frontend/src/app/
  [locale]/
    (public)/
      page.tsx                                    # Homepage (SSR)
      search/page.tsx                             # Tour listing / search with filters
      tours/[slug]/page.tsx                       # Tour detail (SSR)
      destinations/[slug]/page.tsx                # Destination landing (SSR)
      categories/[slug]/page.tsx                  # Category page (SSR)
      booking/page.tsx                            # Checkout flow
      booking/confirmation/page.tsx               # Booking confirmation
      privacy/page.tsx                            # Privacy policy
      terms/page.tsx                              # Terms of service
      blog/page.tsx                               # Blog listing (Phase 7 — not yet created)
      blog/[slug]/page.tsx                        # Blog post detail (Phase 7 — not yet created)
    (auth)/
      auth/login/page.tsx                         # Login
      auth/register/page.tsx                      # Register
      auth/forgot-password/page.tsx               # Forgot password
      auth/reset-password/page.tsx                # Reset password
      auth/verify-email/page.tsx                  # Email verification
      auth/partner-register/page.tsx              # Partner registration
    (traveler)/
      my-bookings/page.tsx                        # Booking list
      my-bookings/[reference]/page.tsx            # Booking detail
      wishlist/page.tsx                           # Wishlist (saved tours)
      profile/page.tsx                            # Profile settings
      my-reviews/page.tsx                         # My reviews
    (partner)/
      partner/page.tsx                            # Partner dashboard overview
      partner/tours/page.tsx                      # Partner tour list
      partner/tours/create/page.tsx               # Create tour (wizard)
      partner/tours/[id]/edit/page.tsx            # Edit tour
      partner/tours/[id]/availability/page.tsx    # Availability calendar
      partner/tours/[id]/pricing/page.tsx         # Pricing management
      partner/analytics/page.tsx                  # Partner analytics
      partner/bookings/page.tsx                   # Partner bookings
      partner/reviews/page.tsx                    # Partner reviews
      partner/profile/page.tsx                    # Partner profile settings
```

**Route notes**: Tour listing uses `/search` (not `/tours`). Checkout is at `/booking` (not `/checkout`). Partner routes are nested under `partner/tours/[id]/` for availability and pricing (not standalone). Auth includes forgot-password, reset-password, verify-email, and partner-register in addition to login/register.

### Rendering Strategy

| Route Group | Rendering | Rationale |
|-------------|-----------|-----------|
| `(public)` | SSR/SSG via React Server Components | SEO-critical pages need crawlable HTML |
| `(auth)` | SSR for initial load, client-side form handling | Login/register need fast first paint |
| `(traveler)` | CSR with auth middleware guard | Protected user data, no SEO needed |
| `(partner)` | CSR with role-based middleware guard | Protected partner data, no SEO needed |

### Middleware

The existing `middleware.ts` uses `next-intl` for locale routing. Auth protection requires extending it:

```text
1. next-intl middleware handles [locale] routing (already exists).
2. Auth middleware checks session for (traveler) and (partner) route groups.
3. Role middleware verifies user.role === 'partner' for (partner) routes.
4. Unauthenticated visitors are redirected to /[locale]/auth/login?returnUrl=<intended-path>.
```

### Component Structure

```text
frontend/src/
  components/
    auth/                                 # Auth forms and guards
      AuthGuard.tsx                       # Traveler route protection
      PartnerAuthGuard.tsx                # Partner route protection
      LoginForm.tsx                       # Login form with validation
      RegisterForm.tsx                    # Registration form
      ForgotPasswordForm.tsx              # Password reset request
      ResetPasswordForm.tsx               # Password reset with token
      VerifyEmailClient.tsx               # Email verification
      PartnerRegisterForm.tsx             # Partner onboarding form
      GuestConversionPrompt.tsx           # Guest-to-registered conversion
    booking/                              # Checkout and payment
      BookingForm.tsx                     # Multi-step checkout
      BookingConfirmation.tsx             # Post-payment confirmation
      StripePaymentForm.tsx               # Stripe Elements wrapper
      ParticipantSelector.tsx             # Guest count selector
      PriceBreakdown.tsx                  # Price summary display
      PriceChangeModal.tsx                # Price change notification
      PaymentStatus.tsx                   # Payment result display
      DateConfirmation.tsx                # Date selection confirmation
    home/                                 # Homepage sections
      HeroSection.tsx                     # Hero banner
      FeaturedTours.tsx                   # Featured tour cards
      CategoryGrid.tsx                    # Category navigation grid
      DestinationShowcase.tsx             # Destination highlights
    layout/                               # Global layout
      Header.tsx                          # Public header with nav
      Footer.tsx                          # Site footer
      LocaleSwitcher.tsx                  # EN/ES/IT language toggle
      MobileNavPanel.tsx                  # Mobile navigation drawer
      UserMenuDropdown.tsx                # Authenticated user menu
    my-bookings/                          # Traveler booking management
      BookingCard.tsx                     # Booking list item
      BookingList.tsx                     # Paginated booking list
      BookingStatusBadge.tsx              # Status indicator badge
      CancelBookingButton.tsx             # Cancel with confirmation
    partner/                              # Partner dashboard components
      analytics/AnalyticsSummary.tsx       # Overview metrics
      analytics/BookingsChart.tsx          # Recharts booking chart
      layout/PartnerSidebar.tsx           # Dashboard sidebar nav
      layout/PartnerHeader.tsx            # Dashboard header
      layout/MobileDrawer.tsx             # Mobile sidebar drawer
      tours/TourList.tsx                  # Partner tour list
      tours/TourCard.tsx                  # Partner tour card
      tours/TourWizard.tsx                # Multi-step tour creation/edit
      tours/ImageUploader.tsx             # Drag-and-drop image upload
      tours/AvailabilityCalendar.tsx       # Availability slot management
      tours/PricingTierForm.tsx           # Pricing tier editor
      bookings/...                        # Partner booking views
      reviews/...                         # Partner review views
      profile/...                         # Partner profile forms
    profile/                              # Traveler profile
      ProfileForm.tsx                     # Profile info editor
      ProfileSettings.tsx                 # Settings container
      PasswordChangeForm.tsx              # Password update form
      PreferencesForm.tsx                 # User preferences
    reviews/                              # Review system
      AggregateRating.tsx                 # Average rating display
      ReviewList.tsx                      # Paginated review list
      ReviewCard.tsx                      # Individual review item
      ReviewForm.tsx                      # Submit/edit review form
      StarRating.tsx                      # Interactive star input
      MyReviewCard.tsx                    # User's own review card
      MyReviewsList.tsx                   # User's review list
    search/                               # Tour search and discovery
      SearchBar.tsx                       # Search input with autocomplete
      FilterPanel.tsx                     # Faceted filter sidebar
      SearchResults.tsx                   # Results grid layout
      TourCard.tsx                        # Tour listing card
      Pagination.tsx                      # Page navigation
      SortDropdown.tsx                    # Sort order selector
      SearchUnavailable.tsx               # Search error fallback
    seo/                                  # SEO components
      StructuredData.tsx                  # JSON-LD schema output
    tour/                                 # Tour detail page
      TourDetail.tsx                      # Main detail layout
      ImageGallery.tsx                    # Photo gallery with lightbox
      AvailabilityCalendar.tsx            # Public availability view
      BookingCTA.tsx                      # Book now action panel
      ReviewList.tsx                      # Tour-specific review list
    ui/                                   # Shared primitives
      button.tsx, input.tsx, label.tsx     # Form controls
      select.tsx, switch.tsx, textarea.tsx # Form inputs
      Toast.tsx                           # Notification toasts
      EmptyState.tsx                      # No-data placeholder
      ErrorState.tsx                      # Error boundary display
      LoadingSkeleton.tsx                 # Loading placeholder
    wishlist/                             # Saved tours
      WishlistButton.tsx                  # Save/unsave toggle
      WishlistGrid.tsx                    # Wishlist page grid
  lib/
    api/                                  # API client modules
      client.ts                           # Base HTTP client with auth
      auth.ts                             # Auth endpoints
      tours.ts                            # Public tour endpoints
      search.ts                           # Search and filter endpoints
      bookings.ts                         # Booking CRUD
      my-bookings.ts                      # Traveler booking endpoints
      traveler.ts                         # Traveler profile, wishlist, reviews
      partner.ts                          # Partner CRUD (tours, availability, pricing)
      homepage.ts                         # Homepage data
      categories.ts                       # Category endpoints
      destinations.ts                     # Destination endpoints
      types.ts                            # Shared API response types
    auth/                                 # Auth context, session management
    hooks/                                # Custom hooks
      useAuth.tsx                         # Auth state and actions
      useCheckout.ts                      # Checkout flow state
      useFilters.ts                       # Search filter management
      useFocusTrap.ts                     # Modal focus trap
      usePartnerRealtime.ts               # Partner SSE/polling
      useTour.ts                          # Single tour data
      useTours.ts                         # Tour list data
    reviews/                              # Review utilities
    stores/                               # Zustand stores
      checkout-store.ts                   # Checkout session state
      tourWizard.ts                       # Multi-step tour creation
    stripe/                               # Stripe integration
      stripe-client.ts                    # Stripe.js initialization
    validators/                           # Zod schemas
      auth.ts                             # Auth form validation
    design-tokens.ts                      # Centralized design tokens
    query-provider.tsx                    # TanStack React Query provider
    utils.ts                              # Shared utilities
    images.ts                             # Image helpers (plaiceholder)
```

### Data and Rendering Boundaries

- Public discovery pages use SSR or SSG where SEO matters.
- Traveler and partner dashboards use authenticated CSR or server actions only where compatible with session handling.
- API responses are validated at the frontend boundary before rendering critical UI.
- Forms use Zod schema validation via `react-hook-form` + `@hookform/resolvers` before submission and show field-level errors.
- Payment UI must use Stripe Elements; never collect raw card data in Bookly components.
- Protected routes enforce role and session checks before fetching private data.
- Data fetching uses TanStack React Query for server state caching, deduplication, and background refetching.

### Styling and Tooling

- **CSS framework**: Tailwind CSS v4 via `@tailwindcss/postcss` with `tailwind-merge` for conditional class composition.
- **Error monitoring**: Sentry (`@sentry/nextjs`) is integrated for both client and server error tracking via `sentry.client.config.ts` and `sentry.server.config.ts`.
- **Charts**: Recharts is used for partner analytics (booking trends, revenue charts).
- **Image optimization**: `plaiceholder` for blur-up placeholders on SSR-rendered images.

## Design System Tokens

All tokens below align with the existing `frontend/src/lib/design-tokens.ts` file and extend it where needed.

> **Implementation gap**: The current `design-tokens.ts` implements basic colors (`primary`, `accent`, `background`, `surface`, `text`, `border`, `error`, `success`), typography (h1–h4 with Inter font), spacing (8px grid), border radius, and three shadow levels. The tokens below for interactive states (`hover`, `pressed`), `border.focus`, `shadow.modal`, animation/transition values, z-index scale, and mobile-specific typography sizes are **not yet implemented** and must be added before use.

### Color

| Token | Value | Usage |
|-------|-------|-------|
| `color.brand.navy` | `#0A2540` | Navigation, headings, high-emphasis UI |
| `color.brand.gold` | `#FFB800` | Primary calls to action, highlights, star ratings |
| `color.background.page` | `#F7F9FB` | App background |
| `color.background.surface` | `#FFFFFF` | Cards, panels, forms |
| `color.background.elevated` | `#FFFFFF` | Modals, dropdowns (with shadow) |
| `color.text.primary` | `#102033` | Body and headings |
| `color.text.secondary` | `#5D6B7A` | Supporting copy |
| `color.text.inverse` | `#FFFFFF` | Text on dark backgrounds |
| `color.border.default` | `#DDE5EE` | Inputs, cards, separators |
| `color.border.focus` | `#0A2540` | Focus rings on interactive elements |
| `color.state.success` | `#11845B` | Success states, confirmed badges |
| `color.state.warning` | `#B76E00` | Warnings and pending states |
| `color.state.danger` | `#C62828` | Errors and destructive actions |
| `color.interactive.hover` | `rgba(10, 37, 64, 0.08)` | Hover overlay on interactive surfaces |
| `color.interactive.pressed` | `rgba(10, 37, 64, 0.12)` | Active/pressed state overlay |

### Typography

| Token | Desktop | Mobile | Usage |
|-------|---------|--------|-------|
| `type.pageTitle` | 32px / 1.2 / 700 | 26px / 1.2 / 700 | Page headings (h1) |
| `type.sectionTitle` | 24px / 1.25 / 600 | 21px / 1.25 / 600 | Section headings (h2) |
| `type.cardTitle` | 18px / 1.3 / 600 | 18px / 1.3 / 600 | Card titles (h3) |
| `type.subheading` | 16px / 1.35 / 600 | 16px / 1.35 / 600 | Subheadings (h4) |
| `type.body` | 16px / 1.5 / 400 | 16px / 1.5 / 400 | Body text |
| `type.small` | 14px / 1.4 / 400 | 14px / 1.4 / 400 | Supporting text, labels |
| `type.caption` | 12px / 1.4 / 400 | 12px / 1.4 / 400 | Captions, metadata |

Font: Inter via Google Fonts. Weights: 400 (regular), 500 (medium), 600 (semibold), 700 (bold). Letter spacing: 0.

### Spacing

8px base grid with the following scale: 4, 8, 12, 16, 24, 32, 48, 64px.

| Alias | Value | Usage |
|-------|-------|-------|
| `space.pagePadding` | 24px (mobile) / 64px (desktop) | Page-level horizontal padding |
| `space.sectionGap` | 48px (mobile) / 64px (desktop) | Vertical gap between major sections |
| `space.cardPadding` | 16px (mobile) / 24px (desktop) | Internal card padding |
| `space.formGap` | 16px | Gap between form fields |
| `space.inlineGap` | 8px | Gap between inline elements (icon + text) |

### Shadows

| Token | Value | Usage |
|-------|-------|-------|
| `shadow.sm` | `0 1px 2px 0 rgb(0 0 0 / 0.05)` | Subtle elevation (badges, chips) |
| `shadow.card` | `0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)` | Cards, panels |
| `shadow.dropdown` | `0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)` | Dropdowns, popovers |
| `shadow.modal` | `0 25px 50px -12px rgb(0 0 0 / 0.25)` | Modals, dialogs |

### Radius

| Token | Value | Usage |
|-------|-------|-------|
| `radius.sm` | 8px | Buttons, inputs, small cards |
| `radius.default` | 12px | Standard cards, panels |
| `radius.lg` | 16px | Large media cards, hero sections |
| `radius.full` | 9999px | Avatars, pills, toggles |

### Breakpoints

| Token | Value | Usage |
|-------|-------|-------|
| `breakpoint.mobile` | 390px | Mobile verification target |
| `breakpoint.tablet` | 780px | Tablet verification target |
| `breakpoint.desktop` | 1280px | Desktop verification target |

### Animation

| Token | Value | Usage |
|-------|-------|-------|
| `transition.fast` | 150ms ease-out | Hover states, toggles |
| `transition.default` | 200ms ease-in-out | Dropdowns, accordions |
| `transition.slow` | 300ms ease-in-out | Modals, page transitions |

### Z-Index Scale

| Token | Value | Usage |
|-------|-------|-------|
| `z.dropdown` | 10 | Dropdowns, popovers |
| `z.sticky` | 20 | Sticky headers, footers |
| `z.modal` | 30 | Modals, dialogs |
| `z.toast` | 40 | Toast notifications |
| `z.tooltip` | 50 | Tooltips |

## Verification Plan

### Commands

These scripts exist in `frontend/package.json` and are verified:

```bash
npm run build       # Next.js production build (TypeScript check included)
npm test            # Jest unit tests
npm run lint        # ESLint
npm run test:e2e    # Playwright E2E tests
npm run test:a11y   # Playwright accessibility tests (axe-core)
```

**`lighthouse` is available** (already in `package.json`). Still needs to be added:

```bash
npm run typecheck   # Add: "typecheck": "tsc --noEmit" to package.json
```

### Visual Verification

Visual verification uses manual screenshot comparison against Stitch exports. If the team adopts a visual diff tool (Chromatic, Percy, or BackstopJS), update tasks to include automated visual regression.

| Area | Routes | Stitch IDs | Viewports |
|------|--------|-----------|-----------|
| Public discovery | `/`, `/search`, `/tours/[slug]`, `/destinations/[slug]`, `/categories/[slug]` | ST-006-001 through ST-006-013 | 390px, 780px, 1280px |
| Booking/payment | `/booking`, `/booking/confirmation`, payment success/failure states | ST-007-001 | 390px, 780px, 1280px |
| Auth | `/auth/login`, `/auth/register`, `/auth/forgot-password`, `/auth/reset-password` | ST-001-001 through ST-001-003 | 390px, 1280px |
| Traveler account | `/my-bookings`, `/my-bookings/[ref]`, `/wishlist`, `/profile`, `/my-reviews` | ST-015-001 through ST-015-006, ST-017-001, ST-017-002 | 390px, 780px, 1280px |
| Partner dashboard | `/partner`, `/partner/tours`, `/partner/tours/create`, `/partner/tours/[id]/edit`, `/partner/tours/[id]/availability` | ST-010-001 through ST-010-003, ST-011-001 through ST-011-004, ST-012-001 | 390px, 780px, 1280px |
| Blog | `/blog`, `/blog/[slug]` | ST-016-001, ST-016-002 | 390px, 780px, 1280px |

### Acceptance Thresholds

- Lighthouse Performance >= 90 for public pages.
- Lighthouse Accessibility >= 95 for public pages and forms.
- No TypeScript or lint errors (`npm run build` and `npm run lint` pass).
- E2E coverage for homepage -> search -> detail -> checkout -> payment -> confirmation.
- E2E coverage for login -> dashboard -> bookings -> voucher download.
- E2E coverage for login -> wishlist save -> wishlist page -> remove saved tour.
- E2E coverage for partner login -> dashboard -> create tour -> manage availability.
- i18n verification for EN, ES, and IT routes where the spec requires localized content.

### Per-Phase Verification Gates

| Phase | Gate | Command/Check |
|-------|------|---------------|
| Phase 1 | All ST-006-* screens match Stitch | Manual visual diff at 3 viewports |
| Phase 1 | Booking E2E passes | `npm run test:e2e -- --grep "booking"` |
| Phase 1 | Lighthouse >= 90 | `npm run lighthouse` on `/`, `/tours`, `/tours/[slug]` |
| Phase 2 | Auth guard redirects | `npm run test:e2e -- --grep "auth"` |
| Phase 2 | Voucher download works | Manual test: download PDF, verify content |
| Phase 3 | Review eligibility enforced | `npm run test:e2e -- --grep "review"` |
| Phase 4 | Partner role gate | `npm run test:e2e -- --grep "partner"` |
| Phase 5 | Filament resources match ST-013-* | Manual visual check in Filament panel |
| Phase 6 | Voucher PDF has QR code | Manual download and verify |
| Phase 7 | Blog SEO metadata present | Lighthouse SEO audit on `/blog`, `/blog/[slug]` |

## Implementation Guardrails

- Keep public pages inspectable and SEO-friendly.
- Keep admin screens in Filament unless a spec explicitly changes ownership.
- Do not copy Stitch output directly into production components without extracting reusable components and tokens.
- Validate user input at form boundaries before API submission.
- Add empty, loading, error, and permission-denied states for every data-driven surface.
- Ensure every frontend task references a Spec ID and Stitch ID.
- All new backend models, migrations, and API endpoints for spec 016 follow existing Laravel patterns from spec 010.
- Use Tailwind utility classes consistent with the existing component patterns; avoid mixing raw CSS with Tailwind.
- Extend `design-tokens.ts` before adding new hardcoded values — the token file is currently incomplete (missing interactive states, z-index, animations, and mobile typography scales).
- Add Zod validators to `lib/validators/` for all new forms (currently only `auth.ts` exists; profile, booking, review, and partner validators are needed).
- Report frontend errors to Sentry via the existing `@sentry/nextjs` integration.
