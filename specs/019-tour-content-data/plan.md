# Implementation Plan: Tour Content Data Model and API

**Branch**: `codex/019-tour-content-data` | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md)
**Spec ID / Master-plan phase**: `019`; UI master-plan Phase 2, Tasks 2.1-2.4
**Governing Constitution**: [v2.1.0](../../.specify/memory/constitution.md)
**Baseline / Dependencies**: Original source `codex/bookly-ui-redesign-all-work` at `84ae4e5`. [PR #27](https://github.com/hatemsamirafifi/bookly-travel/pull/27) merged on 2026-10-02; [Phase 1 PR #28](https://github.com/hatemsamirafifi/bookly-travel/pull/28) merged on 2026-10-03 (Africa/Cairo). The branch was rebased onto `origin/main` at `f1a1f37`, preserving the original baseline and incorporating later CI fixes. Main `803048e` was subsequently integrated by merge `60ce24d` after reviewed foundation commit `fb5c718`, without changing tested runtime source. Do not duplicate overlapping Phase 1 work.
**Input**: [Spec 019](spec.md), [master-plan Phase 2](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-2---tour-content-data-model-and-api), preserved [Spec 018](../018-bookly-ui-redesign/spec.md) and its original research/contracts.
**Status**: Design and [task generation](tasks.md) complete. Setup/foundations T001-T009 independently reviewed; story implementation, full acceptance, CI, merge and release remain pending.

## Summary

Complete the inherited tour-content vertical slice with one English source, revision-bound Spanish/Italian generation, independently disclosed fallbacks, structured itineraries, truthful media/difficulty/guide languages, safe operator statistics, eligible related tours and compatible public/partner contracts. Existing implementations are reuse inputs, not acceptance proof. The main work is closing validated-write gaps, serializing source/completion races, correcting eligibility/gallery selection, completing bounded existing UI controls and adding explicit acceptance coverage.

The phase preserves `/api/public/tours/{slug}`, partner endpoints and response envelopes. It does not rebuild the detail layout or partner editor, implement another payment flow, merge prerequisite PRs or mark the wider redesign complete.

## Technical Context

**Language/Version**: Backend PHP `^8.2`, deployment target 8.3+, Laravel 11; frontend TypeScript 5+, Next.js 16.2.3 and React 19.2.4 (current manifests).
**Primary Dependencies**: Existing Laravel Sanctum 4, Filament 3.3 internal-only, Scout 11, Redis queue, next-intl 4.9, Tailwind CSS 4, React Hook Form/Zod and the existing Gemini HTTP adapter. No new framework or SDK is required.
**Storage**: PostgreSQL tour/translation/media tables and existing translation-state table; Redis queue/cache, existing Scout indexing abstraction.
**Testing**: Pest/PHPUnit on disposable PostgreSQL via `phpunit.pgsql.xml`; Laravel HTTP/queue fakes; Jest/Testing Library; Playwright/Axe; Pint, Larastan, ESLint, TypeScript and production-build Lighthouse evidence.
**Target Platform**: API-first browser application in EN/ES/IT at 390/768/1024/1440px; internal Filament publishing remains governed separately.
**Project Type**: Existing backend/frontend web marketplace.
**Performance Goals**: Keep generation off request paths; avoid per-candidate N+1 queries through eager-loaded availability and bounded candidate pages. Primary-public-page release Lighthouse Performance remains >=90 under documented conditions. No new latency, volume or translation-completion SLA is invented.
**Constraints**: Exact ownership and publishing gates; additive schema/contracts; preserve legacy source and old derivative rows; current-hash-only derivatives; backend-only credentials; real source facts and no private operator fields.
**Scale/Scope**: Six stories, 30 FRs and eight outcomes limited to master-plan Tasks 2.1-2.4. Four related cards by default, eight absolute ceiling; 30 itinerary days, 20 stops/day. Existing routes/components are extended only as needed for this contract.

## Constitution Check

Pre-research evaluation on 2026-10-02 found no unapproved design departure: approved stack, tours-only scope, verified predecessor and Spec 019 ownership are retained. Post-design evaluation below is **PASS for the design**, contingent on implementing and running the cited acceptance gates. It is not a claim that inherited code already passes.

| Principle/gate | Pre-research | Post-design response | Design result |
|---|---|---|---|
| I. Tours-only / partner ownership | Retain existing marketplace and owner lookup | Shared validated writes and IDOR tests; no unrelated vertical | PASS |
| II. Approval/review integrity | Keep publication and review eligibility | English required on submit/approval; ES/IT optional; exact eligible review aggregates | PASS |
| III. Auditable commerce | No new money behavior | Preserve prices, booking/payments/Stripe; regression smoke, no fabricated offer | PASS |
| IV. API-first and authorization | Existing Laravel authority / internal Filament | Move active controller validation/query decisions into Requests/Actions; explicit projections | PASS |
| V. Shared design / originality | Reuse Phase 1 tokens/primitives | Bounded source/duration/difficulty controls and notices; no copied assets/full redesign | PASS |
| VI. Localization / accessibility | Three locales and four viewports | Localized field errors/notice/status, actual text language, keyboard/Axe/browser matrix | PASS |
| VII. Evidence and regression | Historical checks are baseline only | New PostgreSQL races/contracts, frontend/types, SSR transitions and recorded gate output | PASS |
| English/derived source rules | Keep approved revision/translation decisions | Common lock order, post-commit jobs, retry/recovery, independent current/EN selection | PASS |
| Privacy / secrets | Use allowlists and backend configuration | State/hash forgery rejection; no secret/provider body serialization or live key in tests | PASS |
| Compatibility / adoption / rollback | Preserve existing fields/URLs/schema | Forward corrective adoption; legacy image readers; backend before consumers; data retained | PASS |
| Specification identity / baseline | Active pointer resolves to 019 | This directory owns new artifacts; Spec 018 remains intact; merged predecessor PRs and current branch ancestry recorded | PASS |
| Release governance | Separate design and execution evidence | Tasks generated; no implementation/CI/merge/release gate marked complete | PASS |

No constitutional exception is requested. [tasks.md](tasks.md) was generated on 2026-10-02; confirm the predecessor/base still matches before implementation. Repeat the active Constitution Check before release using actual test and deployment results.

## Project Structure

### Documentation (this feature)

```text
specs/019-tour-content-data/
  spec.md
  plan.md
  research.md
  data-model.md
  quickstart.md
  checklists/requirements.md
  contracts/
    tour-detail-api.md
    partner-tour-content-api.md
    translation-generation.md
  tasks.md                     # execution ledger; accepted markers match evidence
```

### Source Code (repository root)

```text
backend/
  app/Models/{Tour,TourTranslation,TourTranslationState}.php
  app/Domains/Partner/
    Controllers/TourController.php
    Requests/                  # wire shared validation into active routes
    Services/{TourService,TourTranslationService,GeminiTourTranslator}.php
    Contracts/TourContentTranslator.php
    Jobs/GenerateTourTranslationJob.php
    Models/TourMedia.php
  app/Domains/Search/
    Actions/{GetTourDetailAction,GetPublicOperatorSummaryAction,GetRelatedToursAction}.php
    Transformers/TourCardTransformer.php
  app/Domains/Admin/Actions/ApproveTourAction.php
  app/Console/Commands/QueueTourTranslations.php
  app/Console/Commands/AdoptLegacyTourItineraries.php  # planned corrective command
  app/Providers/EventServiceProvider.php
  database/migrations/          # existing schema + new nullable alt_text migration
  tests/Feature/{Partner,Search,Admin}/
frontend/
  src/lib/api/{types,tours,partner,client}.ts
  src/types/{tour,partner}.ts
  src/lib/stores/tourWizard.ts
  src/lib/validators/partner.ts
  src/components/partner/tours/{TourWizard,ItineraryEditor,ImageUploader,TourContentPreview}.tsx
  src/components/tour/{TourDetail,OperatorSummary,ImageGallery}.tsx
  src/components/seo/StructuredData.tsx
  src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx
  src/app/[locale]/(public)/tours/[slug]/page.tsx
  messages/{en,es,it}.json
  src/**/__tests__/
  tests/e2e/{tour-detail.spec.ts,partner/,a11y/}
```

**Structure Decision**: Extend the existing domain/service and frontend component layout. Actual routes use `TourController` inline validation today; new reusable rules must be wired there. Reuse `GetPublicOperatorSummaryAction`, not a parallel master-plan sketch class with a different name. Wizard step controls are functions in TourWizard, not a separate StepDetails file. UI message catalogs live at `frontend/messages`, not `frontend/src/messages`.

## Design and Delivery Sequence

| Slice | Scope / requirement trace | Implementation intent | Acceptance evidence |
|---|---|---|---|
| A. Source and itinerary | US1; FR-001-009; SC-001/005; Task 2.1 | Reusable validated English patch, null/omission semantics, correct create/update round trip, existing incomplete draft snapshots, bounded adoption | Complete source-field fixtures, ownership, boundary validation, transaction rollback, legacy/repeated/missing-EN adoption |
| B. Revision and publication | US2/US3; FR-010-016; SC-002-004; Task 2.1 | Tour-first locks, exact current hash, safe duplicate/failed jobs, async post-commit dispatch and recovery, EN-only publication | Real PostgreSQL save/completion interleavings, fake provider failures, orphan pending recovery, required-EN guards |
| C. Media and supported facts | US4; FR-017-021; SC-006; Task 2.2 | Difficulty and guide aliases, cover/gallery normalization, legacy image reads, nullable specific alt, matching DTO/editor duration controls | EN round trip, media ordering/dedup/fallback, no fabricated empty facts, unchanged media omission |
| D. Public-safe discovery | US5; FR-022-026; SC-007; Task 2.3 | Add verified to approved active summary; exact operator eligibility; paginated related eligibility, four-default/eight-ceiling | Negative private-field tests, expired/unpublished/hidden-review fixtures, >32 invalid candidates and deterministic ties |
| E. Contract/display/SEO | US6 plus cross-story UI; FR-027-030; SC-008; Task 2.4 | Preserve endpoint/fields, separate locale notices, fresh SSR reload, matching actual metadata/itinerary/gallery and real Place origin | Public/partner contract snapshots, localized components/types, SSR transition fixtures, keyboard/Axe and structured-data assertions |

Slice A and B establish canonical source semantics before dependent UI; public readers accepting legacy and new media types precede new media-type writers. Preserve opaque legacy snapshots until materialization. Normalize nested object keys before hashing while preserving array order. Matching-ready retries/reconciliation may refresh projections without rewriting derivatives or invoking the provider. Detailed files and individual tasks are generated by `/speckit.tasks`; this table is design sequencing, not a replacement task tracker.

Affected frontend details include stable position/composite keys for repeated valid day numbers, null/unset difficulty preservation on older-form restoration and unrelated updates, and TourImage/ImageGallery consumption of alt_locale. Image/caption language follows actual alt text while action labels follow the page locale. These cases are acceptance requirements, not a new uniqueness constraint, invented difficulty default, or broader gallery redesign.

## Compatibility and Rollout

1. Inventory active English-source consumers and deployed backend migrations; pending all-work/Phase 1 PRs must be resolved or carried as an explicit stacked dependency before merge. Do not cherry-pick Phase 1 twice or delete its documents.
2. Deploy additive nullable alt storage and readers for `image`, `cover`, and `gallery`. Reuse existing itinerary/guide/state migrations; never rerun or duplicate their columns. Keep the legacy cover column synchronized for existing consumers.
3. Correctively adopt only null EN itineraries in bounded dry-run/repeatable batches. Existing arrays, including intentional `[]`, and missing EN rows remain untouched; invalid/empty input resolves to `[]` where an EN row exists. Report missing source for governed repair.
4. Deploy compatible EN-writing clients and complete validated source fields. New ES/IT source writes are already rejected on this branch; verify every deployed authoring consumer before releasing that restriction to an older deployment. Retain old derivatives for regeneration. Publish guards remain English plus existing lifecycle/pricing/media requirements.
5. Enable Redis workers and source/completion serialization; index dispatch after commit. Reconcile persisted desired states if queue dispatch fails. Use a configured available Gemini model with backend secret-store credentials; automated acceptance uses fakes.
6. Release affected UI and run all required acceptance gates. Record commit, fixture, command, result, viewport/locale and evidence path. CI/merge/deployment remain separate statuses.
7. Roll back frontend/application images to a verified compatible reader if needed; retain database columns, media, English content and derived rows. Stop/restart incompatible workers. Do not run migration rollback as ordinary release rollback; old backend `type=image` readers need the reader compatibility patch to preserve a complete newly typed gallery. Legacy cover fallback alone is degraded display, not full gallery acceptance.

## Verification Strategy

Follow [quickstart.md](quickstart.md) for exact commands/scenarios. Backend tests run on disposable PostgreSQL, one suite owner at a time. Keep provider calls fake; isolated two-connection/process tests exercise lock interleavings. Browser tests distinguish fixture-driven SSR acceptance from seeded integrated partner writes. No test count from Spec 018 is substituted for Spec 019 evidence.

Acceptance must cover every FR/SC group above, absence of sensitive nested fields, no fabricated facts, exact public bookability, full legacy preservation, fresh ready transitions, and EN/ES/IT affected interface states. Run both backend and frontend gates after implementation. Lighthouse >=90 remains a release gate and is measured from a reachable production build; failed/unreachable CI is never counted as a pass.

## Complexity Tracking

No constitutional violations or additional architecture exceptions. Existing pending branches are documented dependencies allowed by v2.1.0, not an exception. The only new persisted concern is nullable media alt text; corrective adoption/recovery reuses existing command/domain boundaries.
