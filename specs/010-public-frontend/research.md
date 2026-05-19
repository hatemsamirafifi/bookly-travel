# Research: Public Frontend — Technology Decisions

**Feature**: 010-public-frontend | **Date**: 2026-05-19

## 1. Internationalization (i18n)

**Decision**: `next-intl` with Next.js App Router localized pathnames

**Rationale**:
- First-class App Router support with `createNavigation()` and pathname-based routing
- Type-safe message keys via TypeScript integration
- Built-in locale detection, prefix-based routing (`/en/`, `/es/`, `/it/`), and `<Link>` component
- Rich formatting: dates, numbers, plurals, ICU message syntax

**Alternatives considered**:
- `next-i18next`: Legacy Pages Router focus; heavier setup; i18next familiarity but less App Router native
- Custom solution: More control but significant dev overhead; i18n is well-solved by `next-intl`

**Implementation note**: Translation JSON files at `messages/en.json`, `messages/es.json`, `messages/it.json`. Locale redirect middleware at `middleware.ts`.

## 2. Stripe Elements Integration

**Decision**: `@stripe/react-stripe-js` + `@stripe/stripe-js` with Elements provider wrapping the payment step

**Rationale**:
- Official Stripe React bindings; PCI-compliant card capture by default
- `Elements` provider loads Stripe.js once; `CardElement` renders the hosted iframe
- Supports `confirmCardPayment()` for client-side confirmation with idempotency keys
- Test mode cards (`4242 4242 4242 4242`) for e2e testing

**Alternatives considered**:
- Stripe Checkout (hosted page): Redirect-based flow; simpler but less integrated UX
- Custom card form: Requires PCI SAQ-D compliance; strictly avoided

**Implementation note**: Publishable key via `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`. Payment intent created by backend; frontend confirms and polls for status.

## 3. Checkout Session State

**Decision**: Zustand with `persist` middleware (sessionStorage backend)

**Rationale**:
- Lightweight (~1KB) state management; no complex boilerplate
- `persist` middleware with `sessionStorage` provides page refresh and back-navigation survival
- Selective persistence: tour, date, participants, details, step — excludes payment data and tokens
- Auto-clears on browser close (sessionStorage lifecycle)

**Alternatives considered**:
- React Context + useReducer: Simpler but no built-in persistence; loses state on refresh
- Redux Toolkit: Overkill for single checkout flow; heavier bundle
- Server-side draft bookings: Adds backend complexity; unnecessary for MVP guest flow

**Implementation note**: Store shape documented in data-model.md. Sensitive fields (card details, tokens) explicitly excluded from persistence.

## 4. Server-State Caching (API Data)

**Decision**: TanStack Query (React Query v5)

**Rationale**:
- Declarative data fetching with automatic caching, background refetch, and stale-while-revalidate
- Built-in pagination, infinite queries (for tour listing "Load More"), and optimistic updates
- Devtools for debugging in development
- Pairs with Redis cache on backend for API response caching

**Alternatives considered**:
- SWR: Simpler API but less feature-rich for pagination and mutations; Vercel-native
- fetch + useState: No caching; reinvented wheel

**Implementation note**: Query keys scoped by locale. Stale time default 30s for search results, 5min for tour details.

## 5. Cookie Consent (GDPR)

**Decision**: `react-cookie-consent` with custom styling to match Stitch design

**Rationale**:
- Lightweight, zero-dependency banner component
- Configurable: accept/reject buttons, cookie name, expiration
- Non-essential scripts (Sentry, analytics) gated behind consent check
- Works with Next.js App Router (client component wrapper)

**Alternatives considered**:
- Cookiebot / OneTrust: Full CMP solutions; heavy for MVP; monthly cost
- Custom banner: More development time; `react-cookie-consent` is production-proven

**Implementation note**: Consent stored as cookie. Non-essential scripts loaded conditionally based on consent value.

## 6. Error Monitoring (Observability)

**Decision**: `@sentry/nextjs` with PII exclusion

**Rationale**:
- Official Next.js SDK; automatic instrumentation of route handlers, API routes, and client-side errors
- Source maps uploaded during build for readable stack traces
- `beforeSend` hook strips PII (names, emails, phone numbers) from events
- Contextual metadata: booking ID, payment ID, environment attached to payment/checkout errors

**Alternatives considered**:
- LogRocket: Session replay; PII risk and heavier
- Custom logging: Reinventing wheel; Sentry is standard

**Implementation note**: `SENTRY_DSN` env variable. `sentry.client.config.ts` and `sentry.server.config.ts` for client/server-side config. Error boundary component wraps checkout flow.

## 7. Image Optimization

**Decision**: `next/image` with `remotePatterns` for Cloudflare R2 + `plaiceholder` for blur placeholders

**Rationale**:
- `next/image` provides automatic WebP/AVIF conversion, lazy loading, and responsive `srcSet`
- `remotePatterns` in `next.config.ts` whitelists R2 public domain
- `plaiceholder` generates Base64 blur hashes at build time for progressive loading (LQIP)
- Satisfies SC-010 (swipeable gallery) and the edge case for slow image loading

**Alternatives considered**:
- `blurhash`: Lighter encoding; `plaiceholder` is simpler for static site generation
- Direct `<img>`: No optimization; fails Lighthouse performance targets

**Implementation note**: Tour images served from R2 CDN. Placeholder blurs generated at build time or via API.

## 8. Accessibility Testing

**Decision**: `@axe-core/playwright` integrated into Playwright e2e test suite

**Rationale**:
- Automated WCAG 2.1 AA violation detection per page
- Runs as part of e2e test pipeline; fails CI on violations
- Complements manual testing and Lighthouse accessibility audit (target ≥ 90)

**Alternatives considered**:
- `cypress-axe`: Cypress-based; team has Playwright in stack
- `pa11y`: Separate tooling; adds complexity

**Implementation note**: Every page route gets an axe scan in e2e tests. Keyboard navigation and screen reader tests added for critical flows.

## 9. API Client Pattern

**Decision**: Thin fetch wrapper with typed request/response types — no heavy API client library

**Rationale**:
- Next.js server components use `fetch` directly; client components use a shared utility
- TypeScript interfaces for all API responses ensure type safety
- Sanctum CSRF cookie handled before auth-required requests
- Error handling consistent across all service calls

**Alternatives considered**:
- Axios: Adds bundle weight; `fetch` is native and sufficient
- tRPC: Not applicable (Laravel backend, not TypeScript server)

## Summary of Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| next | ^16.x | App Router framework |
| next-intl | ^4.x | i18n routing + translations |
| @stripe/react-stripe-js | ^3.x | Stripe Elements React bindings |
| @stripe/stripe-js | ^5.x | Stripe.js loader |
| zustand | ^5.x | Checkout session state |
| @tanstack/react-query | ^5.x | Server-state caching |
| @sentry/nextjs | ^9.x | Error monitoring |
| react-cookie-consent | ^9.x | GDPR cookie banner |
| plaiceholder | ^3.x | Blur placeholder generation |
| tailwindcss | ^4.x | Utility-first CSS |
| @axe-core/playwright | ^4.x | Accessibility assertions |
| playwright | ^1.x | E2E testing |
| jest | ^30.x | Unit testing |
