# Data Model: Bookly Redesign and Tour Localization

## Existing authoritative entities

- **Tour** (`tours`): partner owner, publication status, location/category, availability, price in minor units, cover/media and `guide_languages` code array. Guide codes describe spoken service, never content availability.
- **TourTranslation** (`tour_translations`): one row per tour and locale with title, description, highlights, inclusions, exclusions, meeting point, cancellation policy and localized itinerary. English is the only partner-authored source. Add optional `important_information` to the same localized record; no change to current non-null title requirement for stored rows.
- **TourMedia**, **PricingTier**, **AvailabilityRule/Exception**, **Review**, **Booking** and **Partner** keep their existing ownership, authorization and lifecycle semantics.

## New TourTranslationState

One row per tour and derived locale (`es`, `it`) in an additive `tour_translation_states` table:

| Field | Type | Constraint / meaning |
|---|---|---|
| `tour_id` | FK | Cascade delete with Tour |
| `locale` | string(2) | `es` or `it`; unique with `tour_id` |
| `source_hash` | char(64) | SHA-256 of canonical, ordered EN translatable-field JSON for the queued/current revision |
| `translated_hash` | char(64), nullable | EN hash from which stored derivative was generated |
| `status` | string | `pending`, `ready`, `stale`, `failed` |
| `last_error_code` | string, nullable | Sanitized category only; never provider body, request, key or draft text |
| timestamps | datetime | Operational observability; not a substitute for hash checks |

**Invariant:** A derived `tour_translations` row is publicly current only when state is `ready` and `translated_hash === current EN hash`. Missing state or row is not current. No placeholder translated row is inserted. The state table is backend-only; public response may expose a safe enum, not hashes/errors.

## Lifecycle

1. Partner creates or edits EN source. Validate title and description before submission; optional source fields may be absent.
2. If the canonical EN field hash changes, upsert ES/IT states: `stale` where a derivative row exists, otherwise `pending`. Dispatch one job per locale after commit.
3. Worker reads current EN snapshot; if its hash differs from the job hash, abandon without a write. Translate allowed text through Gemini, validate shape, and under row lock check the hash again. Only then write the complete locale row and mark `ready` with `translated_hash`.
4. Transient provider failure retries with bounded backoff; terminal failure marks `failed` only if the job still matches current source. Retry/requeue remains possible.
5. On EN change during in-flight generation, old results cannot become ready. Existing ES/IT rows remain recoverable but are not served.
6. Published tours remain published during translation changes. Publication requires EN title/description plus existing partner/pricing/media guards, not ES/IT.
7. Legacy ES/IT rows without matching state are treated as unverified, not deleted; a bounded administrative backfill queues regeneration.

## Content and display rules

- Public detail, cards/search projections, SEO and structured data select one current translation for requested locale, or the current EN source with an explicit warning and actual `content_locale=en`.
- Localized itinerary follows the same current/EN decision. Absent optional EN itinerary stays absent; there is no fabricated itinerary.
- Language labels in the interface use next-intl/Intl, while `guide_languages` are unchanged stable codes.
- Public API never exposes state hashes, provider errors, secret configuration or private partner fields.

## Migration and rollback

Add the state table and optional translation field without dropping legacy columns or translations. Existing rows stay intact. Apply backend schema before workers/frontends. Backfill in batches outside migration. Rollback application code can ignore the table and field; destructive cleanup requires a later release.
