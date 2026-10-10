# Translation Generation and Recovery Contract: Spec 019

**Governance**: [Constitution v2.1.0](../../../.specify/memory/constitution.md)
**Scope**: Internal TourContentTranslator/job/state boundary; no public provider endpoint.

## Request Boundary

Reuse TourContentTranslator and GeminiTourTranslator. A job carries tourId, requested derived locale (es/it), and sourceHash only; never credentials, provider responses, private partner/booking/payment fields or raw prompts. The worker loads the current whitelisted English source. Translate nonempty source strings by stable paths; reconstruct into the source shape so numeric day/duration, nulls and list ordering stay unchanged.

The backend sends the existing structured JSON generateContent request with backend-only configured model/key. Prompt input is data, not instructions. Exact expected keys must return once each as nonblank strings; unknown/missing/duplicate keys or invalid lengths reject the result. Validate every source-field and itinerary bound after reconstruction, including numeric fields preserved from source. [Gemini structured outputs](https://ai.google.dev/gemini-api/docs/generate-content/structured-output) supports structured JSON but does not replace application value validation.

Specific media alt text remains English-specific accessibility text with actual alt_locale; it does not add a per-photo translation or AI-caption request.

## Revision and Atomic Completion

1. Persist canonical EN and desired ES/IT states in the source transaction. Lock Tour, then EN, then states in es/it order. Compute normalized current hash; unchanged source is a no-op.
2. Dispatch after commit to an asynchronous Redis worker. Source HTTP success is independent of provider completion and of a recoverable post-commit queue-dispatch failure.
3. Worker prechecks existence and hash. Superseded/deleted/missing-source jobs exit without a content write. Perform provider HTTP outside database locks.
4. On completion, take the same lock order and recompute source hash. Only matching current desired hash can be applied. If matching ready content already exists, derivative writing is a no-op; do not replace it merely because another worker produced different wording. Precheck matching-ready work before the provider call and allow idempotent projection refresh without regeneration, repairing a prior index-dispatch failure after ready commit.
5. Atomically store one derived tour/locale row, ready status and translated_hash. Clear sanitized error, commit, then dispatch index refresh and relevant cache invalidation.
6. Failure writes only when the same desired hash is still current and not ready. A late failure cannot demote ready/newer content. Existing old derivative remains retained but not current.

Use the existing unique tour/locale constraints and database serialization for correctness. Job dedup can save provider cost but is not a substitute for revision checks, and must not suppress a newer desired hash or failed-current retry indefinitely.

## Failure Classification and Limits

| Outcome | Behavior |
|---|---|
| Missing/invalid config or unsupported requested locale | Sanitized permanent category; source remains saved |
| HTTP 408/429/5xx or network timeout | Retry current work with bounded attempts/backoff |
| Other rejected response, blocked/incomplete output, malformed JSON/shape/value | Sanitized permanent failure; no invalid derivative write |
| Retry exhaustion | Failed for matching current hash only |
| Superseded work or failure after ready | No derivative/state mutation; matching-ready retry may refresh projections without provider work |

Retain four attempts and backoff5/15/60 seconds. Design target: provider timeout40 seconds, job timeout60, Redis retry-after90; worker timeout must not exceed the job's effective timeout, and retry-after must remain greater. Validate deployed values before release rather than assume docker defaults prove them. Automated tests use fake provider/queue; test processes must not inherit real provider credentials or make network calls.

## Recovery and Projection Freshness

Extend existing `tours:queue-translations --after-id=0 --limit=100` (limit1-500) to reconcile desired current states, including pending work whose dispatch was lost. Requeue only current unfinished revisions. Add optional `--refresh-ready` to schedule idempotent index/cache refresh for matching-ready tours without provider calls or derivative writes, covering lost projection dispatch after a ready commit. Repeated reconciliation may enqueue duplicate work, which completion safely handles; do not claim exactly-once provider calls. Index refresh loads current source/state rather than replaying translated strings captured by an older job.

Corrective adoption uses `tours:adopt-itineraries` and then the same source/hash/state service. Dry-run enqueues nothing. Current success/source changes schedule projections after commit. Do not cache a derivative as current when its hash no longer matches; detail reload selects live current source/state, while existing discovery cache/index policy remains explicit.

Monitor queue lag, unfinished/failed state counts, sanitized categories and source-fallback rate without logging prompts, raw responses or secrets. A live staging smoke uses only a configured available model and provisioned backend secret; it is not mandatory for fake-based automated tests and has not been executed in planning.

## Required Interleavings

Real PostgreSQL tests must demonstrate old completion versus newer source, two completions for the same hash, terminal failure after ready, source transaction rollback, queue failure after commit/reconciliation, changed English during legacy adoption, and concurrent source edits using a consistent lock order. Provider fake barriers/independent DB connections control the ordering; sequential hash assertions alone are insufficient evidence of row-lock correctness.
