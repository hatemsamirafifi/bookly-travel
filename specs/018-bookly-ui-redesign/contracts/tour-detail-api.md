# Public Tour Detail Contract

## Request

`GET /api/public/tours/{slug}?locale=en|es|it` (retain the project's existing route/query spelling). The route remains public and keeps existing 404/410 published-state behavior.

## Compatibility

Keep existing `data` fields, including `title`, `description`, `highlights`, `inclusions`, `exclusions`, `itinerary`, `content_locale`, `itinerary_locale`, `translation_warning`, `languages`, `guide_languages`, `seo`, pricing, rating and availability. `languages` remains the backward-compatible spoken-guide alias; neither guide field indicates translated-content availability.

Add `translation_status` as an enum-safe public field: `source` for the fixed authored locale and `ready`, `pending`, `stale`, or `failed` for a derivative. Keep `translation_warning=partial_translation` for visible content using a different source language. Do not include hashes, provider errors or internal job state.

The response also includes optional `difficulty_level` (`easy`, `moderate`, `challenging` or `null`), ordered `images` with the selected cover first and a legacy-cover fallback, nullable `operator`, and `related_tours` (up to four existing TourCard objects). The public operator allowlist is `name`, `description`, `logo_url`, `tour_count`, `review_count`, `average_rating`; it is null without an approved active partner profile. No contact, tax, payout or account fields are returned. Operator review counts/averages include only visible or flagged reviews attached to bookable tours. Related tours exclude the current, unpublished and unbookable tours, favoring matching destination, then category, then genuine review quality.

The additive `options` array follows the [Spec 019 supplier option contract](../../019-tour-content-data/contracts/tour-detail-api.md#supplier-option-content).

## Selection

| Requested locale | Current derived translation? | Returned content | `content_locale` | Warning |
|---|---|---|---|---|
| Source locale | N/A | Current source | source locale | absent |
| Either derivative locale | ready and source hash matches | Current derivative | requested locale | absent for general content |
| Either derivative locale | missing, pending, stale, or failed | Current source | source locale | `partial_translation` for nonempty fallback |

`itinerary_locale` reflects the language selected independently for the itinerary. A missing source itinerary returns `[]`. The page displays visible localized notices for nonempty sections that fall back to the source. SEO description and structured-data `inLanguage` follow actual content, not route locale. URL/canonical/alternate routes remain stable, but metadata must not claim fallback text is translated.

## Test contract

Backend feature tests cover all table rows, current/stale races, guide-language independence, no private fields, and unchanged 404/410/availability. Frontend type/component and Playwright tests cover visible warning, automatic ready transition on refresh and EN/ES/IT display.
