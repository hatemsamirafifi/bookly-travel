# Data Model: Spec 019 Tour Content

**Governance**: [Constitution v2.1.0](../../.specify/memory/constitution.md)
**Design status**: Target refinements to the inherited schema; no migration or code executed by this document.

## Entity Ownership and Relationships

| Entity | Existing storage / relationship | Role and target refinements |
|---|---|---|
| Tour | `tours`; existing `partnerRecord`, translations, media, availability, category | Authoritative lifecycle/ownership, difficulty, guide codes; retain legacy itinerary/cover |
| TourTranslation | `tour_translations`; belongs to Tour; existing unique tour/locale | English source and derived ES/IT records; reuse existing itinerary and important_information JSONB |
| TourTranslationState | `tour_translation_states`; unique `(tour_id, locale)` | Existing desired/translated hashes and statuses for ES/IT, not public serialization |
| TourMedia | `tour_media`; existing ordered relation | Add nullable English `alt_text`; write cover/gallery type with legacy image compatibility |
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

## English and Derived Translation Fields

Canonical source projection retains fixed field/key order: `title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`, `itinerary`, `important_information`. Rebuild day/stop objects in their declared field order before hashing, but preserve list/day/stop array order. Normalize nullable/empty distinctions once at input. Hash this normalized projection using the existing SHA-256 algorithm, extended with nested canonicalization so equivalent object-key reordering is an unchanged save. Omitted update keys retain old values; explicit null clears nullable text, empty arrays clear lists. Title remains a stored string, not null; empty draft source cannot pass submission/approval.

| Field | Accepted shape / existing bounds |
|---|---|
| title | String, max 120 characters; existing create requires it; publication requires trimmed nonempty EN |
| description | Nullable draft source string, max 5,000; preserve existing top-level create 100-character minimum; publication requires trimmed nonempty EN |
| highlights / inclusions / exclusions / important_information | Nullable/list of at most 30 strings, each max 500 |
| meeting_point | Nullable string, max 500 |
| cancellation_policy | Nullable string, max 2,000 |
| itinerary | Nullable storage for not-yet-adopted data; accepted authored array max 30; public empty output always `[]` |

For currently published or approval-bound content, preserving the existing required English source is checked before applying a clearing update; incomplete opaque draft snapshots do not bypass live-source eligibility. Partners author only EN; ES/IT rows are platform-owned and old rows are retained when not current.

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
| locale | `es` or `it`; unique with tour |
| source_hash | 64-character SHA-256 of desired normalized current EN projection |
| translated_hash | Nullable hash identifying stored derivative's source |
| status | pending / stale / ready / failed |
| last_error_code | Nullable sanitized category, max existing 40; never raw body/prompt/key |
| timestamps | Operational record only, not source identity |

**Current invariant**: derivative row exists, state is ready, and translated_hash equals freshly computed current English hash. Public `translation_status` maps an apparently ready but mismatched/missing derivative to stale; EN maps to source. No public hash/error/job fields.

| Event | State/result |
|---|---|
| New usable EN / no derivative | Persist pending desired hash for ES/IT; dispatch after commit |
| Changed EN / derivative exists | Preserve old derivative, mark stale with new desired hash, dispatch after commit |
| Identical source save | No state invalidation or redundant generation |
| Current valid completion | Atomically save derivative and ready/translated_hash; clear sanitized error |
| Superseded completion/failure | No mutation of current state or content |
| Duplicate already-ready completion | Derivative-write/provider no-op; may schedule idempotent fresh projection refresh |
| Current permanent invalid output / exhausted transient retry | Failed for matching desired hash only; preserve EN/old derivative |
| Recover failed/orphan pending work | Bounded requeue of current desired revision; no duplicate public rows; optional ready projection refresh requires no regeneration |

Source writes and completions lock **Tour -> EN translation -> ES/IT states in fixed locale order**; recheck the source inside that transaction. Fetch/translate outside the locks and use fresh reads at completion. This is the planned race fix, not a claim about the baseline state-only lock. Queue/index dispatch is after commit and exceptions cannot undo an already accepted source save; persisted desired states allow reconciliation.

## Media and Specific Alternative Descriptions

New migration: nullable text `tour_media.alt_text`, validated at max 500 characters. No required backfill or caption-generation subsystem. Existing rows without a specific alt use the selected public title; specific partner-supplied alt is English and has `alt_locale=en`, otherwise `alt_locale=content_locale`.

Materialized media writes use `type=cover|gallery`, accepting legacy `type=image` on reads. Preserve the existing at-most-20/distinct-URL input rules; duplicate authored URLs fail validation. Select the first flagged cover (or first valid media if no flag) and retain remaining sort order. Keep one authoritative cover selection and synchronize the legacy cover URL. Legacy duplicates become one public photo. Omitted media preserves rows. A provided replacement list replaces media atomically with the source; omitted alt for an existing URL retains its stored specific alt for older writers, while explicit null clears it.

Public gallery reads prefer typed cover/gallery records; legacy image rows are read in sorted order, deriving their cover from matching legacy URL when possible. The legacy URL is not prepended as an extra stale photo to an otherwise usable gallery. Only a media-empty tour falls back to its real legacy cover. Extend shared model readers before changing write types, including search/partner consumers. No thumbnails, coordinates or captions are fabricated.

## Exact Eligibility and Projections

Operator summary: only approved active profiles with public name, description and logo. `verified=true` reflects those conditions. Eligible tour count and review aggregates use the exact shared public-bookability check after eager-loading availability, not only the approximate bookable scope. Review states remain visible/flagged; zero review_count yields average_rating=null. No contact/address/tax/payout/account identifiers leave the projection.

Related projection: destination/category candidates exclude current/unpublished tours and apply exact eligibility. Rank matching destination, then matching category, then genuine rating, existing review-count tie and stable ID. Scan bounded pages until four eligible cards or exhaustion; optional internal limit clamps at eight. No new public limit parameter or recommendation table.

## Corrective Adoption and Rollback

- Preserve existing schema migrations. Add only nullable alt_text with a forward migration; ordinary application rollback retains it and all source/derived data.
- New bounded command `tours:adopt-itineraries --dry-run --after-id=0 --limit=100` inspects records in ID order (limit 1-500). Apply normalization in a transaction to existing EN rows whose itinerary is null only. No row creation without valid source.
- Legacy arrays of strings normalize to numbered days with string titles; structured arrays preserve valid days/stops/order. Any malformed/partly invalid record normalizes as a whole to `[]`, not partial fabricated reliable content. Report invalid/empty/valid/skipped-populated/missing-EN counts and resume cursor, without logging raw content.
- Re-read eligibility under the same Tour-first lock before adoption so a concurrently entered itinerary cannot be overwritten. Where English exists, persist `[]` for invalid/empty legacy values; a missing EN row is reported and remains unchanged.
- A changed adopted source uses the same hash/state service and after-commit scheduling; dry-run creates no source/state/job changes. Repeated adoption preserves all populated arrays, including intentionally empty arrays.
- Backup and staging rehearsal precede a release. Do not run destructive schema rollback or delete legacy/derived records to revert a frontend release. A prior image-only media reader requires the compatibility patch before it can consume newly typed cover/gallery rows fully.
