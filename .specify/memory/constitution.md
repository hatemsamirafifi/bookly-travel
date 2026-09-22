<!--
  ============================================================
  SYNC IMPACT REPORT
  ============================================================
  Version change : 1.1.0 -> 2.0.0
  Bump rationale : MAJOR — the previous constitution explicitly
                   prohibited automated partner payouts. Stripe
                   Connect marketplace payments and payout readiness
                   are now implemented on main, so that prohibition
                   is removed. This amendment also makes design-system,
                   accessibility, localization, compatibility, and
                   evidence-based quality rules constitutional gates
                   for the full-product UI redesign.
  Ratified       : 2026-04-10
  Last Amended   : 2026-09-21

  Modified sections:
    - Core Principles (consolidated marketplace, booking, publishing,
      commerce, architecture, experience, and verification rules)
    - Architecture & Approved Stack (aligned with current manifests)
    - Security & Data Protection (expanded marketplace isolation and
      financial-flow requirements)
    - Delivery Workflow & Quality Gates (added Spec Kit traceability,
      UI acceptance, regression, and deployment gates)
    - Governance (clarified exceptions and historical version pins)

  Added sections:
    - Bookly Design System & Originality
    - Accessibility, Localization & Responsive Behavior
    - UI Acceptance Contract
    - Compatibility and incremental-delivery gates under Delivery Workflow

  Removed or superseded:
    - Phase 1 prohibition on automated partner payouts
    - "multi-language support planned" wording; EN/ES/IT are active
    - Unversioned or inaccurate stack descriptions

  Propagation:
    - docs/PRD.md — updated to v2.1.0 and aligned to the current
      Spec 017/018 status and Constitution v2.0.0.
    - docs/Phase 2 Implementation Plan.md — updated to v2.0.0,
      renumbered against the delivered repository baseline, and linked
      to Constitution v2.0.0.
    - docs/bookly-ui-redesign-spec-kit-master-plan.md — updated to cite
      Constitution v2.0.0 and require a Constitution Check.
    - .specify/templates/plan-template.md — compatible; it already
      contains a Constitution Check gate.
    - .specify/templates/spec-template.md — compatible; no version token.
    - .specify/templates/tasks-template.md — compatible; no version token.
    - Historical specs and Phase 1 planning documents remain pinned to
      the constitution version that governed their delivery.

  Follow-up TODOs: none
  ============================================================
-->

# Bookly Constitution

## Core Principles

### I. Marketplace Integrity and Tours-Only Scope

Bookly MUST remain a multi-partner marketplace dedicated to tours and
activities.

- Every publicly bookable tour MUST belong to an authorized partner.
- Partners control their own tour content, pricing, and availability, subject
  to ownership policies and platform governance.
- The platform MUST mediate bookings and payments initiated through Bookly;
  partners MUST NOT bypass the platform for those bookings.
- Hotels, flights, restaurants, forums, rewards programs, native applications,
  and unrelated travel verticals require an explicit constitution amendment
  before implementation.
- A feature MUST have a clear tours-marketplace use case. Generic platform
  work without a traced product requirement is out of scope.

**Rationale:** A narrow marketplace boundary prevents feature drift and keeps
data models, search, commerce, and user journeys coherent.

### II. Booking, Publishing, and Review Integrity

Bookly MUST preserve trustworthy and deterministic marketplace behavior.

- Tours MUST require admin approval before public visibility. Supported tour
  lifecycle states MUST include `draft`, `pending_review`, `published`,
  `rejected`, and `archived`.
- Only `published` tours MAY appear in public discovery or be newly booked.
- Bookings MUST use direct confirmation. Inquiry-only, request-to-book, and
  waitlist flows require a constitution amendment.
- UI changes MUST NOT fabricate prices, fees, discounts, availability,
  cancellation rights, ratings, or inventory that the backend does not
  provide.
- A traveler MAY review a tour only through a completed booking, with no more
  than one traveler review per booking.
- Partner responses and admin moderation MUST retain authorization and audit
  controls.

**Rationale:** Marketplace conversion is valuable only when the published
inventory, price, booking result, and social proof are reliable.

### III. Platform-Controlled and Auditable Commerce

All Bookly commerce MUST be processed through platform-controlled payment
flows and MUST be financially auditable.

- Stripe is the approved gateway. Stripe Connect MAY route marketplace funds
  to eligible connected partners while retaining the configured platform fee.
- Every charge, refund, fee, transfer, payout-state change, and booking payment
  transition MUST be traceable through persisted payment and/or ledger data.
- Money MUST be calculated and persisted in integer minor units with an
  explicit currency. Floating-point arithmetic is prohibited for financial
  calculations.
- Booking creation, payment intent creation, webhook processing, refunds, and
  partner settlement operations MUST be idempotent.
- A retry MUST NOT create a duplicate booking, charge, refund, transfer,
  invoice, or ledger effect.
- Payment provider status MUST be confirmed server-side; client success UI is
  never authoritative.
- Checkout redesigns MUST preserve Stripe Elements behavior, server-calculated
  totals, price-drift handling, cancellation behavior, confirmation, and
  recoverable error paths.

**Rationale:** Financial correctness and replay safety are non-negotiable in a
multi-party marketplace.

### IV. API-First Boundaries and Strict Authorization

The Laravel backend MUST remain the authoritative source for business rules,
authorization, persistence, and public contracts.

- Public, authentication, traveler, checkout, and partner surfaces MUST use
  the Laravel API and render through Next.js.
- Laravel Filament is the sole server-rendered exception and is restricted to
  authenticated internal administration at `/admin`.
- The Filament exception MUST NOT be extended to public or partner-facing
  application surfaces without a constitution amendment.
- Every protected operation MUST enforce authentication, role or permission,
  and resource ownership as applicable.
- Partner isolation MUST be enforced server-side. Client filtering is not an
  authorization control.
- Controllers and Filament actions MUST remain thin and delegate business
  rules to domain Actions or Services.
- Controllers MUST NOT perform direct database queries for business workflows.
- Cross-domain behavior MUST use explicit service or action boundaries rather
  than reaching into another domain's persistence internals.

**Rationale:** Stable contracts and centralized authorization prevent UI work
from duplicating or weakening business rules.

### V. Bookly Design System and Originality

Every web surface MUST present one coherent Bookly product identity.

- Tripadvisor MAY be used as a reference for information hierarchy,
  interaction density, discovery patterns, gallery behavior, and booking CTA
  placement. Bookly MUST NOT copy Tripadvisor source code, trademarks, logo,
  proprietary text, imagery, or advertising surfaces.
- Bookly navy `#0A2540` and gold `#FFB800` are brand anchors. Components MUST
  consume semantic design tokens instead of proliferating raw color values.
- Green is reserved for trust, availability, and success semantics; it MUST
  NOT replace Bookly's primary brand identity.
- Typography, spacing, radii, shadows, borders, focus styles, and interactive
  states MUST be defined through shared tokens and reusable primitives.
- Shared shells and primitives MUST cover public, auth, traveler, checkout,
  partner, and Filament admin surfaces. A one-off component MUST be justified
  when an existing primitive cannot express the required behavior.
- A visual redesign MUST preserve the meaning and outcome of existing booking,
  payment, authentication, publishing, review, and partner workflows unless a
  separate approved requirement changes that behavior.

**Rationale:** Structural inspiration is useful; imitation and fragmented
styling create legal, maintenance, accessibility, and brand risks.

### VI. Accessibility, Localization, and Responsive Behavior

Accessibility and localization are release requirements, not post-release
polish.

- New and redesigned UI MUST target WCAG 2.1 AA.
- All actions MUST be keyboard operable with visible focus, correct semantics,
  accessible names, sufficient contrast, and announced validation or error
  feedback.
- Motion MUST respect reduced-motion preferences. Information MUST NOT depend
  on color, hover, or animation alone.
- Supported customer-facing locales are English (`en`), Spanish (`es`), and
  Italian (`it`). New user-visible copy MUST ship in all three locales.
- User-visible copy MUST NOT be hardcoded when it belongs in the localization
  catalog. Locale-prefixed routes, canonical URLs, and alternate-language
  metadata MUST remain correct.
- Primary journeys MUST be accepted at 390px, 768px, 1024px, and 1440px.
- Each data-driven surface MUST define loading, empty, error, success, disabled,
  and permission-denied behavior where applicable.
- Desktop sticky booking behavior MUST have a usable mobile equivalent; no
  critical action may exist only at one viewport.

**Rationale:** A marketplace journey is incomplete when it works only for one
device, language, or interaction method.

### VII. Evidence-Based Quality and Regression Safety

No feature is complete solely because its implementation compiles or matches a
mockup.

- Every requirement MUST be traceable from specification to implementation and
  verification evidence.
- Critical booking, payment, authentication, authorization, publishing,
  review, and partner-isolation flows MUST have automated regression coverage.
- API contract changes MUST have backend feature or contract tests and matching
  frontend type updates.
- Shared UI primitives MUST have focused component tests; primary user journeys
  MUST have browser-level coverage.
- Accessibility checks MUST include automated scanning and manual keyboard and
  focus verification on critical journeys.
- Primary public pages MUST target a Lighthouse Performance score of at least
  90 from a production build under documented test conditions. A failed or
  unreachable Lighthouse run is not evidence of compliance.
- A quality claim MUST cite reproducible test output, a repository artifact, or
  a documented manual acceptance result. Assumptions are not evidence.

**Rationale:** Tests and measured acceptance criteria protect the marketplace
from visually attractive regressions in high-risk workflows.

## Architecture and Approved Stack

The approved implementation families, verified from project manifests at
Constitution v2.0.0, are:

| Layer | Approved technology |
|---|---|
| Public, auth, traveler, checkout, partner UI | Next.js 16.2.x, React 19.2.x, TypeScript 5+, Tailwind CSS 4 |
| Localization | next-intl 4; `en`, `es`, `it` |
| Backend API | Laravel 11 on PHP `^8.2` |
| Authentication | Laravel Sanctum 4 |
| Internal admin | Laravel Filament 3.3; internal-only exception |
| Database | PostgreSQL |
| Cache and queues | Redis |
| Search abstraction | Laravel Scout 11 with Meilisearch support |
| Payments | Stripe PHP 13; Stripe.js and React Stripe.js |
| Frontend verification | Jest, Playwright, axe integration, Lighthouse CI |
| Backend verification | Pest, PHPUnit, Laravel Pint, Larastan/PHPStan |

Patch and minor dependency upgrades within these families do not require a
constitution amendment. Replacing a framework family, moving a public or
partner surface away from the API-first boundary, or introducing another
payment gateway requires an approved architecture decision and a constitution
amendment when it changes a principle.

### Backend Structure

- Server-side writes MUST use Laravel Form Requests or an equivalent validated
  input boundary.
- Business logic belongs in domain Actions or Services, not controllers,
  middleware, Eloquent model event side effects, or UI resources.
- Search-heavy public discovery MUST use the Scout abstraction rather than
  coupling controllers to database-specific full-text behavior.
- Long-running or non-blocking notification, indexing, report, and image work
  MUST use queued jobs. Jobs MUST be safe to retry.
- Migrations MUST preserve existing production data, define rollback behavior,
  and avoid destructive removal in the same release that introduces a
  replacement.

### Public Contracts and SEO

- Existing public URLs, locale prefixes, canonical behavior, and API response
  fields MUST remain backward-compatible during the UI redesign.
- Additive API evolution is preferred. Removing or changing a field requires a
  migration plan, updated consumers, contract tests, and explicit approval.
- Indexable public pages MUST provide localized metadata, canonical URLs,
  alternate-language links, and appropriate structured data where supported by
  the page's actual content.
- Structured data MUST describe visible, truthful content and MUST NOT invent
  ratings, offers, prices, availability, or operator claims.

## Security and Data Protection

- Secrets MUST come from environment configuration or an approved secret store
  and MUST NOT be committed, logged, embedded in client bundles, or copied into
  documentation.
- Public APIs MUST NOT expose partner contact details, tax data, payout data,
  Stripe identifiers, internal user IDs, or private operational notes unless a
  reviewed contract explicitly requires the field for the authenticated actor.
- File uploads MUST validate MIME type, size, filename or key construction, and
  authorization. Executable content and path traversal are prohibited.
- Stripe webhooks MUST verify provider signatures before processing and MUST
  deduplicate provider event IDs.
- Audit records are mandatory for governed admin actions, tour publishing
  decisions, booking status transitions, financial operations, and material
  partner-account changes.
- Audit records MUST identify the actor, action, target, timestamp, and relevant
  before/after state without storing secrets.

## UI Acceptance Contract

Every redesigned route MUST be reviewed against all applicable items below:

1. Content hierarchy matches the approved feature specification.
2. Shared tokens and primitives are used; unexplained raw styles are rejected.
3. Keyboard, focus, screen-reader naming, contrast, and reduced motion pass.
4. EN/ES/IT render without missing keys, clipping, or hardcoded fallback copy.
5. Loading, empty, error, success, and disabled states behave intentionally.
6. Layout is accepted at 390px, 768px, 1024px, and 1440px.
7. API errors and authorization failures do not leak private data.
8. Booking and payment totals come from authoritative backend responses.
9. SEO metadata and structured data match visible content on public pages.
10. Before/after screenshots or equivalent visual evidence are retained for
    the primary acceptance matrix.

## Delivery Workflow and Quality Gates

### Specification and Planning

1. Work MUST start from an up-to-date `main` with repository prerequisites
   verified.
2. A Spec Kit feature MUST contain a complete `spec.md`, `plan.md`, and
   `tasks.md`; research, data model, contracts, and quickstart artifacts are
   required when the feature changes those concerns.
3. Each plan MUST include a Constitution Check against the active version
   before implementation and again before release.
4. Requirements MUST be objective and testable. `TBD`, unresolved
   `NEEDS CLARIFICATION`, and invented backend behavior block execution.
5. Deviations from this constitution MUST be recorded in the plan's Complexity
   Tracking section with rationale and a rejected simpler alternative.

### Implementation

1. Changes MUST be delivered in independently testable phases with focused
   commits.
2. Shared foundations and contracts MUST precede dependent page work.
3. API and database additions MUST remain compatible with the currently
   deployed frontend until dependent frontend changes are released.
4. New behavior MUST include tests in the same phase. Regression tests MUST be
   added before fixing a reproducible defect in a critical workflow.
5. Generated or AI-assisted code receives the same review, test, security, and
   accessibility requirements as manually written code.

### Verification

Run the gates applicable to the changed surface from the relevant project
directory:

```text
frontend: npm run lint
frontend: npm run typecheck
frontend: npm test
frontend: npm run test:e2e
frontend: npm run test:a11y
frontend: npm run build

backend:  php artisan test
backend:  vendor/bin/pint --test
backend:  vendor/bin/phpstan analyse
```

- Critical cross-surface changes MUST run both frontend and backend gates.
- A failing gate MUST be fixed or explicitly documented as a pre-existing,
  independently reproduced issue. It MUST NOT be silently ignored.
- Release evidence MUST include the commands executed, their result, and any
  intentionally deferred non-blocking finding.

### Deployment and Rollback

- Database migrations MUST be applied before code that requires their schema.
- Additive backend contracts MUST deploy before frontend consumers.
- Frontend rollback MUST NOT require deleting newly written production data.
- Destructive schema cleanup requires a separate follow-up after adoption and
  rollback safety are verified.
- Booking errors, payment errors, API latency, and client exceptions MUST be
  monitored during rollout.

## Governance

This constitution is the highest-priority engineering and product-delivery
policy in the repository. Feature specifications and plans MAY impose stricter
rules but MUST NOT weaken it.

### Amendment Procedure

1. Write the proposed change and rationale.
2. Identify affected principles, active specifications, templates, contracts,
   migrations, and operational procedures.
3. Obtain explicit project-owner approval.
4. Apply a semantic version bump and update the Sync Impact Report.
5. Propagate the amendment to active dependent documentation before work that
   relies on it begins.

### Versioning Policy

- **MAJOR:** Removes or incompatibly redefines a principle, product boundary,
  or mandatory governance rule.
- **MINOR:** Adds a principle or materially expands mandatory guidance without
  invalidating an existing rule.
- **PATCH:** Clarifies wording, fixes an error, or makes a non-semantic
  refinement.

Historical specifications MAY retain the constitution version that governed
their original delivery. Active specifications and new amendments MUST cite the
current version.

### Compliance Review

- Every active feature plan MUST record its Constitution Check result.
- Code review MUST verify the principles affected by the change, not merely
  assert generic compliance.
- A release MUST NOT be declared complete while a non-negotiable constitutional
  gate is knowingly failing.
- Exceptions require written owner approval, bounded scope, an expiry or
  remediation plan, and entry in the feature plan's Complexity Tracking table.

**Version**: 2.0.0 | **Ratified**: 2026-04-10 | **Last Amended**: 2026-09-21
