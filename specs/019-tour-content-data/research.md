# Research: Spec 019 Tour Content Data Model and API

**Date**: 2026-10-02
**Governance**: [Constitution v2.1.0](../../.specify/memory/constitution.md)
**Research baseline**: `codex/019-tour-content-data`, original HEAD `84ae4e5`, inherited from `codex/bookly-ui-redesign-all-work`. Implementation rechecks on 2026-10-02/03 confirmed both predecessor PRs merged. The branch was rebased onto `f1a1f37`; latest fetched `origin/main` is `803048e`, adding Phase 1 evidence and ignore rules. Reconcile that latest base after committing reviewed implementation changes. Code observations below remain static research evidence, not execution results.

## Evidence and gaps

| Concern | Confirmed repository evidence | Consequence for this phase |
|---|---|---|
| Existing schema | `backend/database/migrations/2026_09_25_000001_add_guide_languages_and_localized_itinerary.php` adds localized itinerary/guide codes; `2026_09_25_000002_create_tour_translation_states_table.php` adds states and important information | Reuse these columns/table; do not create duplicate migrations or rewrite applied history |
| Source validation | Active `backend/app/Domains/Partner/Controllers/TourController.php:99,156` uses inline validation; its create rules at :115 omit several nested source fields; `TourService.php:16,179` supports a broader field list | Wire reusable validation into the actual routes; editing unused FormRequests alone will not fix accepted writes |
| Revision safety | `TourTranslationService.php:35,40,123` hashes source, schedules after commit, checks ready/current; `GenerateTourTranslationJob.php:39,57` checks before/after provider but locks only state | Add shared Tour-first serialization and actual database interleaving coverage; sequential stale-result tests do not prove concurrency safety |
| Migration adoption | First migration :25-38 writes valid legacy data only into existing EN rows with null itinerary; skips invalid/empty results; rejects a partly invalid array as a whole | Define empty public output explicitly; add bounded corrective adoption, preserve populated content and report missing EN instead of inventing it |
| Public response | `GetTourDetailAction.php:61,106,148` already selects actual languages and emits generic fallback; :65 treats empty English itinerary as a fallback | Preserve response keys; independently disclose nonempty itinerary fallback and suppress empty-itinerary notices |
| Operator eligibility | `GetPublicOperatorSummaryAction.php:13,22,35` allowlists profile fields but lacks verified and uses approximate `Tour::bookable()`; `Tour.php:156` does not prove future availability | Add verified and aggregate only IDs satisfying exact shared bookability |
| Related selection | `GetRelatedToursAction.php:19,33,39,42` ranks/filter/takes four but truncates 32 before exact eligibility | Paginate candidates until four eligible results or exhaustion; do not hide valid later candidates behind the prefilter cap |
| Media | `TourService.php:228` synchronizes media in transaction; `Tour.php:194` orders/deduplicates; `Domains/Partner/Models/TourMedia.php:15` has no specific alt storage | Preserve cover compatibility; optional specific English alt needs an additive nullable column, otherwise use selected title |
| Authoring UI | `TourWizard.tsx:113,165` already writes EN/itinerary/difficulty/guide codes but drops inclusions; `edit/page.tsx:174,192` omits difficulty/important information | Complete the existing DTOs and bounded controls, not the full Phase 7 editor redesign |
| Duration/errors | `ItineraryEditor.tsx` limits days/stops but has no duration control; `lib/validators/partner.ts:48` validates duration; wizard interprets nested default English errors as translation keys | Add duration input and localized field-level feedback; normalize older persisted wizard state |
| Display/SEO | `TourDetail.tsx:74` has one generic notice; `StructuredData.tsx:25-75` already uses actual languages, ordered days, safe real images and guarded ratings | Add independent notice and real stop/meeting structure; preserve escaping, prices, canonicals and URLs |
| Actual route | `backend/bootstrap/app.php:27` and `routes/api/public.php:105` register `/api/public/tours/{slug}`; `frontend/src/lib/api/tours.ts:10` uses it | Correct shorthand in new 019 contracts; leave 018 historical contract untouched |

Paths in evidence rows are relative to `backend/app/Domains/Partner/` for translation/service/job references, and to `frontend/src/components/partner/tours/` for wizard/editor references; the plan source map identifies full paths.

## Decisions

### D1 - Complete the inherited vertical slice

**Decision**: Reuse the existing model, Actions/Services, provider interface, queue job, media functions and UI primitives. Extend/fix active paths and establish new Spec 019 evidence.
**Rationale**: Most foundations exist but acceptance gaps are observable; replacement would duplicate domain logic and migrations.
**Alternatives considered**: Recreate all master-plan suggested classes/schema; claim existing Phase 0 code is complete. Both lose traceability or skip verification.

### D2 - Normalize one English patch at the validated boundary

**Decision**: Retain top-level shorthand and `translations.en`. Nested explicitly provided fields take precedence over shorthand for that field; absent fields preserve existing values, explicit null clears nullable fields, and empty arrays clear lists. Validate the normalized result. Materialized writes forbid authored ES/IT and platform-owned state/hash fields. Existing opaque incomplete draft snapshots retain their payload-array boundary, including legacy string-array itineraries; normalize and validate only on materialization. Autosaving snapshots does not trigger generation or authorize applying platform-owned state.
**Rationale**: The service already favors structured source inputs; declaring precedence and null handling closes silent drops without inventing a new endpoint. Existing create-required basic fields remain compatible; draft save and publication validation remain separate.
**Alternatives considered**: Silently discard nested fields; force new clients to use an unrelated route; apply publication rules to every draft save.

### D3 - Serialize source revisions and derivative completion

**Decision**: Preserve SHA-256 over canonical source fields as revision identity. Rebuild nested day/stop objects in fixed key order while preserving array order, so object-key reordering alone does not invalidate derivatives. Within source-write and job-completion transactions acquire Tour, EN row, then derived states in fixed `es,it` order. Provider work runs outside locks. Recheck current hash under the same lock order; matching already-ready work skips provider/content writes but may refresh current projections idempotently. Non-translatable settings and media-only changes do not invalidate source derivatives.
**Rationale**: A state-only lock does not serialize a concurrently written English row. Content identity is already the revision contract; no second revision counter/table is required.
**Alternatives considered**: Timestamp-only checks; lock through the network request; use unique jobs as the only correctness control. None guarantees the required final source check safely.

### D4 - Dispatch after commit with bounded recovery

**Decision**: Use an asynchronous Redis connection in deployed API/workers, preserve four attempts and 5/15/60-second backoff, provider HTTP timeout 40 seconds, job timeout 60 seconds and queue retry-after 90 seconds. Persist desired states transactionally and dispatch/index only after commit. Queue dispatch failure must leave source saved and states recoverable; extend the bounded `tours:queue-translations` reconciliation command to recover pending/stale/failed work, including orphan pending states. Add optional `--refresh-ready` for lost index/cache dispatch after a ready commit, with no provider call or derivative rewrite. Projection jobs load current source/state. Use hash-aware duplicate no-op, not permanent unique-job suppression.
**Rationale**: Durably persisted desired state permits recovery without holding HTTP saves hostage to provider/queue outages. Tests fake dispatch/provider; production must not use sync translation dispatch.
**Alternatives considered**: Generate inside the request; mark every retry ready; an unbounded retry-all command.
**Official support**: Laravel documents post-commit dispatch and queue retry/timeout separation; the recovery and lock design are this plan's decisions. [Laravel 11 queues](https://laravel.com/framework/docs/11.x/queues).

### D5 - Preserve provider adapter and validate returned values

**Decision**: Keep `TourContentTranslator` and `GeminiTourTranslator`, backend-only `GEMINI_API_KEY`/`GEMINI_TRANSLATION_MODEL`, the existing generateContent structured JSON request, and server-side exact-key/value validation. Translate only whitelisted source strings; preserve numeric day/duration values, nulls and array structure. Retain sanitized failure categories and a configured-model smoke check before release; no model entitlement is inferred from a default string.
**Rationale**: Structured output helps shape consistency but still requires application value validation. [Gemini structured outputs](https://ai.google.dev/gemini-api/docs/generate-content/structured-output), [GenerationConfig reference](https://ai.google.dev/api/generate-content#v1beta.GenerationConfig).
**Alternatives considered**: New SDK/provider, client-side credentials, free-text parsing, or switching to older request fields solely from old examples.

### D6 - Forward corrective adoption and nullable specific alt

**Decision**: Reuse schema already present. Add only nullable `tour_media.alt_text` (English source, max 500 characters) in a new migration; public `alt_locale` identifies its English language, otherwise alt uses the selected title language. No caption-generation or per-image translation subsystem is introduced. Preserve distinct-URL authored-input validation; deduplicate legacy URLs on public reads. Deploy readers accepting `image`, `cover`, and `gallery` before new typed writers. Add a dry-run/resumable `tours:adopt-itineraries` command targeting null structured EN itineraries; retain raw legacy values and report invalid/missing-EN counts.
**Rationale**: Preserving older source and existing structured arrays protects adoption and rollback. Specific alt is an optional description, never an invented image claim.
**Alternatives considered**: Destructive legacy removal, overwriting all English itineraries, recreating missing EN with fabricated titles, introducing translated image-caption tables.

### D7 - Exact eligibility and compatible four-card default

**Decision**: Reuse the existing exact public-bookability method with eager-loaded availability. Operator counts and reviews use only exact eligible IDs; related candidates retain destination/category/rating ordering and are scanned in bounded pages until four or exhaustion. Keep deterministic existing review-count/ID ties, default four and hard ceiling eight without exposing a new public limit parameter.
**Rationale**: Approximate scopes are candidate filters, not final eligibility. The old four-card contract already fits the master's eight-card ceiling.
**Alternatives considered**: Expand to eight by default, aggregate all published/expired tours, retain a truncation that can discard eligible recommendations.

### D8 - Fresh reload semantics and separate notices

**Decision**: Preserve public SSR detail reads without ISR; explicitly use no-store for detail if needed to make freshness testable. On source change/current completion refresh search projections after commit and invalidate only existing affected caches. Do not add client polling or change the unused five-minute `useTour` hook unless it becomes a consumer. Distinguish content and itinerary fallback; hide the empty itinerary section/notice.
**Rationale**: Existing browser acceptance uses a fixture server because client route mocks do not intercept SSR. Next-read selection satisfies FR-016 without an invented latency promise.
**Alternatives considered**: Pretend browser-only mocks prove SSR behavior; require continuous polling; revalidate unrelated authenticated caches.

### D9 - Truthful Schema.org extensions

**Decision**: Preserve the real meeting point and encode it as `tripOrigin: Place` when it is the actual starting meeting location. Keep an ordered itinerary ItemList representing real days/stops, using ordinary activity text rather than inventing coordinates or attraction classifications. Preserve current content-language metadata, safe image URLs and script escaping; validate newly emitted property/type pairs.
**Rationale**: TouristTrip supports ordered itinerary and a Place trip origin. The design uses documented properties rather than an invented meetingPlace field. [TouristTrip](https://schema.org/TouristTrip), [tripOrigin](https://schema.org/tripOrigin).
**Alternatives considered**: Infer stops or locations; add an unsupported meetingPlace property; emit ratings without reviews.

## Resolved unknowns and verification limits

All design unknowns are resolved above. Runtime concurrency, adoption results, deployed queue configuration, live model availability and release performance are acceptance checks, not unmade design decisions. No live Gemini request or runtime suite was executed during planning. Legacy IDOR policy helpers are not substituted for the active partner-ID-scoped service; no currently confirmed secret leak was found, so negative projection tests harden boundaries rather than claim one occurred.
