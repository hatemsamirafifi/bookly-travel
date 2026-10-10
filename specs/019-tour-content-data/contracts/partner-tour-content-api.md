# Partner Tour Content Contract: Spec 019

**Governance**: [Constitution v2.1.0](../../../.specify/memory/constitution.md)
**Source requirements**: FR-001-013, FR-017-021, FR-030.
**Status**: Target completion of existing owned routes; implementation acceptance pending.

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

`title` and `description` remain English shorthand; existing optional top-level source fields and itinerary remain compatible. `translations.en` is the structured English form. Explicit nested keys take precedence for that field; shorthand fills absent nested keys; other absent keys preserve existing values. Validate this canonical patch before applying it, not a partial subset silently discarded by Laravel validation.

Allowed source fields: title, description, highlights, inclusions, exclusions, meeting_point, cancellation_policy, itinerary, important_information. Read/write all of them consistently in create and update. Source title max120, description max5000, list max30/item500, meeting_point max500, cancellation_policy max2000; preserve existing top-level create requirements (including description min100) and non-source required fields. Null clears nullable text, [] clears a list; omitted keys do neither. Title remains a string; trimmed nonempty EN title/description are required for submit/approval. Draft snapshots may be incomplete; a snapshot is not permission to clear a published tour's required live source.

Reject partner-authored translations.es/it or unsupported derived locales and platform-owned source_hash, translated_hash, translation_status(es), readiness, and provider/error/job state in materialized writes with field-specific 422 feedback. Legacy stored derivatives are preserved. Ignore neither attempted state forgery nor unsupported source edits silently.

Example source portion only (all other existing required create fields still apply):

```json
{
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

This shortened example is not a complete valid create request; use the seeded existing form for executable acceptance.

## Itinerary Validation

| Value | Rule |
|---|---|
| itinerary | Array max30; [] permitted |
| day.day | Required integer 1-30; preserve array ordering |
| day/stop.title | Required nonblank string max160 |
| day/stop.description | Null or string max2000 |
| day.stops | Array max20; absent normalizes to []; zero stops permitted |
| stop.duration_minutes | Null or integer 1-1440 |

Report exact nested field paths for invalid values; do not partially save source/media. English editor duration controls and bounds agree with this contract. Localization of form errors belongs to the page locale; raw provider failures never appear in a field error.

## Difficulty, Guide Codes and Media

Supplied difficulty_level is easy/moderate/challenging; absent old difficulty remains absent/null on read. Older-tour restoration must not inject the new-tour easy default; omit untouched missing difficulty from updates. guide_languages and compatible languages alias are spoken codes, max20 distinct entries; normalize case and preserve existing supported-code syntax. guide_languages wins when both are supplied. Neither depends on English/Spanish/Italian content readiness.

Media retains existing ordered URL/is_cover input and at most 20 authored media objects. Add optional nullable English alt_text max500. Select first flagged cover or first real image if none; preserve distinct-URL input validation, with duplicates rejected through field-specific 422. Public reads deduplicate legacy repeated URLs while preserving order/cover selection. New storage type is cover/gallery; reads accept old image rows. Omitted media preserves all rows. Provided replacement and canonical source changes commit together. For an existing image URL, omitted alt preserves prior specific alt, explicit null clears it. Keep cover_image_url synchronized for compatibility; no-photo response remains empty.

## Draft and Publishing Boundaries

Opaque draft endpoints preserve incomplete payload round trips, partner ownership, retention and response shape; they do not update published canonical source or dispatch provider generation merely from autosave. Reuse existing wrappers to restore snapshots on the existing tour editor. Preserve the existing payload-array boundary, including legacy string-array itineraries covered by TourDraftTest. Apply canonical itinerary/source validation only when materializing a snapshot after restoration/normalization; never silently alter stored opaque legacy payloads. New ES/IT authoring or server-owned state is rejected on materialized writes and cannot be applied from a snapshot.

The wizard's existing draft-tour create remains available for its valid basic fields; new incomplete local wizard state remains restorable locally. This phase does not promise a new server endpoint for an unsaved blank tour. Save Draft and Submit/Approve use separate validation so an existing incomplete snapshot can be saved/restored while publication is refused until English and all existing pricing/cover/lifecycle gates pass.

English source changes update revision states and schedule ES/IT after commit without waiting for generation; unchanged saves do not invalidate derivatives. Queue/provider outage must not turn an accepted English save into a false failure; persisted desired state is recoverable. Approval never requires ES/IT readiness.

## Owned Response and Privacy

Keep existing tour/source/media/draft fields and envelopes. Add or retain sanitized translation_statuses `{es:pending|ready|stale|failed,it:...}` on owned detail; publicStatus performs current-hash checks. No source hashes, API credentials, provider bodies, prompts, internal error traces or loaded state models are serialized. A partner's private tour does not become publicly visible because it has a translation row.

Frontend read/write DTOs represent actual structured itinerary, difficulty_level, guide_languages and readiness without casts through unknown. Existing legacy difficulty alias, where present, is adapted on read; writes use authoritative difficulty_level. Older persisted wizard state is normalized with missing arrays/default form state rather than treated as a current fully populated payload.

## Compatibility and Tests

Verify every deployed writer uses EN before activating the already-approved ES/IT restriction against an older deployed backend. This branch already restricts those writes; do not weaken it to conceal a rollout mismatch. Retain stored old derivatives; readers fall back until a current generation exists.

Extend existing create/draft/translation/moderation suites with all source-field round trips, omission/null/[] behavior, bounds, owned IDs versus user IDs, source-state forgery, concurrent save/completion, no-media-change retention, blank draft versus rejected publish, optional source-field editing, and generated locale status privacy. Frontend/browser tests exercise EN-only entry, duration/difficulty/media, localized nested errors and pending/failed/ready statuses through the actual existing routes.
