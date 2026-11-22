# Partner Tour Content Contract: Spec 019

**Governance**: [Constitution v2.1.0](../../../.specify/memory/constitution.md) at original design; synchronized 2026-11-10 with [Constitution v3.0.0](../../../.specify/memory/constitution.md). The tour-content source policy and criteria are defined in the [spec.md Amendment Record](../spec.md); where this file says English/EN as the authored source, the active rule is the tour's fixed canonical source locale.
**Source requirements**: FR-001-013, FR-017-021, FR-030, FR-031-FR-040.
**Status**: Target completion of existing owned routes; EGM-003 revision-governed implementation and Round 1 review remediations complete on 2026-11-17, pending final acceptance gate closure. The revision-governed section below was synchronized on 2026-11-14 to the [EGM-003 technical design v3](../../../docs/designs/supplier-portal-egm003-governance-v3.md) and its [execution file/test manifest](../../../docs/designs/supplier-portal-egm003-execution-plan.md); the earlier 2026-11-15 [interim review](../../../docs/reports/supplier-portal-egm003-checkpoint-review.md) failure record is preserved as historical context.

## Existing Routes and Authorization

| Method / path | Existing operation / envelope |
|---|---|
| POST /api/partner/tours | Create materialized draft tour; preserve existing 201 data envelope |
| GET /api/partner/tours/{id} | Read own tour; preserve data envelope and existing loaded content |
| PUT /api/partner/tours/{id} | Update owned tour; preserve data envelope |
| POST /api/partner/tours/{id}/drafts/save | Save existing owned tour's opaque incomplete payload snapshot; preserve existing draft response |
| GET /api/partner/tours/{id}/drafts/latest | Latest existing owned snapshot; retain existing no-draft 404 |
| POST /api/partner/tours/{id}/submit | Governed submission for review; existing data/message response |

Preserve auth:sanctum, partner middleware and throttling. Unauthenticated=401; missing partner capability/record returns the existing 404; restricted writes by an inactive or unapproved partner retain the existing 403 ONBOARDING_STATUS_BLOCKED response. Cross-partner/unknown tour remains 404 through the scoped lookup. Partner records may have IDs different from user IDs: do not compare tour.partner_id directly to the user ID. Admin approval remains its existing internal/API action; no public approval or Gemini endpoint.

## Source Payload and Normalization

`title` and `description` top-level shorthand address the tour's canonical source locale (`tours.source_locale`, fixed at creation); existing optional top-level source fields and itinerary remain compatible. `translations.{source_locale}` is the structured source form — for pre-amendment tours and tours authored in English that path is `translations.en`. Explicit nested keys take precedence for that field; shorthand fills absent nested keys; other absent keys preserve existing values. Validate this canonical patch before applying it, not a partial subset silently discarded by Laravel validation.

Allowed source fields: title, description, highlights, inclusions, exclusions, meeting_point, cancellation_policy, itinerary, important_information. Read/write all of them consistently in create and update. Source title max120, description max5000, list max30/item500, meeting_point max500, cancellation_policy max2000; preserve existing top-level create requirements (including description min100) and non-source required fields. Null clears nullable text, [] clears a list; omitted keys do neither. Title remains a string; a trimmed nonempty source-locale title/description are required for submit/approval. Draft snapshots may be incomplete; a snapshot is not permission to clear a published tour's required live source.

Reject partner-authored derivatives (the two non-source locales of the tour) or other unsupported derived locales, and platform-owned source_hash, translated_hash, translation_status(es), readiness, and provider/error/job state in materialized writes with field-specific 422 feedback. `source_locale` itself is immutable after creation. Legacy stored derivatives are preserved. Ignore neither attempted state forgery nor unsupported source edits silently.

Example source portion only (all other existing required create fields still apply):

```json
{
  "source_locale": "en",
  "translations": {
    "en": {
      "title": "Morning walking tour",
      "description": "Current English description...",
      "highlights": ["Old-town route"],
      "inclusions": ["Live guide"],
      "exclusions": [],
      "important_information": ["Comfortable walking shoes recommended"],
      "itinerary": [{
        "day": 1,
        "title": "Old town",
        "description": null,
        "stops": [{"title": "Main square", "description": null, "duration_minutes": 30}]
      }]
    }
  },
  "difficulty_level": "easy",
  "guide_languages": ["en", "de"]
}
```

This shortened example is not a complete valid create request; use the seeded existing form for executable acceptance. A tour created with `source_locale:"es"` or `"it"` places its authored source under `translations.es` / `translations.it` at creation; the source locale is fixed afterward.

## Itinerary Validation

| Value | Rule |
|---|---|
| itinerary | Array max30; [] permitted |
| day.day | Required integer 1-30; preserve array ordering |
| day/stop.title | Required nonblank string max160 |
| day/stop.description | Null or string max2000 |
| day.stops | Array max20; absent normalizes to []; zero stops permitted |
| stop.duration_minutes | Null or integer 1-1440 |

Report exact nested field paths for invalid values; do not partially save source/media. The partner editor duration controls and bounds agree with this contract in every source locale. Localization of form errors belongs to the page locale; raw provider failures never appear in a field error.

## Difficulty, Guide Codes and Media

Supplied difficulty_level is easy/moderate/challenging; absent old difficulty remains absent/null on read. Older-tour restoration must not inject the new-tour easy default; omit untouched missing difficulty from updates. guide_languages and compatible languages alias are spoken codes, max20 distinct entries; normalize case and preserve existing supported-code syntax. guide_languages wins when both are supplied. Neither depends on English/Spanish/Italian content readiness.

Media retains existing ordered URL/is_cover input and at most 20 authored media objects. Add optional nullable source-locale alt_text (English for pre-amendment rows) max500. Select first flagged cover or first real image if none; preserve distinct-URL input validation, with duplicates rejected through field-specific 422. Public reads deduplicate legacy repeated URLs while preserving order/cover selection. New storage type is cover/gallery; reads accept old image rows. Omitted media preserves all rows. Provided replacement and canonical source changes commit together. For an existing image URL, omitted alt preserves prior specific alt, explicit null clears it. Keep cover_image_url synchronized for compatibility; no-photo response remains empty.

## Supplier option content

Create and update accept `options`. The source-language option title and pickup instructions follow the tour's fixed source. Create rejects supplied option IDs. Update accepts existing IDs scoped to the owned tour, preserves rows when `options` is omitted, and retires omitted options when a list is sent. An empty list retires all options. Changing source option text or order can trigger regeneration. Price, capacity, schedule, and guide codes are shared across locales. These draft-tour behaviours are unchanged; on a **published** tour the governed option text and option structure follow the revision-governed section below. [Supplier plan](../../../docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md#٣-البيانات-وقواعد-الحجز)

## Draft and Publishing Boundaries

Opaque draft endpoints preserve incomplete payload round trips, partner ownership, retention and response shape; they do not update published canonical source or dispatch provider generation merely from autosave. Reuse existing wrappers to restore snapshots on the existing tour editor. Preserve the existing payload-array boundary, including legacy string-array itineraries covered by TourDraftTest. Apply canonical itinerary/source validation only when materializing a snapshot after restoration/normalization; never silently alter stored opaque legacy payloads. Writes to any non-source locale and writes to server-owned state are rejected when materializing a snapshot.

The wizard's existing draft-tour create remains available for its valid basic fields; new incomplete local wizard state remains restorable locally. This phase does not promise a new server endpoint for an unsaved blank tour. Save Draft and Submit/Approve use separate validation so an existing incomplete snapshot can be saved/restored while publication is refused until canonical-source content and all existing pricing/cover/lifecycle gates pass.

Canonical-source content changes update revision states and schedule the two derived locales after commit without waiting for generation; unchanged saves do not invalidate derivatives. Queue/provider outage must not turn an accepted source save into a false failure; persisted desired state is recoverable. Approval never requires derived-locale readiness.

## Owned Response and Privacy

Keep existing tour/source/media/draft fields and envelopes. Add or retain sanitized translation_statuses for the tour's derived locales (for example `{es:pending|ready|stale|failed,it:...}` on an English-source tour) on owned detail; publicStatus performs current-hash checks. No source hashes, API credentials, provider bodies, prompts, internal error traces or loaded state models are serialized. A partner's private tour does not become publicly visible because it has a translation row.

Frontend read/write DTOs represent actual structured itinerary, difficulty_level, guide_languages and readiness without casts through unknown. Existing legacy difficulty alias, where present, is adapted on read; writes use authoritative difficulty_level. Older persisted wizard state is normalized with missing arrays/default form state rather than treated as a current fully populated payload.

## Compatibility and Tests

Verify every deployed writer addresses the tour's canonical source locale before activating the derived-locale-authoring restriction against an older deployed backend. This branch already restricts those writes; do not weaken it to conceal a rollout mismatch. Existing legacy rows default to `en` as a compatibility default, not a restriction on new authoring. Retain stored old derivatives; readers fall back until a current generation exists.

Extend existing create/draft/translation/moderation suites with all source-field round trips, omission/null/[] behavior, bounds, owned IDs versus user IDs, source-state forgery, concurrent save/completion, no-media-change retention, blank draft versus rejected publish, optional source-field editing, and generated locale status privacy. Cover the tour's canonical source locale at creation, its immutability afterward, source-locale validation at both gates, and publication while derivatives are pending or failed. Frontend/browser tests exercise canonical-locale entry, duration/difficulty/media, localized nested errors and pending/failed/ready statuses through the actual existing routes.

## Revision-Governed Content (EGM-003 owner decision, 2026-11-14)

Partners own and edit canonical tour content. A content edit to a **published** tour is submitted as a separate reviewable revision and the approved public version stays unchanged until that revision is approved. Gemini derivatives are never manually edited. Administrators request changes with a mandatory reason and specific fields; an exceptional administrator correction is proposed only through a dedicated, authorized, audited domain action and never through the general Filament form. There is no emergency publishing path, and the correction action cannot approve or publish. The [EGM-002 publication guard](../../../backend/app/Domains/Admin/Services/TourPublicationGuardService.php) remains the only route to `published`.

**Design of record.** [EGM-003 technical design v3](../../../docs/designs/supplier-portal-egm003-governance-v3.md), with its [execution file/test manifest](../../../docs/designs/supplier-portal-egm003-execution-plan.md), supersedes the failed v1/v2 proposals; the v1/v2 files are retained as historical review evidence and are not active contracts. The owner policy is [ADR 0024](../../../docs/adr/0024-tour-content-governance.md) and Constitution v3.0.0 is unchanged.

**Correction review.** Ordinary administrative review of an exceptional correction is the **selected implementation choice** under the owner-approved correction policy, not an additional owner quote. No emergency approval, automatic publication or four-eyes path is introduced. The correction permission has no automatic ordinary-admin grant; the existing super-admin wildcard and explicit grants apply.

**Enforcement boundary.** Refusals are enforced in authenticated Laravel boundaries, domain Actions/Services and the known writer inventory. No PostgreSQL `GUC` or trigger bypass mechanism is introduced. After the coordinated cutover, an unpatched old application build and direct operator SQL are not supported mutation interfaces; a rolled-back client receives the refusal and cannot bypass it.

### Owned read — additive field

`GET /api/partner/tours/{id}` keeps its existing envelope and fields and adds one additive object for **every owned tour**:

```jsonc
"content_governance": {
  "source_locale": "es",
  "generation_epoch": 4,
  "content_version": 4,
  "approved_revision": { "id": 12, "revision_number": 3, "kind": "legacy_baseline", "state": "approved",
                         "payload_hash": "…", "created_at": "…" },
  "active_candidate": { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "proposed",
                         "payload_hash": "…", "base_revision_id": 12, "submitted_at": "…", "change_summary": "…" },
  "latest_revision":  { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "proposed",
                         "payload_hash": "…", "base_revision_id": 12, "submitted_at": "…" },
  "open_requests":    [ { "id": 9, "context": "published_review", "state": "open", "reason": "…",
                           "field_refs": [ { "path": "source.meeting_point", "note": "…" } ],
                           "created_at": "…" } ]
}
```

Rules for this object:

- It is **never `null`** for an owned tour that the platform now protects. A published legacy tour is returned with a lazily captured baseline; the baseline is captured under the Tour lock **before** the response is built.
- `approved_revision` is `null` for a never-public tour, and `active_candidate` is `null` when no candidate exists. A never-public tour still reports its **real** `generation_epoch`, so an initial-review response needs no invented revision ID.
- `kind` is one of `legacy_baseline`, `partner_edit`, `admin_correction`, `publication`. Request `context` is one of `initial_review`, `published_review`, `correction`; request `state` is one of `open`, `answered`, `approved`, `rejected`, `closed`, `superseded`.
- No payload, hash or request body content beyond the bounded summary fields above is exposed by this summary; the full payload is read through the single-revision route below.

### New routes

| Method / path | Operation |
|---|---|
| `GET /api/partner/tours/{id}` | Owned tour read; adds the `content_governance` object described above |
| `GET /api/partner/tours/{id}/revisions/{revisionId}` | Read one owned candidate revision **including its full immutable payload**, its payload hash (replacement token; source/content generation hashes remain internal), its kind/state, its base revision ID, its timestamps, its reason/fields and its normalized diff versus the base |
| `GET /api/partner/tours/{id}/revisions` | Bounded paginated owner-scoped revision history (read-only) |
| `GET /api/partner/tours/{id}/revision-requests` | Bounded paginated owner-scoped change-request history (read-only) |
| `POST /api/partner/tours/{id}/revisions` | Create a candidate revision from a content patch over the approved revision; observed base ID plus bounded source/presentation/options/media PATCH; **201** with the new draft and complete payload metadata |
| `PUT /api/partner/tours/{id}/revisions/{revisionId}` | Replace the candidate: observed base ID + observed payload hash + patch; **200** with the **NEW** replacement ID and full payload metadata; the old ID becomes `superseded`. The endpoint name does not mean mutable snapshot storage |
| `POST /api/partner/tours/{id}/revisions/{revisionId}/submit` | Submit the owned current draft for the ordinary review; 200 state response or the documented 409/422/404 |
| `POST /api/partner/tours/{id}/revisions/{revisionId}/withdraw` | Withdraw the partner's own current draft/proposed candidate; 200 state response or the documented 409/422/404 |
| `POST /api/partner/tours/{id}/revision-requests/{requestId}/respond` | Partner response to an open request: submitted revision ID for a published context, or observed generation epoch for an initial context, plus a bounded note; 200 answered request |

`auth:sanctum` + `partner` middleware and the existing throttle are preserved. Unauthenticated is 401; a missing partner capability or an unknown/cross-partner tour is 404 through the scoped lookup; a restricted partner keeps the existing 403 `ONBOARDING_STATUS_BLOCKED` response. No public or administrator REST approval endpoint is added.

All candidate operations identify tour **and** revision and scope ownership before validation, so an unknown or foreign revision remains 404. Ownership, permissions and account eligibility are re-read from the locked rows, not trusted from a caller-supplied model.

### Owned response projections

`GET tours/{id}` exposes a flat `content_governance` object with `source_locale`, `generation_epoch`, `content_version`, nullable `approved_revision`, nullable `active_candidate`, nullable `latest_revision` (preserving the highest-numbered revision summary so terminal `rejected`/`withdrawn`/`superseded` outcomes stay visible after `active_candidate` clears), and bounded `open_requests`. Approved summaries include `state: "approved"`; candidate summaries identify the exact immutable ID, number, kind, state, base, optional `change_summary`, and payload hash. Request summaries contain the request ID, context/state, mandatory reason, field references and timestamps. Full histories use the paginated owner-only endpoints; raw Eloquent request models and internal requested-content hashes are never serialized.

Full revision GET/create/replace/submit/withdraw responses put the revision fields directly under the existing `data` key: `id`, `tour_id`, `revision_number`, `kind`, `state`, `source_locale`, `change_summary`, `payload`, `payload_hash`, `base_revision_id`, relevant timestamps and `diff`. They do not introduce a `data.revision` wrapper. The response examples below abbreviate the full payload and diff. The only exposed replacement token is `payload_hash`; canonical source/content hashes and provider diagnostics remain internal. Partner frontend types and real HTTP contract tests must agree on this projection before acceptance.

### Create request and payload

```jsonc
{
  "change_summary": "Correct the meeting point",   // optional, max 500
  "base_revision_id": 12,                          // mandatory: the observed approved revision, must equal the current pointer
  "translations": { "es": { "meeting_point": "Plaza de Armas, puerta 3" } },
  "options": [ { "id": 12, "title": "Grupo pequeño", "pickup_instructions": "…" } ],
  "media": [ { "url": "https://…/a.jpg", "alt_text": "…", "is_cover": true } ],
  "presentation": { "location": "Cairo", "category_id": 7, "slug": "cairo-old-town" }
}
```

Only the tour's fixed source locale may appear under `translations`. The request-level `translations` key is the existing compatibility form for the canonical-source patch; the stored revision snapshot keeps the same nine canonical fields under its `source` tier, and field references use the stored tier names (`source.*`, `presentation.*`, `options[*].title`, `options[*].pickup_instructions`, `media[*].alt_text`) with stable option IDs and media positions from the captured context. `options` accepts authored text only for options owned by the tour; price, capacity, schedule, guide codes, membership, order and status are commercial/operational and are rejected. The candidate's option set must equal the tour's active option ids in the live order — an addition, removal or reorder is refused with `option_structure_change_not_supported`. `presentation` carries the governed tour-row presentation fields `category_id`, `slug`, `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`, `difficulty_level`, `guide_languages` and `cover_image_url`; commercial and operational keys are rejected. `media` keeps the existing ordered-list, distinct-URL and cover-selection semantics. Present-key semantics apply: omitted preserves, explicit `null` clears nullable text, `[]` clears a list. Bounds are the existing source, itinerary, option and media bounds. The merge base is the **approved revision's payload**; the server stores the resolved full snapshot.

Create preconditions: the tour is `published`; the supplied `base_revision_id` equals the current approved-revision pointer; and no active candidate exists. The server inserts a complete immutable `draft` snapshot with a new ID and revision number, and answers **409 `REVISION_ALREADY_OPEN`** identifying the conflicting revision when an active candidate already exists. A stale base is refused with **409 `REVISION_SUPERSEDED`** and nothing is written.

### Replace an existing candidate

`PUT /api/partner/tours/{id}/revisions/{revisionId}` takes `{ "base_revision_id": 12, "observed_payload_hash": "…", …patch keys… }`. The merge base is the **candidate's own stored payload**, never the latest approved revision. A payload hash that differs from the stored `payload_hash` is refused with 409 `REVISION_CONFLICT` and nothing is written, so the client must reload through the full-payload read endpoint and re-apply its edits.

Replacement is **immutable**: the server merges the patch against that stored candidate, inserts a **NEW** immutable `draft` row with a new ID and revision number, marks the previous row `superseded`, and returns the NEW ID/number. A payload is never mutated under a reviewed ID. A `draft` or `proposed` candidate may be replaced, including an active administrator correction that is explicitly being replaced. Linked answered/correction requests become `superseded`; unlinked open requests remain open.

| Condition | Status | Body |
|---|---|---|
| Created | 201 | `{ "data": { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "draft", "payload_hash": "…", "base_revision_id": 12, "created_at": "…" }, "message": "Revision saved." }` |
| Replaced | 200 | `{ "data": { "id": 16, "revision_number": 5, "kind": "partner_edit", "state": "draft", "payload_hash": "…", "base_revision_id": 12, "created_at": "…" }, "message": "Revision replaced." }` — the previous ID is superseded |
| Stale `base_revision_id`, or the tour is no longer `published` | 409 | `{ "message": "…", "error_code": "REVISION_SUPERSEDED" }` |
| A candidate is already open and this is not a replacement of it | 409 | `{ "message": "…", "error_code": "REVISION_ALREADY_OPEN", "revision_id": 14 }` |
| `observed_payload_hash` differs from the stored payload hash | 409 | `{ "message": "…", "error_code": "REVISION_CONFLICT", "revision_id": 15 }` |
| The candidate is already in a terminal state | 409 | `{ "message": "…", "error_code": "REVISION_TERMINAL" }` |
| Replaying a superseded candidate | 409 | `{ "message": "…", "error_code": "REVISION_SUPERSEDED" }` |
| Commercial, operational, derivative-locale or platform-owned key in the payload | 422 | `{ "message": "…", "errors": { "<path>": ["…"] } }` |
| Content bounds violated | 422 | `{ "message": "…", "errors": { "translations.es.itinerary.0.stops.2.duration_minutes": ["…"] } }` |
| Option id not owned by the tour | 422 | `{ "message": "…", "errors": { "options.0.id": ["The selected option does not belong to this tour."] } }` |
| Option set differs from the tour's active options or their order | 422 | `{ "message": "…", "error_code": "option_structure_change_not_supported" }` |
| Field reference outside the allowlist, unresolvable, or a changed path not declared | 422 | `{ "message": "…", "error_code": "unknown_field_reference" }` / `{ "message": "…", "error_code": "undeclared_field_change" }` |
| Unknown or cross-partner tour or revision | 404 | `{ "message": "Not found." }` |
| Partner not approved or suspended | 403 | existing `ONBOARDING_STATUS_BLOCKED` body |

`POST …/revisions/{revisionId}/submit` returns 200 with the submitted revision, 409 `REVISION_SUPERSEDED` / `REVISION_TERMINAL`, 422 `REVISION_CONTENT_INVALID` when the merged candidate lacks a trimmed non-empty canonical title and description in the source locale, and 404 for an unknown or non-owned revision.

`POST …/revision-requests/{requestId}/respond` takes `{ "revision_id": 15, "response_note": "…" }` for a **published** request context, or `{ "observed_generation_epoch": 4, "response_note": "…" }` for an **initial-review** request context, and returns 200 with the answered request. It refuses a mismatching tour or ownership with 404, a stale context or request state with 409 `REQUEST_NOT_OPEN`, and an invalid reference or candidate link with 422. A published-context response may link **only** the current owned submitted candidate that carries the request's base; an initial-context response requires the observed epoch to equal the current owned source epoch. An answered request is never silently reused for another revision.

### New machine-readable error codes

`content_requires_revision`, `option_structure_change_not_supported`, `REVISION_CONFLICT`, `REVISION_ALREADY_OPEN`, `REVISION_SUPERSEDED`, `REVISION_TERMINAL`, `REVISION_CONTENT_INVALID`, `REQUEST_NOT_OPEN`, `unknown_field_reference`, `undeclared_field_change`. They travel on the existing `{"message": …}` and `{"message": …, "errors": {…}}` shapes; no new envelope is introduced. The exact offending input paths are reported; mixed dirty commercial/source fields are never silently dropped.

### Write routing for a published tour

`PUT /api/partner/tours/{id}` keeps its contract for operational and commercial fields on every tour. For a **published** tour the request is classified inside a fresh Tour lock **before any write**: governed fields that actually change, governed fields that are unchanged, and operational fields. The governed set is the canonical source text and its aliases, every governed presentation field (`category_id`, `slug`, `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`, `difficulty_level`, `guide_languages`, `cover_image_url`), the media projection and the authored option text.

If any governed value changes, the **whole request** is refused with one atomic 422 `content_requires_revision` and the exact paths, and **nothing** is written — neither the governed nor the operational field, so there is no partial save and no silent drop. When no governed value changes, the request proceeds exactly as today: unchanged round-tripped content may accompany a legitimate operational price/capacity/schedule save. A submitted option array whose authored text, identity and order are unchanged keeps its existing commercial-edit contract; a text change is refused with `content_requires_revision`, and a membership or order change is refused with `option_structure_change_not_supported`.

**Published option structure fails closed in this slice.** Option additions, reactivation, retirement and reordering on a published tour are refused with a localized explanation. This restriction is explicit, tested and recorded as remaining option-review work; it is not a claim that the A3 option slice is complete. Existing active option text can be revised through a revision, and an unchanged-text, unchanged-identity, unchanged-order option list can round-trip in an operational save. Draft-tour option functionality is unchanged.

**Mixed dirty fields are never partially applied.** If governed content **and** commercial fields are both dirty, the editor makes **no request**, retains every entered value, and shows localized instructions to save operational changes separately. Explicit content-draft and operational-only save controls each send only their own allowed fields; after either succeeds the saved baseline is refreshed while the other dirty fields are retained, and a failure keeps the dirty values and announces the error.

**Enforcement and activation.** The refusal is enforced in authenticated Laravel boundaries, domain Actions/Services and the known writer inventory; no PostgreSQL `GUC` or trigger bypass mechanism exists. It is part of the single coordinated EGM-003 activation: production enters maintenance for partner/administrator writes, queue producers and schedulers stop, old workers finish their current jobs and exit, the complete new build and additive schema are deployed, baselines are captured in bounded idempotent Tour-locked batches, and only new workers are restarted. A rolled-back client cannot save published content; an older frontend shows a recoverable error. After the cutover, an unpatched old application build and direct operator SQL are not supported mutation interfaces, so the supported rollback target is a compatible build that retains all governance guards and readers, with proposal/correction/approval writes disabled if necessary. Accepted snapshots and columns are never destructively dropped.

### Administrator surfaces

No administrator REST endpoint is added; the administrator surfaces are Filament actions backed by domain actions (`RequestTourChangesAction`, `ProposeTourCorrectionAction`, `ApproveTourRevisionAction`, `RejectTourRevisionAction`). The general Filament form carries no translation field and no canonical authoring, and is read-only for governed fields on a published tour, so no administrator surface writes a canonical or derivative translation row. Raw submitted state is compared before relationship persistence, so a forged change is rejected; read-only controls alone are insufficient.

Administrator change requests and corrections carry a reason that is non-blank after trimming and bounded at 2,000 characters, and 1–30 allowlisted field-reference objects (`path`, optional plain-text `note` max500); there is no arbitrary ten-character minimum. Published requests use the captured approved base; initial-review requests use current captured source context/hash/epoch without an invented revision. All governed source/presentation/option-text/media fields are requestable, but only the nine canonical `source.*` fields are administratively correctable in this slice. A correction must declare every field it changes, and changed fields must be a subset of declared references. The correction permission is granted only explicitly and is not granted to ordinary administrator roles by default. A correction cannot approve, publish, or change tour lifecycle state; it passes ordinary revision review — the selected implementation choice under the owner-approved policy, not a separate owner quote.
