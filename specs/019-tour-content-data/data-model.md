# Data Model: Spec 019 Tour Content

**Governance**: [Constitution v2.1.0](../../.specify/memory/constitution.md) at original design; synchronized 2026-11-10 with [Constitution v3.0.0](../../.specify/memory/constitution.md). Source/derivative roles follow the tour's fixed canonical `source_locale` ([spec.md Amendment Record](spec.md)).
**Design status**: Target refinements to the inherited schema; no migration or code executed by this document.

## Entity Ownership and Relationships

| Entity | Existing storage / relationship | Role and target refinements |
|---|---|---|
| Tour | `tours`; existing `partnerRecord`, translations, media, availability, category | Authoritative lifecycle/ownership, difficulty, guide codes, and the fixed canonical `source_locale` (`en`/`es`/`it`, defaulting to `en` for pre-amendment rows); retain legacy itinerary/cover |
| TourTranslation | `tour_translations`; belongs to Tour; existing unique tour/locale | Canonical source record plus derived non-source-locale records; reuse existing itinerary and important_information JSONB |
| TourTranslationState | `tour_translation_states`; unique `(tour_id, locale)` | Existing desired/translated hashes and statuses for each derived locale, not public serialization |
| TourMedia | `tour_media`; existing ordered relation | Add nullable canonical-source `alt_text` (English for pre-amendment rows); write cover/gallery type with legacy image compatibility |
| Partner / profile | Existing partnerRecord and profile | Eligibility-approved active public summary only; no FK/tenant remapping |
| Review | Existing Reviews domain model | Visible/flagged review aggregates on exact eligible operator tours |
| TourDraft | Existing opaque payload snapshot owned by partner/tour | Incomplete autosave distinct from materialized canonical source; no new draft subsystem |

Public operator/related outputs are projections, not new persisted tables. Preserve the existing partner-record ownership lookup; do not replace it with a helper comparing user IDs to partner-record IDs.

## Tour Fields

| Field | Type / validation | Semantics |
|---|---|---|
| `difficulty_level` | Existing nullable string; supplied value easy/moderate/challenging | Missing old value stays null; no invented default on reads |
| `guide_languages` | Existing nullable JSONB code list, at most 20 distinct values | Normalize stable 2/3-letter language codes and existing code subtags; never infer from derivatives |
| `languages` | Existing compatibility input/output alias | Same spoken codes; guide_languages wins if both provided, as existing service behavior |
| `itinerary` | Retained legacy JSONB | No new authored writes; preserve raw source during adoption |
| `cover_image_url` | Retained nullable URL | Synchronized compatibility cover; only fallback when usable media is absent |
| lifecycle/pricing/availability | Existing fields and relationships | No revised money, approval or booking model |

## Canonical Source and Derived Translation Fields

Canonical source projection retains fixed field/key order: `title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`, `itinerary`, `important_information`. Rebuild day/stop objects in their declared field order before hashing, but preserve list/day/stop array order. Normalize nullable/empty distinctions once at input. Hash this normalized projection using the existing SHA-256 algorithm, extended with nested canonicalization so equivalent object-key reordering is an unchanged save. Omitted update keys retain old values; explicit null clears nullable text, empty arrays clear lists. Title remains a stored string, not null; empty draft source cannot pass submission/approval.

| Field | Accepted shape / existing bounds |
|---|---|
| title | String, max 120 characters; existing create requires it; publication requires a trimmed nonempty canonical-source title |
| description | Nullable draft source string, max 5,000; preserve existing top-level create 100-character minimum; publication requires a trimmed nonempty canonical-source description |
| highlights / inclusions / exclusions / important_information | Nullable/list of at most 30 strings, each max 500 |
| meeting_point | Nullable string, max 500 |
| cancellation_policy | Nullable string, max 2,000 |
| itinerary | Nullable storage for not-yet-adopted data; accepted authored array max 30; public empty output always `[]` |

For currently published or approval-bound content, preserving the existing required canonical source is checked before applying a clearing update; incomplete opaque draft snapshots do not bypass live-source eligibility. Partners author only the tour's canonical source locale; derived rows are platform-owned and old rows are retained when not current.

### Itinerary Value Objects

| Day field | Constraint |
|---|---|
| day | Required integer 1-30 |
| title | Required nonblank string, max 160 |
| description | String max 2,000 or null |
| stops | Ordered array max 20; absent optional legacy value normalizes to `[]` |

| Stop field | Constraint |
|---|---|
| title | Required nonblank string, max 160 |
| description | String max 2,000 or null |
| duration_minutes | Integer 1-1,440 or null |

Order is array order; do not silently sort by day number. Existing editor moves/removals renumber days sequentially as a UI behavior; API accepts valid declared day integers without inventing a new uniqueness rule. Public React keys use stable position/composite identity rather than day number alone, so repeated valid day numbers render safely. Empty itineraries and zero-stop days are valid. Preserve numeric day/duration and nulls while translating text.

## Translation State and Concurrency

| Existing field | Constraint / exposure |
|---|---|
| tour_id | Existing Tour FK, cascade delete |
| locale | A derived locale of the tour's source (`CONTENT_LOCALES` minus `source_locale`; unique with tour) |
| source_hash | 64-character SHA-256 of desired normalized current canonical-source projection |
| translated_hash | Nullable hash identifying stored derivative's source |
| generation_epoch | Integer epoch mirroring the tour's canonical generation identity; the derivative is current only when the desired hash **and** the desired epoch match the tour's current identity |
| status | pending / stale / ready / failed |
| last_error_code | Nullable sanitized category, max existing 40; never raw body/prompt/key |
| timestamps | Operational record only, not source identity |

**Current invariant**: derivative row exists, state is ready, the desired hash and desired epoch equal the tour's current canonical identity, and translated_hash equals the freshly computed current canonical-source hash. A hash alone is never sufficient: after an A→B→A cycle the same text reappears under a new epoch, and the earlier derivative is no longer current. Public `translation_status` maps an apparently ready but mismatched/missing derivative to stale; the canonical source locale maps to `source`. No public hash/error/job fields.

**Desired-state rules.** Matching hash+epoch with a ready row and a real derivative row preserves the derivative; matching hash+epoch in a pending or stale state allows a duplicate requeue for recovery; a failed state is retried by reconciliation; a mismatching epoch or hash overwrites the desired identity, marks the stored derivative stale (or a missing derivative pending) and enqueues current work. A matching ready state without a row is unfinished, not ready. When one state epoch is used, advancing the desired identity MUST clear the translated currency before marking the state pending or stale. Existing successful derivatives are kept for fallback and history, and are never labelled current by hash alone.

| Event | State/result |
|---|---|
| New usable canonical source / no derivative | Persist pending desired hash for the derived locales; dispatch after commit |
| Changed canonical source / derivative exists | Preserve old derivative, mark stale with new desired hash, dispatch after commit |
| Identical source save | No state invalidation or redundant generation |
| Current valid completion | Atomically save derivative and ready/translated_hash; clear sanitized error |
| Superseded completion/failure | No mutation of current state or content |
| Duplicate already-ready completion | Derivative-write/provider no-op; may schedule idempotent fresh projection refresh |
| Current permanent invalid output / exhausted transient retry | Failed for matching desired hash and generation epoch only; never overwrite current ready state; preserve the canonical source and old derivative |
| Recover failed/orphan pending work | Bounded requeue of current desired revision; no duplicate public rows; optional ready projection refresh requires no regeneration |

Source writes and completions lock **Tour -> canonical-source translation -> derived-locale states in platform order**; recheck the source inside that transaction. Fetch/translate outside the locks and use fresh reads at completion. This is the planned race fix, not a claim about the baseline state-only lock. Queue/index dispatch is after commit and exceptions cannot undo an already accepted source save; persisted desired states allow reconciliation.

## Media and Specific Alternative Descriptions

New migration: nullable text `tour_media.alt_text`, validated at max 500 characters. No required backfill or caption-generation subsystem. Existing rows without a specific alt use the selected public title; specific partner-supplied alt follows the tour's canonical source locale (`alt_locale=source_locale`, so `en` for pre-amendment rows), otherwise `alt_locale=content_locale`.

Materialized media writes use `type=cover|gallery`, accepting legacy `type=image` on reads. Preserve the existing at-most-20/distinct-URL input rules; duplicate authored URLs fail validation. Select the first flagged cover (or first valid media if no flag) and retain remaining sort order. Keep one authoritative cover selection and synchronize the legacy cover URL. Legacy duplicates become one public photo. Omitted media preserves rows. A provided replacement list replaces media atomically with the source; omitted alt for an existing URL retains its stored specific alt for older writers, while explicit null clears it.

Public gallery reads prefer typed cover/gallery records; legacy image rows are read in sorted order, deriving their cover from matching legacy URL when possible. The legacy URL is not prepended as an extra stale photo to an otherwise usable gallery. Only a media-empty tour falls back to its real legacy cover. Extend shared model readers before changing write types, including search/partner consumers. No thumbnails, coordinates or captions are fabricated.

## Exact Eligibility and Projections

Operator summary: only approved active profiles with public name, description and logo. `verified=true` reflects those conditions. Eligible tour count and review aggregates use the exact shared public-bookability check after eager-loading availability, not only the approximate bookable scope. Review states remain visible/flagged; zero review_count yields average_rating=null. No contact/address/tax/payout/account identifiers leave the projection.

Related projection: destination/category candidates exclude current/unpublished tours and apply exact eligibility. Rank matching destination, then matching category, then genuine rating, existing review-count tie and stable ID. Scan bounded pages until four eligible cards or exhaustion; optional internal limit clamps at eight. No new public limit parameter or recommendation table.

## Content Revisions and Review Requests (EGM-003, 2026-11-14)

The owner decision of 2026-11-14 (see the [spec.md Revision Governance Record](spec.md#revision-governance-record-egm-003-owner-decision-2026-11-14)) adds an immutable content-revision record for published tours. Live tables remain the only source of truth for public projections; a candidate revision writes nothing to them.

This section was synchronized on 2026-11-14 to the [EGM-003 technical design v3](../../docs/designs/supplier-portal-egm003-governance-v3.md) and its [execution file/test manifest](../../docs/designs/supplier-portal-egm003-execution-plan.md). The v1 and v2 proposals are retained as historical review evidence and are not active contracts. No migration or code has been executed by this document, and EGM-003 remains open.

### New tables

| Table | Columns | Semantics |
|---|---|---|
| `tour_content_revisions` | `id`, `tour_id` (FK), `revision_number`, `kind` (`legacy_baseline`/`partner_edit`/`admin_correction`/`publication`), `state` (`draft`/`proposed`/`approved`/`rejected`/`withdrawn`/`superseded`), `source_locale`, `payload` (jsonb), `payload_hash`, `content_hash` (char 64), `base_revision_id` (self FK, nullable), `created_by` (nullable), `submitted_at` (nullable), `decided_by` (nullable), `decided_at` (nullable), timestamps | Append-only content identity. Unique `(tour_id, revision_number)`; a PostgreSQL partial unique index allows at most one `draft`/`proposed` row per tour. Foreign keys cascade with tour deletion; base and published pointers may only name the same tour, enforced in the locked service. `payload`, `payload_hash`, `content_hash`, `source_locale`, `revision_number`, `kind` and `base_revision_id` are immutable at the model layer; only workflow columns move, and only through the domain service. |
| `tour_revision_requests` | `id`, `tour_id` (FK), `context` (`initial_review`/`published_review`/`correction`), `base_revision_id` (nullable), `revision_id` (nullable), `state` (`open`/`answered`/`approved`/`rejected`/`closed`/`superseded`), `reason`, `field_refs` (jsonb), `response_note` (nullable), `response_generation_epoch` (nullable), `requested_by`, `responded_by` (nullable), `responded_at` (nullable), `decided_by` (nullable), `decided_at` (nullable), `decision_reason` (nullable), `requested_content_hash`, timestamps | The review workflow. `reason` is mandatory, non-blank after trimming and bounded at 2,000 characters; `field_refs` is mandatory and holds 1–30 allowlisted, resolvable references; `requested_content_hash` is immutable. `response_generation_epoch` records the observed epoch for an initial-review response. Old reasons, fields and responses are kept as history, and an answered request is never silently reused for another revision. |

### Additive columns and the four identities

| Column | Default | Identity / purpose |
|---|---|---|
| `tours.published_revision_id` (nullable FK → `tour_content_revisions.id`, `nullOnDelete`) | null | **Approved snapshot pointer** — the revision whose content is materialized in the live tables. `null` for a never-published tour and for a legacy published tour until the baseline capture runs. |
| `tours.generation_epoch` | 0 | **Generation epoch** — the canonical generation identity. It advances when the canonical source hash changes, including active option source text, and on an explicit authorized force-regeneration even with equal text. It fences older work and makes an A→B→A cycle safe. |
| `tours.content_version` | 0 | **Content version** — bumped in the same transaction as anything that changes the rendered public payload; it versions the public cache key. |
| `tour_translation_states.generation_epoch` (`NOT NULL DEFAULT 0`, existing rows backfilled to 0) | 0 | The per-locale desired-state identity mirroring the tour's generation epoch. |

**There is no separate publication epoch.** Every first/re-publication establishes a **NEW approved revision ID**, even when the source text is equal, and revision approval additionally requires the tour to currently be `published`. The compare-and-set token is therefore the pair (observed `base_revision_id` equal to the current `tours.published_revision_id`, and the tour's current published status). At submission and at approval, a candidate whose base no longer equals the current pointer, or whose tour is no longer published, is refused and committed as `superseded`. No `lock_version` column is added. The canonical content **hash** remains a value identity and is not a revision identity.

### Typed bounded payload and its tiers

| Tier | Content | Stored in a revision |
|---|---|---|
| Source | `source` — the nine existing canonical fields with their existing bounds | yes |
| Presentation | `presentation` — `category_id`, `slug`, `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`, `difficulty_level`, `guide_languages`, `cover_image_url`, the governed tour-row presentation facts | yes |
| Options | `options` — the existing active option IDs in live order, with `title` and `pickup_instructions` only | yes |
| Media | `media` — the faithful stored/public media projection (`url`, `alt_text`, `is_cover`) | yes |
| Commercial / operational | price, pricing tiers, availability rules/exceptions, option price/capacity/start time/weekdays/guide codes, option membership/order/status, departures, lifecycle, ownership, provider state | no — rejected inside a revision payload; they stay live on their existing contracts |

A revision payload containing a commercial or operational key, an unknown key, a derivative-locale key, or a platform-owned revision/provider key is refused with the exact offending path, reusing `TourContentRules::forbiddenPresentPaths()`. A revision row always stores a resolved full snapshot; the API accepts a patch with the existing present-key semantics (omitted preserves, explicit null clears nullable text, `[]` clears a list). The payload contains no derivative text, currency, prices, stock, partner ID, lifecycle or provider state. Source text and option text are hashed with the existing algorithm unchanged; the entire snapshot is hashed separately for immutable replacement compare-and-set, preserving array order.

`category_id` and `slug` are **governed presentation fields**, not commercial data: a change to either is reviewed like any other presentation change and is refused as an unflagged write on a published tour.

### Option content promotion

Revision-governed option content covers authored **text** only. Promotion MUST NOT reuse `TourService::syncOptions()`, which rewrites price, capacity, start time, weekdays, guide codes, order, status and retirement; a text-only option payload is never passed to it. A dedicated text-only materializer updates `title` and `pickup_instructions` of the listed, owned, active options and touches nothing else. The candidate's option set MUST equal the tour's active option ids in the live order. On a published tour, option additions, reactivation, retirement and reordering are **refused in this slice** with a localized explanation, and a structural change is never silently applied; existing active option text can be revised, and an option list whose text, identity and order are unchanged round-trips inside an operational price/capacity/schedule save. Draft-tour option functionality is unchanged. This restriction is explicit, tested and recorded as remaining option-review work; it is not a claim that the A3 option slice is complete.

### Revision identity, concurrency and pointer reconciliation

`content_hash` is the SHA-256 of the existing canonical projection plus the active option texts, computed with the current algorithm so that the value equals the `sourceHash()` of the same content after materialization. The existing `sourceHash()` becomes a thin wrapper over a payload-based variant so previously persisted hashes stay byte-identical. The separate `payload_hash` covers the whole snapshot and is the compare-and-set token for immutable replacement. Revision numbers are allocated under the tour lock as `max(revision_number)+1`. The lock order extends the existing proven order: partner owners → tour → authored source translation → derivative states in platform locale order → revision rows by ascending id → request row. Approval materializes the canonical source row, the option authored texts, the media rows and the presentation facts in one transaction under that lock, advances `published_revision_id` and `content_version`, marks the previous approved revision superseded, and dispatches the revision-bound generation after commit. Approval never changes lifecycle state, `published_at`, pricing, availability, capacity, reviews, ratings, or bookings.

**Replacement is immutable.** A replace merges the patch onto the observed candidate's stored payload, inserts a **NEW** immutable `draft` row with a new ID and revision number, marks the old row `superseded`, and returns the new ID/number. A payload is never mutated under a reviewed ID, and the old request/revision evidence is preserved.

Pointer reconciliation: the first publication additively creates the approved revision snapshot and advances the pointer; **every** re-publication creates a new approved revision ID and advances the pointer, and supersedes the older candidates in the same transaction. Any exit from `published` (administrative unpublish/archive, partner archive, suspension discovery removal) makes approval fail immediately through a fresh status check, and the next governance read or mutation reconciles the active candidates and published-context requests as `superseded` with an audit. Security does not depend on eager invalidation, so archive/unpublish/re-publish can never resurrect a candidate.

### Enforcement boundaries and activation

For a published tour, unflagged writes to the canonical source translation row, an option's `title`/`pickup_instructions`, any `tour_media` row, and the governed `tours` presentation columns MUST be refused **by the platform's authenticated application boundaries**: the domain Actions/Services, the known writer inventory and the partner/admin request validation. Read-only controls alone are insufficient. **No PostgreSQL `GUC` or trigger bypass mechanism is introduced**, and there is no transaction-local privileged flag: the pre-policy backend is not a supported mutation interface after cutover, and neither is direct operator SQL.

Activation is a **single coordinated cutover**: production enters maintenance for partner/administrator writes, queue producers and schedulers stop, old workers gracefully finish their current jobs and provider calls and then exit (bounded by the configured timeout, with process exit verified), the complete new build and the additive schema are deployed, published baselines are captured in bounded idempotent Tour-locked batches, current derivative states are reconciled, and only new workers are restarted. The queue is **not** claimed to empty while the workers are stopped; queued or delayed old jobs may remain, and the new workers safely no-op their null epoch while reconciliation queues replacements. The supported rollback target is a compatible build that retains all governance guards and readers, with proposal/correction/approval writes disabled if necessary. Accepted snapshots and columns are never destructively dropped, and stored booking snapshots are never rewritten by backfill, proposal or promotion.

### Field-reference allowlist

`source.*` (the nine canonical fields), `options[*].title`, `options[*].pickup_instructions`, `media[*].alt_text` and `presentation.*` are requestable. Only `source.*` is administratively correctable in this slice; other governed fields require a partner change. Commercial and operational paths are neither. References use stored snapshot names, stable option IDs and captured media positions; the request-level `translations` compatibility key maps onto `source.*`. Each request has 1–30 objects with an allowlisted `path` and optional plain-text `note` (max500). Published references resolve against the captured base snapshot; initial-review requests use captured current source context/hash/epoch, with canonical schema fields referenceable even when missing. Option/media references must identify existing entries. An administrator correction must declare every path it changes, and changed paths must be a subset of declared references; undeclared changes, derivative paths and no-effect corrections are refused.

### Baseline capture

`legacy_baseline` revisions are created by a bounded, resumable, idempotent command (`--dry-run`, `--after-id`, `--limit`) for published tours that have no `published_revision_id`, using a pointer-if-null compare-and-set under the tour lock so an established pointer is never overwritten. A baseline is a **verbatim** capture of the live content, including unusual media shapes and bounds: it never normalizes, repairs or invents approved content, it is never re-approved, and it does not itself constitute a new approval or publication event. Invalid legacy source is not silently repaired or newly approved. Lazy baseline capture is also mandatory on the first owned published GET, on proposal/correction/request creation and on review use, so a legacy row missed by a batch still works load→propose→submit. Candidate patches normalize and validate only the **changed** fields using the current bounds, preserve omitted legacy fields, and validate the full required source title and description on submission and approval; unchanged legacy media is not rewritten. A new candidate that would require changing invalid legacy fields must obtain a partner correction — adoption never fabricates one.

## Corrective Adoption and Rollback

- Preserve existing schema migrations. Add only nullable alt_text with a forward migration; ordinary application rollback retains it and all source/derived data.
- New bounded command `tours:adopt-itineraries --dry-run --after-id=0 --limit=100` inspects records in ID order (limit 1-500). Apply normalization in a transaction to existing EN rows whose itinerary is null only. No row creation without valid source.
- Legacy arrays of strings normalize to numbered days with string titles; structured arrays preserve valid days/stops/order. Any malformed/partly invalid record normalizes as a whole to `[]`, not partial fabricated reliable content. Report invalid/empty/valid/skipped-populated/missing-EN counts and resume cursor, without logging raw content.
- Re-read eligibility under the same Tour-first lock before adoption so a concurrently entered itinerary cannot be overwritten. The legacy adoption command operates on existing English rows. It persists `[]` for invalid or empty legacy values and reports missing English rows without creating source text.
- A changed adopted source uses the same hash/state service and after-commit scheduling; dry-run creates no source/state/job changes. Repeated adoption preserves all populated arrays, including intentionally empty arrays.
- Backup and staging rehearsal precede a release. Do not run destructive schema rollback or delete legacy/derived records to revert a frontend release. A prior image-only media reader requires the compatibility patch before it can consume newly typed cover/gallery rows fully.
