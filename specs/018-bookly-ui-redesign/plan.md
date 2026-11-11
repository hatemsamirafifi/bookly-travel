# Implementation Plan: Bookly Web Experience Redesign

**Branch**: `018-bookly-ui-redesign` | **Date**: 2026-09-25 | **Spec**: [spec.md](spec.md)

**Input**: `specs/018-bookly-ui-redesign/spec.md`, [master plan](../../docs/bookly-ui-redesign-spec-kit-master-plan.md), Constitution v2.0.0.

**Continuation governance**: [Constitution v2.1.0](../../.specify/memory/constitution.md) applied to new phases and release acceptance while active; from 2026-11-10, [Constitution v3.0.0](../../.specify/memory/constitution.md) governs active phases, and its tour-content source amendment governs the active plan ([Spec 019 Amendment Record](../019-tour-content-data/spec.md)). The checks recorded below remain historical v2.0.0 design evidence, not a later-version revalidation. Spec 018 retains the original program plan and completed Phase 1 foundations; active master-plan Phase 2 planning belongs to [Spec 019](../019-tour-content-data/spec.md). (Historical baseline note from generation: its dependent branch started from `codex/bookly-ui-redesign-all-work` at `84ae4e5`, with PRs #27/#28 pending merge at that time; both later merged.)

## Summary

Deliver the phased Bookly tours-only redesign with existing booking, publishing, authorization, and URL contracts. Partners select one fixed tour source from `en`, `es`, and `it`. Gemini generates the other two asynchronously from the current revision. Public sections use a current derivative or the current source with a visible localized notice. Live-guide languages remain independent.

## Technical Context

**Language/Version**: PHP `^8.2` (runtime target 8.3), TypeScript 5+, React 19, Next.js 16.2

**Primary Dependencies**: Laravel 11, Sanctum 4, Filament 3.3, Scout 11, Tailwind CSS 4, next-intl 4, Gemini REST `generateContent`

**Storage**: PostgreSQL; Redis for queues/cache; Meilisearch behind Scout

**Testing**: Pest/PHPUnit, Jest/Testing Library, Playwright/Axe, Lighthouse CI, Pint, Larastan, ESLint, TypeScript

**Target Platform**: Browser at 390/768/1024/1440px; Laravel API/queue workers

**Project Type**: API-first web marketplace with internal Filament admin

**Performance Goals**: Primary public pages Lighthouse Performance >=90 in documented production-build audits; keep translation generation off request paths

**Constraints**: EN/ES/IT only; no Tripadvisor assets; additive deploy/rollback-safe migrations; no AI key or private partner/booking data in browser or prompt; existing public fields remain compatible

**Scale/Scope**: All existing web route families; translation jobs bounded by Gemini project quotas and configured worker concurrency, not an invented tour-volume target

## Constitution Check

*Gate evaluated before research and again after contract design against Constitution v2.0.0.*

| Principle/gate | Design response | Result |
|---|---|---|
| I. Tours-only marketplace | No non-tour verticals; partner ownership retained | PASS |
| II. Publishing/booking/review integrity | EN required at submission and approval; ES/IT not a publication gate; existing booking/review rules unchanged | PASS |
| III. Auditable commerce | Checkout retains server-authoritative totals, idempotency, Stripe confirmation and recovery | PASS |
| IV. API-first/authorization | Laravel owns content, translation state, publishing and public API; partner writes remain ownership-gated | PASS |
| V. Bookly design system/originality | Shared semantic tokens; Tripadvisor is hierarchy reference only | PASS |
| VI. Accessibility/localization/responsiveness | EN/ES/IT UI copy, explicit EN content fallback, keyboard/focus and four-width acceptance | PASS |
| VII. Evidence-based verification | Backend contracts, frontend types/components, browser journeys, a11y and measured performance gates | PASS |
| Security/data protection | Gemini key backend secret only; prompts contain public-intended tour text only; no credentials or PII; failures sanitized | PASS |
| Deployment/rollback | Additive status schema and API fields precede frontend; async jobs use revision checks; no same-release destructive cleanup | PASS |

**Post-design recheck:** PASS. The data model and API contracts preserve these boundaries. No constitution exception is requested.

## Project Structure

### Documentation

```text
specs/018-bookly-ui-redesign/
  spec.md
  plan.md
  research.md
  data-model.md
  contracts/tour-detail-api.md
  contracts/partner-tour-content-api.md
  quickstart.md
  tasks.md
```

### Source Code

```text
backend/app/Domains/Partner/{Controllers,Services,Jobs}/
backend/app/Domains/Search/{Actions,Transformers}/
backend/app/Domains/Admin/Actions/
backend/app/Models/
backend/config/
backend/database/migrations/
backend/tests/Feature/{Partner,Search,Admin}/
frontend/src/app/[locale]/
frontend/src/components/{tour,partner,seo,ui}/
frontend/src/lib/api/
frontend/messages/{en,es,it}.json
frontend/tests/e2e/
```

**Structure Decision**: Retain Laravel-domain and Next.js App Router boundaries. Gemini adapter and queue job live server-side; no browser-to-Gemini call or new PHP SDK is needed.

## Delivery Sequence

1. **Contracts and foundation**: lock existing routes/API shapes, source/derived translation model, semantic design tokens, shared shells and acceptance matrices.
2. **Tour-content vertical slice**: additive status migration, fixed per-tour source authoring, Gemini translator adapter, revision-safe queue, source-locale publication guard, public fallback/ready selection, frontend state and E2E regression coverage.
3. **Discovery and tour detail**: data-backed home/search cards, accessible gallery, facts, itinerary, guide languages, operator/related content and truthful booking CTA.
4. **Checkout, traveler and partner surfaces**: preserve payment/booking/account authorization; redesign responsive flows and analytics with correctly scaled financial values.
5. **Admin/supporting pages and release**: moderation, blog/legal/voucher/errors, browser/a11y/performance evidence, staged deployment and rollback checks.

Each slice must keep existing frontend compatible with additive backend responses until the dependent UI is deployed. The full requirement-to-task trace belongs in `tasks.md`.

## Translation Design Decisions

- Partners select one source locale at creation. Submission and publication validate the source title and description. Eligible publication proceeds while derivatives are unavailable.
- Store per-locale status and a hash of the canonical source revision. A result becomes current only if the source hash still matches under a transaction/lock. Unchanged source does not enqueue duplicate work.
- Keep existing derivative records recoverable; treat their provenance conservatively until regeneration. Do not silently expose stale output as current. Backfill/requeue in bounded batches after rollout.
- Use backend-only Gemini `generateContent` with structured JSON, explicit output validation, a translatable-field allowlist, bounded transient retries and sanitized errors. No secret in source, logs, queue payloads, browser bundles or tests.
- Public detail selects current general content and itinerary independently. A nonempty section that uses the current source shows a visible notice in the page language naming the actual source. Metadata and structured data reflect the content shown. Guide languages remain independent.
- Tests fake the translator and prove queue, stale-result rejection, publishing, fallback, ready transition, authorization and browser rendering; no live Gemini call is needed.

## Verification and Release

Run focused Pest/Jest/Playwright first, then applicable full Laravel/Next gates from the constitution. Document production-build Lighthouse conditions and manual keyboard/focus checks. Deploy migration, backend and worker before frontend; configure a newly rotated `GEMINI_API_KEY` in the backend secret store and a backend model setting. Display the current source with a localized notice until a current derivative is available. Roll back frontend/backend without deleting new columns or generated translations. Monitor translation queue failures, Gemini 429/5xx, public fallback rate, booking errors and API latency.

## Complexity Tracking

No constitution violations. A separate translation-state record is justified by missing ES/IT rows: it tracks pending/failed work without inserting fabricated translated titles into the existing non-null translation contract.
