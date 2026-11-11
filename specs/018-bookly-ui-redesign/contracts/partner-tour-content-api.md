# Partner Tour Content Contract

## Existing endpoints

Retain existing authenticated, partner-owned create/show/update/draft/submit endpoints and their response envelopes. No public or client-side Gemini endpoint is added.

## Authoring payload

- Creation accepts one `source_locale` from `en`, `es`, and `it`. Legacy callers that omit it default to `en`. Updates cannot change it. Top-level `title` and `description` address that source.
- `translations.{source_locale}` is the structured source form. It may include `title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`, `itinerary`, and optional `important_information`.
- The partner UI writes only the fixed source. Writes to either derived locale receive field-specific validation errors. Previously stored derivatives remain available for migration and backfill.
- `guide_languages` (and existing `languages` alias) remain tour-level spoken-guide codes, not translation locales.
- Do not accept translation status/hash as partner input. These are server-owned.

## Publication

Submission and admin approval require a nonempty title and description in the fixed source locale and retain existing authorization and lifecycle checks. Derivative readiness does not block eligible publication. Source changes queue both derivatives after commit. Gemini jobs use the current revision and superseded jobs cannot overwrite it.

## Response

Partner show may add `translation_statuses` keyed by the two derived locales for operational visibility. It must not return Gemini credentials, source hashes or provider error bodies. Existing tour and translation fields are retained.

Option text, revision handling, and option IDs follow the [Spec 019 supplier option contract](../../019-tour-content-data/contracts/partner-tour-content-api.md#supplier-option-content).

## Tests

Verify authorization, immutable source selection, source-required submission and publication, rejection of partner-authored derivatives, revision-safe jobs, and sanitized status. The [Spec 019 partner contract](../../019-tour-content-data/contracts/partner-tour-content-api.md) defines current acceptance coverage.
