# Bookly — Phase 2 Implementation Plan

> **Version**: 2.0.0
> **Date**: 2026-09-22
> **Status**: Active — Spec 017 merged; Spec 018 is the next feature
> **Governing Constitution**: [Bookly Constitution v2.0.0](../.specify/memory/constitution.md)
> **Authoritative product source**: [PRD v2.1.0](PRD.md)

---

## 1. Purpose and Numbering Decision

This plan replaces the original pre-implementation Phase 2 roadmap with a
repository-aligned sequence.

- Specs `001–016` remain the delivered Phase 1 sequence.
- Spec `017` is assigned to the Stripe payments, Connect, and invoicing work
  already merged into `main` through PR #26.
- Spec `018` is assigned to the Bookly full-product UI redesign.
- Remaining Phase 2 features are renumbered `019–025`.
- Partner review responses are removed from the future roadmap because their
  database, API, and partner UI already exist.
- Refunds, pricing tiers, and partner analytics are treated as existing
  baselines to extend, not missing systems to rebuild.

The code for Spec 017 was delivered outside the numbered Spec Kit workflow.
That retrospective documentation gap is now closed by
`specs/017-stripe-connect-invoicing/`; it records the merged implementation,
its boundaries, its 2026-09-22 Constitution v2 hardening, and the verified test
results.

---

## 2. Verified Repository Baseline

Verified on 2026-09-21:

| Item | Verified state |
|------|----------------|
| `main` / `origin/main` | `11c534e` — merge of PR #26 |
| Stripe implementation commit | `76b1f1e` |
| Existing numbered spec directories | `001–016` |
| Stripe Connect | Express account creation, onboarding link, account sync, dashboard login, destination charges, application fees |
| Stripe invoicing | Hosted invoice service plus invoice webhook handling |
| Payment methods | Stripe automatic payment methods enabled |
| Full refunds | Existing idempotent full-refund action and ledger entry |
| Partner review responses | Existing `review_responses` storage, partner API, and partner UI |
| Pricing tiers | Existing generic `pricing_tiers` CRUD; no adult/child/infant booking calculation |
| Partner analytics | Existing summary and booking/revenue chart; revenue is formatted from minor units, while unavailable conversion is labeled explicitly until view tracking exists |

### Spec 017 verification result

The original six-scenario suite passed **6 tests and 28 assertions** on
2026-09-21 after the disposable PostgreSQL service was started. On 2026-09-22,
the Constitution v2 hardening was implemented and verified: commission math is
integer-only, invoices use stable idempotency keys plus persisted Booking
linkage, Stripe account changes create governance audit records, and all three
partner Stripe endpoints have unauthenticated and non-partner denial tests. The
combined targeted backend run passed **31 tests and 97 assertions**; frontend
analytics tests, TypeScript, ESLint, Pint, and targeted PHPStan checks passed.
The broader Payment regression suite passed **59 tests and 224 assertions**.

---

## 3. Phase 2 Feature Map

| Spec | Feature | Wave | Status | Primary value |
|:----:|---------|:----:|:------:|---------------|
| `017` | Stripe Payments, Connect & Invoicing Foundation | Delivered baseline | ✅ Merged | Marketplace payment routing and B2B invoice foundation |
| `018` | Bookly Full-Product UI Redesign | 1 | 🟡 Next | Consistent, conversion-focused, accessible product experience |
| `019` | Partner Earnings & Settlement Operations | 2 | Planned | Operational visibility and reconciliation without double-paying partners |
| `020` | Cancellation Policies & Partial Refunds | 2 | Planned | Policy-driven cancellation and reduced manual support |
| `021` | Social Login (OAuth) | 2 | Planned | Lower registration friction |
| `022` | Multi-Staff Partner Accounts | 3 | Planned | Team access for tour operators |
| `023` | Participant Category Pricing | 3 | Planned | Adult/child/infant booking calculations |
| `024` | SMS & Push Notifications | 4 | Planned | Opt-in real-time reminders |
| `025` | Advanced Analytics & Reporting Exports | 4 | Planned | Conversion visibility and governed reporting |

---

## 4. Delivered Baseline — Spec 017

### Stripe Payments, Connect & Invoicing Foundation

**Delivered scope:**

- Stripe automatic payment methods for eligible account/currency/customer
  combinations.
- Stripe Express connected-account creation and hosted onboarding.
- Partner endpoints for Connect status, onboarding link, and Express Dashboard
  login link.
- `account.updated` webhook synchronization for charges, payouts, onboarding,
  and details-submitted flags.
- Destination charges using `transfer_data.destination`.
- Configurable application fee calculation recorded with payment metadata.
- Hosted Stripe invoice creation and invoice webhook processing.
- Existing payment and financial-ledger behavior retained.

**Explicitly not delivered by Spec 017:**

- Bookly-owned `partner_earnings` records.
- Partner payout or settlement history stored in Bookly.
- Admin payout hold/release workflows.
- Scheduled payout batches or transfer jobs.
- Payout reconciliation jobs and operational exception queues.

Those remaining responsibilities move to Spec 019. Because destination charges
already route funds to the connected account, Spec 019 MUST NOT create a second
transfer for the same booking. Its research phase must choose the correct
Stripe balance-transaction/payout reconciliation model before defining tables
or jobs.

### Retrospective Spec Kit artifacts — complete

The following documentation now records the merged implementation:

```text
specs/017-stripe-connect-invoicing/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   `-- stripe-connect-partner-api.md
`-- tasks.md
```

The retrospective spec distinguishes implemented behavior from later
settlement operations, cites implementation commit `76b1f1e` and merge commit
`11c534e`, and records the successful database-backed test rerun.

---

## 5. Wave 1 — Spec 018 Bookly Full-Product UI Redesign

**Status:** Next feature. The authoritative implementation plan is
[bookly-ui-redesign-spec-kit-master-plan.md](bookly-ui-redesign-spec-kit-master-plan.md).

### Objective

Redesign all Bookly web surfaces using Tripadvisor-inspired information
hierarchy and interaction density while retaining Bookly's navy/gold identity,
tours-only scope, and existing business behavior.

### Scope

- Semantic design tokens and reusable primitives.
- Shared public, auth, traveler, checkout, and partner shells.
- Filament admin theme and consistent governance surfaces.
- Tripadvisor-inspired tour detail information architecture without copying
  trademarks, source, text, advertising, or imagery.
- Structured itinerary, complete gallery, operator summary, difficulty, and
  related-tour API additions.
- Homepage, search, categories, destinations, blog, legal, verification, error,
  auth, traveler, checkout, partner, and admin redesigns.
- EN/ES/IT completeness, WCAG 2.1 AA, and acceptance at 390px, 768px, 1024px,
  and 1440px.
- Preservation of Stripe Elements, automatic payment methods, Connect status,
  server-calculated totals, idempotency, price-drift handling, and confirmation
  behavior.

### Required artifacts

```text
specs/018-bookly-ui-redesign/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
|-- contracts/
|   |-- tour-detail-api.md
|   `-- partner-tour-content-api.md
`-- tasks.md
```

### Exit gate

- Every web route is mapped to the new design system or an approved exception.
- Critical booking/payment/auth/publishing/review flows retain regression
  coverage.
- Primary visual acceptance matrices pass at all target viewports.
- EN/ES/IT, accessibility, frontend/backend tests, production build, and
  documented Lighthouse runs meet the Constitution v2.0.0 gates.

---

## 6. Wave 2 — Financial Completion and Growth

Wave 2 starts after Spec 018 establishes stable shared UI primitives. Backend
research and contract design may begin earlier, but customer-facing UI must use
the Spec 018 system.

### Spec 019 — Partner Earnings & Settlement Operations

**Objective:** Add Bookly-owned operational records and reconciliation around
the Stripe Connect destination-charge model delivered in Spec 017.

**Required outcomes:**

- One auditable partner earning record per eligible paid booking.
- Gross, platform fee, partner net, currency, Stripe references, and lifecycle
  state stored in integer minor units.
- Partner earnings and settlement-history APIs scoped by ownership.
- Admin exception queue for missing, restricted, reversed, or unreconciled
  settlement events.
- Hold/release semantics only where they match the selected Stripe funds-flow
  model.
- Retry-safe webhook and reconciliation processing.
- No duplicate transfer for bookings already processed as destination charges.

**Research gate:** Map PaymentIntent, charge, transfer, balance transaction,
connected-account balance, and payout objects before finalizing the data model.

### Spec 020 — Cancellation Policies & Partial Refunds

**Objective:** Extend the existing idempotent full-refund flow with explicit,
versioned tour cancellation policies and partial refunds.

**Required outcomes:**

- Tour-level policy configuration with a safe default for existing tours.
- Policy snapshot attached to each booking so later policy edits do not change
  an existing booking's rights.
- Server-calculated refund preview and final amount.
- Full, partial, and no-refund windows with integer minor-unit calculations.
- Traveler/admin UI that explains the applied rule before confirmation.
- Idempotent Stripe refund initiation and webhook reconciliation.
- Existing full-refund behavior remains backward-compatible.

### Spec 021 — Social Login (OAuth)

**Objective:** Add Google and Facebook sign-in without weakening the current
email/password, guest checkout, verification, or return-URL behavior.

**Required outcomes:**

- OAuth state validation and bounded callback URLs.
- Secure provider identity storage without exposing provider tokens to the
  browser or logs.
- Explicit account-linking rules for matching email addresses.
- Sanctum session issuance through the existing authentication boundary.
- EN/ES/IT login and error states.
- Guest checkout remains available without OAuth.

---

## 7. Wave 3 — Partner Operations and Pricing

### Spec 022 — Multi-Staff Partner Accounts

**Objective:** Allow approved partners to invite staff while retaining strict
tenant isolation.

**Required outcomes:**

- Owner, manager, and staff roles with an explicit capability matrix.
- Invitation, acceptance, revocation, role-change, and last-owner safeguards.
- Existing single-owner partners migrate without behavior change.
- Every partner query scopes by membership and resource ownership.
- Sensitive earnings, Connect, and settlement permissions remain separately
  controllable.
- Material membership and role changes are audited.

### Spec 023 — Participant Category Pricing

**Objective:** Extend existing generic `pricing_tiers` into booking-time
adult/child/infant pricing without replacing the table blindly.

**Required outcomes:**

- Research whether existing tiers can be extended additively or require a new
  normalized category model.
- Participant category, age rules, minimum/maximum counts, and tour-level
  currency constraints.
- Server-authoritative price calculation and immutable booking price snapshot.
- Existing tours receive a compatible adult/default category.
- Search cards continue to show a truthful "from" price.
- Zero-downtime migration and rollback plan.

---

## 8. Wave 4 — Engagement and Intelligence

### Spec 024 — SMS & Push Notifications

**Objective:** Add optional SMS and browser push to the existing email and
in-app notification system.

**Required outcomes:**

- Explicit per-channel opt-in and revocation.
- Booking confirmation, cancellation, and reminder events reuse existing
  domain events rather than duplicating booking logic.
- Retry-safe queued delivery with provider message IDs and failure records.
- Quiet-hour and locale-aware message rules.
- Browser push subscription lifecycle and service-worker security review.
- No booking failure when a notification provider is unavailable.

### Spec 025 — Advanced Analytics & Reporting Exports

**Objective:** Extend the existing partner analytics summary and chart with
measured conversion data and governed exports.

**Required outcomes:**

- Privacy-reviewed tour-view and funnel event model.
- Conversion rate derived from recorded events instead of the current `0.0`
  placeholder.
- Cohort, source, tour, and date filters with partner ownership enforcement.
- CSV export and optional PDF summaries for authorized partners/admins.
- Queued generation for large exports with expiring download links.
- Retention policy and aggregation strategy that prevent unbounded event-table
  growth.

---

## 9. Dependency and Execution Graph

```text
Spec 017 merged
   |
   +---------------------> Spec 019 settlement operations
   |
   +---------------------> Spec 020 cancellation/partial refunds
   |
   v
Spec 018 design system and full-product redesign
   |
   +----------+-----------+-----------+
   v          v           v           v
Spec 021   Spec 022    Spec 023    Spec 024
 OAuth      teams       pricing     messaging
   \          |           |           /
    +---------+-----------+----------+
                      |
                      v
             Spec 025 advanced analytics
```

Rules:

- Spec 018 starts from the verified `main` containing Spec 017.
- Specs 019 and 020 depend on the Spec 017 payment contracts.
- Spec 022 must land before later features rely on staff-level permissions.
- Spec 023 must preserve existing pricing and booking compatibility during its
  migration.
- Spec 025 consumes stable events from earlier features; it must not become a
  blocker for their transactional workflows.

---

## 10. Canonical Spec Directories

| Directory | State |
|-----------|-------|
| `specs/017-stripe-connect-invoicing/` | ✅ Retrospective documentation and Constitution v2 hardening complete; targeted suites verified |
| `specs/018-bookly-ui-redesign/` | Create next through Spec Kit |
| `specs/019-partner-settlement-operations/` | Planned |
| `specs/020-cancellation-partial-refunds/` | Planned |
| `specs/021-social-login/` | Planned |
| `specs/022-multi-staff-partners/` | Planned |
| `specs/023-participant-category-pricing/` | Planned |
| `specs/024-sms-push-notifications/` | Planned |
| `specs/025-advanced-analytics/` | Planned |

No later spec may reuse these numbers without a roadmap amendment.

---

## 11. Expected Data-Model Impact

Names below are planning targets; each spec's data-model artifact owns the final
schema.

| Existing area | Planned change | Spec | Risk |
|---------------|----------------|:----:|:----:|
| Tours/content/media | Structured itinerary, difficulty, gallery ordering, operator summary contract | `018` | Medium — additive plus legacy JSONB compatibility |
| Partners/Stripe | Earnings and settlement operational records linked to existing Connect fields | `019` | High — financial reconciliation |
| Tours/bookings/payments | Versioned cancellation-policy snapshot and partial-refund metadata | `020` | High — financial correctness |
| Users/auth | Provider identities and account-linking audit data | `021` | Medium — identity collision risk |
| Partners/users | Membership and invitation model | `022` | High — tenant authorization |
| Pricing/bookings | Participant categories and booking price snapshot | `023` | High — migration and price integrity |
| Notification preferences | SMS/push consent, subscriptions, delivery attempts | `024` | Medium — privacy and provider reliability |
| Analytics/events | View/funnel events, aggregates, export jobs | `025` | Medium — privacy and data growth |

---

## 12. New Technology Decisions

No new framework is required for Spec 018. Later additions are provisional
until their research artifacts are approved:

| Candidate | Purpose | Spec | Current repository state |
|-----------|---------|:----:|--------------------------|
| Laravel Socialite + Facebook provider | OAuth abstraction | `021` | Not installed |
| SMS provider SDK | Transactional SMS | `024` | Provider not selected |
| Web Push library + service worker | Browser push | `024` | Not installed |

Stripe PHP, Next.js, React, Laravel, Redis, PostgreSQL, Scout, Filament, Jest,
Playwright, Pest, and the existing accessibility/Lighthouse tooling remain the
approved foundation under Constitution v2.0.0.

---

## 13. Cross-Cutting Gates

### Security

- Server-side authorization and partner ownership are mandatory.
- OAuth state, webhook signatures, notification consent, export access, and
  Connect/settlement operations require explicit threat review.
- No public response may expose Stripe identifiers, payout/tax data, private
  partner contacts, internal IDs, or operational notes.

### Internationalization

- Every customer-facing change ships in EN/ES/IT.
- Provider error messages are mapped to localized Bookly messages; raw provider
  errors are not shown to users.

### Accessibility and Responsive UI

- Spec 018 establishes the shared WCAG 2.1 AA and responsive contract.
- Later UI must reuse that contract at 390px, 768px, 1024px, and 1440px.

### Financial Correctness

- Integer minor units, explicit currency, idempotency, auditability, and
  webhook reconciliation are mandatory.
- Spec 019 must prove no duplicate movement of funds under the destination
  charge model.
- Spec 020 must snapshot the policy and calculated refund inputs.
- Spec 023 must snapshot participant counts, category rates, and totals.

### Backward Compatibility

- Additive backend/database changes deploy before dependent frontend changes.
- Existing routes and response fields remain available during migration.
- Destructive schema cleanup is a separate follow-up after rollback safety and
  adoption are proven.

---

## 14. Verification Plan

Each spec MUST define focused tests for its contracts and critical failure
paths. The shared repository gates are:

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

Minimum evidence by feature:

| Spec | Required evidence |
|:----:|-------------------|
| `017` | Database-backed rerun of Stripe Connect/invoicing tests and partner endpoint authorization |
| `018` | Visual matrices, responsive screenshots, accessibility checks, regression suites, production build, documented Lighthouse runs |
| `019` | Reconciliation fixtures, retry/idempotency tests, duplicate-transfer prevention, ownership tests |
| `020` | Boundary-time policy tests, full/partial/no-refund math, price/policy snapshot tests, webhook replay |
| `021` | OAuth state/callback tests, account-link collision cases, provider failure, guest checkout regression |
| `022` | Capability matrix tests, cross-partner denial, invitation/revocation, last-owner protection |
| `023` | Category calculation/property tests, migration/backfill verification, price-drift and snapshot tests |
| `024` | Consent/opt-out, queue retry, provider outage, deduplication, locale tests |
| `025` | Event accuracy, ownership, aggregation reconciliation, retention, export authorization |

An unavailable dependency is reported as blocked verification, not a passing or
failing assertion.

---

## 15. Open Decisions

The following decisions belong to their named research/spec phase and MUST NOT
be guessed during implementation:

1. **Spec 019:** Which Stripe objects are the source of truth for Bookly
   settlement history under destination charges, and which hold/release
   controls are technically valid?
2. **Spec 020:** Global default cancellation windows versus partner-configured
   policies, and the allowed partial-refund percentages.
3. **Spec 021:** Whether Facebook remains required at launch alongside Google,
   and the explicit account-linking consent flow.
4. **Spec 022:** Final owner/manager/staff capability matrix, especially access
   to earnings, Connect, refunds, and exports.
5. **Spec 023:** Fixed or per-tour age bands and whether existing generic tier
   names remain available beside participant categories.
6. **Spec 024:** SMS provider selection, supported countries, quiet hours, and
   message-retention policy.
7. **Spec 025:** Event retention window, anonymization strategy, and PDF export
   necessity versus CSV-only launch.

---

## 16. Old-to-New Roadmap Mapping

| Original item | Current disposition |
|---------------|---------------------|
| Old `017` Partner Payouts & Commission Ledger | Split: implemented Connect/invoicing foundation is new `017`; remaining operations move to `019` |
| Old `018` Automated Refunds | Renamed/renumbered to `020`; full refund exists, only policies and partial refunds remain |
| Old `019` Social Login | Renumbered to `021` |
| Old `020` Multi-Staff Partners | Renumbered to `022` |
| Old `021` Tiered Pricing | Renumbered to `023` and narrowed to participant categories because generic tiers exist |
| Old `022` Partner Review Replies | Removed from roadmap; already delivered |
| Old `023` SMS / Push | Renumbered to `024` |
| Old `024` Advanced Analytics | Renumbered to `025` and defined as an extension of existing analytics |
| New `018` Bookly UI Redesign | Inserted as the current priority |

This mapping is normative for new Spec Kit branches and directories.
