# Partner Tour Content Contract

## Existing endpoints

Retain existing authenticated, partner-owned create/show/update/draft/submit endpoints and their response envelopes. No public or client-side Gemini endpoint is added.

## Authoring payload

- `title` and `description` at top level remain the current English-source shorthand.
- `translations.en` remains the structured English-source form. It may include `title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`, `itinerary`, and optional `important_information`.
- Partner UI authors EN only. New partner writes to `translations.es` or `translations.it` are rejected with a clear validation error once the new contract is active; previously stored rows are preserved for migration/backfill. This intentional write-contract restriction requires coordinated frontend/backend rollout.
- `guide_languages` (and existing `languages` alias) remain tour-level spoken-guide codes, not translation locales.
- Do not accept translation status/hash as partner input. These are server-owned.

## Publication

Submit and admin approval both require nonempty EN title and description and retain all existing authorization/lifecycle guards. ES/IT readiness is not required. English changes on any traveler-facing field mark derived status stale/pending and enqueue regeneration after commit; unchanged content is a no-op.

## Response

Partner show may add `translation_statuses: {es: pending|ready|stale|failed, it: ...}` for operational visibility. It must not return Gemini credentials, source hashes or provider error bodies. Existing tour and translation fields are retained.

## Tests

Pest tests cover auth/ownership, EN-required publish, ES/IT not required, rejection of partner-authored derived content, guide-code independence, idempotent queueing, revision-safe completion and sanitized failure status. Browser tests cover EN-only authoring and status display.
