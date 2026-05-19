# Frontend Implementation Plan — Stitch → Spec-Kit Pipeline

> **Goal**: Map the 60+ Stitch UI screens to the existing spec-kit feature specs and build the complete Bookly frontend using spec-kit's `specify → clarify → plan → tasks → implement` workflow.
> **Status**: ✅ Decisions locked — ready for execution

---

## Resolved Decisions

| # | Question | Decision |
|---|----------|----------|
| D1 | Wishlist & Blog screens? | **Create new specs** (016-Blog, 017-Wishlist) |
| D2 | Frontend implementation order? | **Finish existing frontend (006–008) first**, then new specs |
| D3 | Stitch code export strategy? | **Use Stitch as UI scaffolding** → rebuild as reusable Next.js components |
| D4 | Admin dashboard approach? | **Keep Laravel Filament**, use Stitch screens as design inspiration only |

---

## Current State

### Backend Specs (Implemented ✅)

| Spec | Feature | Backend | Frontend |
|------|---------|---------|----------|
| 001–005 | Traveler Auth (Registration, Sign-in, Brute Force) | ✅ | ✅ Partial |
| 006 | Public Search & Discovery | ✅ | ✅ Partial |
| 007 | Tour Booking | ✅ | ✅ Partial |
| 008 | Payment Processing | ✅ | ⚠️ Stripe Elements pending |

### Remaining Specs (Not Implemented)

| Spec | Feature | Status |
|------|---------|--------|
| 009 | Reviews & Ratings | 📝 Partial spec |
| 010 | Partner Onboarding | 🔲 Not started |
| 011 | Tour Management | 🔲 Not started |
| 012 | Pricing & Availability | 🔲 Not started |
| 013 | Admin Moderation | 🔲 Not started |
| 014 | Notifications & Vouchers | 🔲 Not started |
| 015 | Traveler Account & Bookings | 🔲 Not started |
| **016** | **Blog & Travel Insights** | **🆕 New — needs spec** |
| **017** | **Wishlist / Saved Tours** | **🆕 New — needs spec** |

---

## Stitch Screens Inventory (Mapped to Specs)

### 🌐 Public Traveler Website (Next.js SSR/SSG)

| Stitch Screen | Maps to Spec | Device | Stitch Strategy |
|---------------|-------------|--------|-----------------|
| BooklyTravel Marketplace Home | 006 | Desktop | Scaffold → Next.js components |
| BooklyTravel Home Mobile | 006 | Mobile | Scaffold → responsive variant |
| Explore Tours & Experiences (×2) | 006 | Desktop | Scaffold → tour listing |
| Explore Tours Mobile | 006 | Mobile | Scaffold → responsive |
| Tour Details - BooklyTravel (×2) | 006 | Desktop | Scaffold → tour detail page |
| Tour Details Mobile | 006 | Mobile | Scaffold → responsive |
| Explore Destination - Rome (×2) | 006 | Desktop | Scaffold → destination landing |
| Explore Rome Mobile | 006 | Mobile | Scaffold → responsive |
| History & Culture Desktop Category | 006 | Desktop | Scaffold → category page |
| History & Culture Mobile Category | 006 | Mobile | Scaffold → responsive |
| Booking Detail Mobile | 007/015 | Desktop | Scaffold → booking detail |
| Login to BooklyTravel (×2) | 001–004 | Desktop | Scaffold → auth pages |
| Login Mobile | 001–004 | Mobile | Scaffold → responsive |
| Travel Insights Desktop Blog | **016** | Desktop | Scaffold → blog page |
| Travel Insights Mobile Blog | **016** | Mobile | Scaffold → responsive |

### 👤 Customer / Traveler Dashboard (Next.js CSR)

| Stitch Screen | Maps to Spec | Device | Stitch Strategy |
|---------------|-------------|--------|-----------------|
| Customer Dashboard Desktop | 015 | Desktop | Scaffold → dashboard layout |
| Customer Dashboard Mobile | 015 | Mobile | Scaffold → responsive |
| My Bookings - Customer Dashboard | 015 | Desktop | Scaffold → booking list |
| My Bookings Mobile | 015 | Mobile | Scaffold → responsive |
| My Wishlist - BooklyTravel | **017** | Desktop | Scaffold → wishlist page |
| My Wishlist Mobile | **017** | Mobile | Scaffold → responsive |
| Account Settings - BooklyTravel | 015 | Desktop | Scaffold → settings |
| Profile Settings Mobile | 015 | Mobile | Scaffold → responsive |

### 🏢 Partner Dashboard (Next.js CSR)

| Stitch Screen | Maps to Spec | Device | Stitch Strategy |
|---------------|-------------|--------|-----------------|
| Partner Dashboard Overview (×2) | 010 | Desktop | Scaffold → partner home |
| Partner Dashboard Mobile | 010 | Mobile | Scaffold → responsive |
| My Tours - Partner Portal | 011 | Desktop | Scaffold → tour list |
| My Tours Partner Mobile | 011 | Mobile | Scaffold → responsive |
| Create New Tour Desktop | 011 | Desktop | Scaffold → tour form |
| Tour Editor Dashboard | 011 | Desktop | Scaffold → tour editor |
| Availability Slots Management | 012 | Desktop | Scaffold → calendar |

### 🛡️ Admin Dashboard (Laravel Filament — Design Reference Only)

> [!NOTE]
> Admin screens use **Filament server-rendered views**, NOT Next.js. Stitch screens serve as **design inspiration** for Filament resource customization.

| Stitch Screen | Filament Resource | Purpose |
|---------------|------------------|---------|
| Platform Overview Dashboard (×2) | Custom Filament Widget | Dashboard layout reference |
| Marketplace Admin Home (×2) | Filament Dashboard | Navigation/stats layout |
| Partner Approvals Admin | PartnerResource | Approval workflow UI |
| Tours Moderation Admin | TourResource | Moderation table/actions |
| Booking Management Admin (×2) | BookingResource | Booking table layout |
| Reviews Moderation Admin | ReviewResource | Review moderation table |
| Availability & Slots Admin | AvailabilityResource | Calendar/slots UI |
| Admin Settings Dashboard | Settings Page | Settings layout |
| Content Management Admin | CMS Resource | Content editing reference |
| Site Pages CMS Admin | PageResource | Static pages reference |

---

## Execution Plan — 7 Phases

### Phase 1: Complete Existing Frontend (Specs 006–008) ← **START HERE**

> [!IMPORTANT]
> These specs have backend done. Frontend needs to match Stitch designs. Use Stitch HTML as scaffolding, rebuild into reusable Next.js components.

**Spec-Kit Workflow:**
```
For each of 006, 007, 008:
  1. /speckit.specify  — Frontend-specific spec referencing Stitch screens  
  2. /speckit.clarify  — Resolve component structure, responsive breakpoints
  3. /speckit.plan     — Component tree, state management, API integration
  4. /speckit.tasks    — Task breakdown with Stitch screen references
  5. /speckit.implement — Build components from Stitch scaffolding
```

**Key deliverables:**
- 006: Homepage, tour listing, tour detail, category pages, destination pages (desktop + mobile)
- 007: Checkout flow (multi-step), booking confirmation
- 008: Stripe Elements integration, payment confirmation

**Stitch screens to scaffold from:** 20+ screens across desktop and mobile

---

### Phase 2: Traveler Account (Spec 015)

**Dependencies satisfied:** ✅ 001 (Auth), ✅ 007 (Booking)

**Stitch screens:**
- Customer Dashboard (Desktop + Mobile)
- My Bookings (Desktop + Mobile)
- Account Settings (Desktop + Mobile)
- Profile Settings (Desktop + Mobile)
- Booking Detail with voucher download

---

### Phase 3: Reviews (Spec 009)

**Dependencies:** ✅ 007 (Booking), ✅ 008 (Payment)

**Stitch screens:**
- Review sections within Tour Details pages
- Reviews Moderation Admin (Filament reference)

---

### Phase 4: Partner Dashboard (Specs 010–012)

**Dependency chain:** 010 → 011 → 012

**Stitch screens:**
- Partner Dashboard Overview (Desktop + Mobile)
- My Tours Portal (Desktop + Mobile)
- Create New Tour, Tour Editor
- Availability Slots Management

---

### Phase 5: Admin Moderation (Spec 013)

**Approach:** Laravel Filament resources styled to match Stitch admin designs

**Stitch screens (as reference):** 10+ admin screens for layout/UX guidance

---

### Phase 6: Notifications & Vouchers (Spec 014)

**Primarily backend** — email templates, PDF generation.
Frontend touchpoint: voucher download button on booking detail page (built in Phase 2).

---

### Phase 7: New Features (Specs 016–017)

#### 016 — Blog & Travel Insights
```
/speckit.specify Create the blog and travel insights specification for 
Bookly. This is a content-driven section for SEO and traveler engagement.

Stitch reference screens:
- Travel Insights Desktop Blog
- Travel Insights Mobile Blog

The spec MUST define:
- Blog listing page with categories, featured posts, pagination
- Individual blog post page with rich content, related tours
- SEO optimization (structured data, meta tags, Open Graph)
- Content authoring (admin CMS via Filament or markdown-based)
- Multi-language support (EN, ES, IT)
- Integration with tour discovery (link blog posts to tours)
- Mobile-responsive layouts
```

#### 017 — Wishlist / Saved Tours
```
/speckit.specify Create the wishlist and saved tours specification for
Bookly. Travelers can save tours they're interested in for later.

Stitch reference screens:
- My Wishlist - BooklyTravel (Desktop)
- My Wishlist Mobile

The spec MUST define:
- Save/unsave tour action (heart icon on tour cards and detail pages)
- Wishlist page in traveler dashboard (list of saved tours)
- Guest vs authenticated behavior (require account to save)
- Wishlist persistence and limits
- Tour card display with availability status
- Empty states
- Mobile-responsive layouts
```

---

## Component Architecture (Stitch → Next.js)

When scaffolding from Stitch HTML, extract into this structure:

```
frontend/src/
├── components/
│   ├── ui/                    ← Design system (from Stitch design tokens)
│   │   ├── Button.tsx
│   │   ├── Card.tsx
│   │   ├── Input.tsx
│   │   ├── Badge.tsx
│   │   ├── Modal.tsx
│   │   └── ...
│   ├── layout/                ← Shared layout components
│   │   ├── Header.tsx
│   │   ├── Footer.tsx
│   │   ├── Sidebar.tsx
│   │   └── MobileNav.tsx
│   ├── tours/                 ← Tour-specific components
│   │   ├── TourCard.tsx
│   │   ├── TourGrid.tsx
│   │   ├── TourDetail.tsx
│   │   ├── TourGallery.tsx
│   │   ├── TourFilters.tsx
│   │   └── SearchBar.tsx
│   ├── booking/               ← Booking components
│   │   ├── CheckoutStepper.tsx
│   │   ├── BookingCard.tsx
│   │   ├── BookingDetail.tsx
│   │   └── PaymentForm.tsx
│   ├── account/               ← Traveler account
│   │   ├── DashboardStats.tsx
│   │   ├── BookingList.tsx
│   │   ├── ProfileForm.tsx
│   │   └── WishlistGrid.tsx
│   └── partner/               ← Partner dashboard
│       ├── PartnerStats.tsx
│       ├── TourForm.tsx
│       ├── TourList.tsx
│       └── AvailabilityCalendar.tsx
```

**Design token extraction from Stitch design system:**
- Primary: `#0A2540` (Navy)
- Secondary: `#FFB800` (Gold) 
- Background: `#F7F9FB` (Off-white)
- Font: Inter (all weights)
- Radius: 8px default, 12px cards
- Spacing: 8px base grid

---

## Verification Plan

### Per-Phase Verification
- Visual diff against Stitch screenshots
- Lighthouse Performance ≥ 90 for public pages
- Mobile responsiveness tested at 390px and 780px breakpoints
- API integration tests with backend
- i18n verified for EN, ES, IT routes

### End-to-End
- Full booking flow: homepage → search → detail → checkout → payment → confirmation
- Partner flow: login → dashboard → create tour → manage availability
- Traveler flow: login → dashboard → view bookings → download voucher → write review
