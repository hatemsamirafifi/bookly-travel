# Implementation Plan: Public Frontend — Search, Booking & Payments

**Branch**: `010-public-frontend` | **Date**: 2026-05-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-public-frontend/spec.md`

## Summary

Build the public-facing Next.js 16 frontend for Bookly — a tours-only travel marketplace with three locales (EN/ES/IT). The implementation covers homepage, tour search/discovery, tour detail pages, multi-step booking checkout with Stripe Elements payment, and auth pages — all rendered with SSR/SSG for SEO, styled to the Stitch design system, and meeting WCAG 2.1 AA accessibility standards.

## Technical Context

**Language/Version**: TypeScript 5.x (strict mode)  
**Primary Dependencies**: Next.js 16 (App Router), Tailwind CSS 4.x, next-intl (i18n), @stripe/react-stripe-js (Stripe Elements), Zustand (checkout state), TanStack Query (server-state caching), @sentry/nextjs (error monitoring), react-cookie-consent (GDPR banner), axe-core + Playwright (a11y testing)  
**Storage**: Client-side only (sessionStorage via Zustand persist middleware). No frontend database — all persistent data via Laravel API.  
**Testing**: Playwright (e2e, a11y), Jest (unit), Lighthouse CI (perf)  
**Target Platform**: Web (desktop 1280px+ and mobile 390px responsive)  
**Project Type**: web-application (Next.js SPA with SSR/SSG consuming Laravel REST API)  
**Performance Goals**: Lighthouse Performance ≥ 90, <500ms route transitions, <1s search results, <3min checkout completion  
**Constraints**: WCAG 2.1 AA, GDPR cookie consent, 3 locales (EN/ES/IT), mobile-first responsive, SSR/SSG crawlable HTML  
**Scale/Scope**: Public-facing web app, 3 locales, ~7 page types, multi-step checkout flow, ~25 API-consuming components

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| **I. Marketplace-First** | ✅ PASS | Tours displayed are owned by registered partners; all payments flow through Stripe platform account (FR-009) |
| **II. Tours-Only Discipline** | ✅ PASS | All pages serve the tours vertical exclusively — no hotels, flights, transfers |
| **III. Direct Booking Only** | ✅ PASS | Checkout flow is instant booking with Stripe payment confirmation (FR-007, FR-010); no request/waitlist patterns |
| **IV. Admin-Governed Publishing** | ✅ PASS | Only `published` tours appear in search/discovery (spec assumption: backend enforces status filtering) |
| **V. Platform-Controlled Commerce** | ✅ PASS | All payments via Stripe platform integration (FR-009); idempotency keys prevent duplicate charges (FR-020); immutable ledger handled by backend |
| **VI. Review Integrity** | ✅ PASS | Reviews displayed on tour detail page are read-only from public frontend; submission gating handled by backend (completed booking check) |
| **API-First** | ✅ PASS | Frontend consumes Laravel API exclusively — no server-rendered HTML from backend |
| **Approved Stack** | ✅ PASS | Next.js 16, TypeScript, Tailwind CSS, Stripe, all aligned with constitution |
| **SEO-First** | ✅ PASS | SSR/SSG rendering (FR-001), meta/OG/structured data (FR-011), canonical + hreflang, 3 locales |
| **Security** | ✅ PASS | Sanctum bearer tokens, Stripe Elements PCI-compliant (FR-009), idempotency keys (FR-020), rate limiting (FR-025), PII excluded from Sentry (FR-024), input validated server-side |
| **Testing** | ✅ PASS | Booking + payment flows covered by Playwright e2e, a11y audited via axe-core, perf via Lighthouse |

**Verdict**: All gates pass. No violations.

## Project Structure

### Documentation (this feature)

```text
specs/010-public-frontend/
├── plan.md              # This file
├── research.md          # Phase 0 — technology decisions
├── data-model.md        # Phase 1 — frontend entities
├── quickstart.md        # Phase 1 — dev setup guide
├── contracts/           # Phase 1 — API contracts
│   ├── tours-api.md     # Search, listing, detail endpoints
│   ├── booking-api.md   # Booking creation, payment intent
│   └── auth-api.md      # Login, register, session
└── tasks.md             # Phase 2 — /speckit.tasks output
```

### Source Code (repository root)

```text
frontend/
├── src/
│   ├── app/                   # Next.js App Router pages
│   │   ├── [locale]/          # Localized routes
│   │   │   ├── page.tsx       # Homepage
│   │   │   ├── tours/
│   │   │   │   ├── page.tsx           # Search/listing
│   │   │   │   ├── [slug]/page.tsx    # Tour detail
│   │   │   │   ├── category/
│   │   │   │   │   └── [slug]/page.tsx
│   │   │   │   └── destination/
│   │   │   │       └── [slug]/page.tsx
│   │   │   ├── checkout/
│   │   │   │   └── page.tsx           # Multi-step checkout
│   │   │   ├── confirmation/
│   │   │   │   └── page.tsx           # Booking confirmation
│   │   │   ├── auth/
│   │   │   │   ├── login/page.tsx
│   │   │   │   └── register/page.tsx
│   │   │   ├── privacy/page.tsx
│   │   │   └── terms/page.tsx
│   │   └── layout.tsx         # Root layout (providers, fonts)
│   ├── components/
│   │   ├── ui/                # Design system primitives (Button, Card, Input, etc.)
│   │   ├── layout/            # Header, Footer, Nav, LocaleSwitcher
│   │   ├── home/              # Hero, CategoryGrid, FeaturedTours
│   │   ├── search/            # SearchBar, Filters, SortSelect, TourGrid
│   │   ├── tour/              # TourCard, Gallery, AvailabilityCalendar, Reviews
│   │   ├── checkout/          # StepIndicator, DateSelect, GuestForm, PaymentForm
│   │   ├── auth/              # LoginForm, RegisterForm
│   │   └── shared/            # CookieConsent, ErrorBoundary, SEOHead
│   ├── hooks/                 # useTours, useSearch, useCheckout, useAuth
│   ├── services/              # API client functions (tours, bookings, auth)
│   ├── stores/                # Zustand stores (checkout session)
│   ├── lib/                   # Utilities, constants, design tokens
│   ├── i18n/                  # next-intl config + message files
│   └── middleware.ts          # Locale detection + redirect
├── messages/                  # Translation JSON (en.json, es.json, it.json)
├── public/                    # Static assets
├── tests/
│   ├── e2e/                   # Playwright specs
│   └── unit/                  # Jest unit tests
├── next.config.ts
├── tailwind.config.ts
├── playwright.config.ts
└── package.json
```

**Structure Decision**: Single Next.js application (Option 2: Web application). The Laravel backend lives in `backend/` as a separate project. The frontend consumes it as an external API.

## Complexity Tracking

> No violations to justify. All constitution gates pass.
