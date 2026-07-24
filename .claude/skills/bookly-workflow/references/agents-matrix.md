# Bookly Agents — Responsibilities Matrix

Reference for the bookly-workflow orchestrator. One row per agent; consult when assigning tasks or routing failures.

## Agent roster

| Agent | Subagent type | Owns | Produces | Never does |
|-------|---------------|------|----------|------------|
| `dev-sup-agent` | *(main loop — this skill)* | Task graph, FR-ID tagging, invariant gates, phase progression | Dispatch plan, integration verdict | Writes application code |
| `db-agent` | `db-agent` | Migrations, schema, indexes, locks | Migration files, schema payload, locking note | Controllers/resources/service logic |
| `contract-agent` | `contract-agent` | API request/response shapes, routes, rate limits | Resource classes + `docs/contracts/<fr>.json` artifact | UI; service logic; schema |
| `backend-agent` | `backend-agent` | Service layer, policies, Filament, jobs, webhooks | Controllers/Actions/Models/Policies/Filament/Pest tests | Schema (consumes db-agent); contract shape (consumes contract-agent) |
| `frontend-agent` | `frontend-agent` | App Router, React Query, Zustand, Stripe Elements, i18n keys | Pages, components, hooks, message entries | API shapes; migrations |
| `i18n-agent` | `i18n-agent` | EN/ES/IT parity, hreflang, mail template locales | Parity report | Business logic; API shapes |
| `test-agent` | `test-agent` | Test pyramid execution, critical-flow coverage | Pest/Jest/Playwright artifacts, pass/fail report | Implements features to pass tests |
| `debug-agent` | `debug-agent` | Root-cause for failures, races, Stripe/ledger drift | RCA + fix directive to owner | Edits application code |
| `review-agent` | `review-agent` | Code Review Mode, architecture-violation detection | Structured review blocks + PASS/FAIL | Edits code |
| `prd-validation-agent` | `prd-validation-agent` | FR→diff traceability, scope-creep rejection | COVERED / GAP / OUT-OF-SCOPE verdict | Implements; only validates |

## Domain → Agent ownership (PRD §7 domains)

| Domain | Primary agent |
|--------|---------------|
| Auth, Booking, Payment, Finance, Tours, Pricing, Availability, Partners, Admin Ops | `backend-agent` |
| Search (UI), Traveler (UI), Booking (UI), Notifications (UI) | `frontend-agent` |
| All domains (persistence) | `db-agent` |
| API surface (all surfaces) | `contract-agent` |
| Tours (translations), Notifications (locales) | `i18n-agent` |
| Cross-cutting (runtime failures) | `debug-agent` |
| Cross-cutting (quality) | `review-agent` |
| Cross-cutting (verification) | `test-agent` |
| Cross-cutting (compliance) | `prd-validation-agent` |

## FR-ID → Agent quick map

| FR | Lead agents |
|----|-------------|
| FR-001 Auth | db, contract, backend, frontend, test |
| FR-002 Search & Discovery | db, contract, backend, frontend, i18n, test |
| FR-003 Tour Detail | contract, backend, frontend, i18n, test |
| FR-004 Booking & Checkout | db, contract, backend, frontend, test (critical) |
| FR-005 Payment | db, contract, backend, frontend, test, debug-prone (critical) |
| FR-006 Reviews | db, contract, backend, frontend, test |
| FR-007 Traveler Account | contract, backend, frontend, i18n, test |
| FR-008 Partner Management | db, contract, backend, frontend, i18n, test |
| FR-009 Admin Moderation | db, contract, backend, test |
| FR-010 Notifications & Vouchers | db, contract, backend, i18n, test |

## Dispatch routing rules

- **DB-First**: `db-agent` runs before `contract-agent`. Both run before any implementation.
- **Contract-Before-UI**: `contract-agent` publishes before `backend-agent` and `frontend-agent` start.
- **Parallel Phase B**: `backend-agent` ∥ `frontend-agent` after contract published.
- **Parallel Phase C**: `i18n-agent` ∥ `test-agent` after implementations report ready.
- **Failure routing**: failing test/finding → `debug-agent` → owning agent → `test-agent` re-run.
- **Verdict routing**: `review-agent` + `prd-validation-agent` → `dev-sup-agent` for gate decision.