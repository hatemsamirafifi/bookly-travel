# Phase 0 Research: Tour Management

## Unknowns Resolved

No items marked "NEEDS CLARIFICATION" in the Technical Context. The following decisions are documented for traceability.

---

### Decision: Continue with Next.js App Router + existing Phase 1 stack

- **Rationale**: Spec 010 already established Next.js 16, TypeScript, Tailwind CSS, and the auth/API client. Tour Management is an extension, not a new surface.
- **Alternatives considered**: None — switching frameworks mid-project would violate constitution stack approval requirements.

### Decision: Use React Server Components (RSC) where possible, with Client Components for interactivity

- **Rationale**: Next.js 16 App Router encourages RSC for data fetching. Static metadata (SEO) and initial data fetch can be server-rendered; filters, modals, and optimistic updates require `"use client"`.
- **Alternatives considered**: SPA-only rendering — rejected because it harms SEO and initial load performance.

### Decision: Implement optimistic updates for wishlist toggle

- **Rationale**: FR-021 requires < 100ms visual feedback. An optimistic UI update followed by background API reconciliation is the standard pattern for this constraint.
- **Alternatives considered**: Await API response before updating UI — rejected because network latency would exceed the 100ms success criterion.

### Decision: Debounce wishlist toggle API calls

- **Rationale**: Edge case in spec (rapid toggle) requires preventing duplicate API requests and rate-limit issues.
- **Alternatives considered**: Throttle — rejected because debounce better handles the "rapidly click heart on/off" interaction pattern.

### Decision: Use existing Laravel Sanctum cookie-based session auth

- **Rationale**: Phase 1 already configured Sanctum with `isAuthenticated`, `user`, and `logout`. Tour Management consumes these primitives.
- **Alternatives considered**: Token-based (JWT) — rejected to maintain consistency with Phase 1 and avoid re-authing.

### Decision: Preserve return URL via query parameter (`?returnUrl=`)

- **Rationale**: FR-015 requires redirecting unauthenticated visitors to login with the intended destination preserved. Query parameter is simple, framework-agnostic, and matches existing Phase 1 login flow.
- **Alternatives considered**: Session storage — rejected because it complicates SSR and deep-link sharing.

### Decision: Mobile-first responsive design at 390px / 780px / 1280px

- **Rationale**: Explicit success criteria (SC-010). Tailwind CSS breakpoints (`sm:`, `md:`, `lg:`) map cleanly to these widths.
- **Alternatives considered**: Separate mobile/desktop builds — rejected as over-engineering for a single-codebase Next.js app.
