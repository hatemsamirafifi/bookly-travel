# Public Tour Detail Contract

## Request

`GET /api/tours/{slug}?locale=en|es|it` (retain the project's existing route/query spelling). The route remains public and keeps existing 404/410 published-state behavior.

## Compatibility

Keep existing `data` fields, including `title`, `description`, `highlights`, `inclusions`, `exclusions`, `itinerary`, `content_locale`, `itinerary_locale`, `translation_warning`, `languages`, `guide_languages`, `seo`, pricing, rating and availability. `languages` remains the backward-compatible spoken-guide alias; neither guide field indicates translated-content availability.

Add `translation_status` as an enum-safe public field: `source` on EN; `ready`, `pending`, `stale` or `failed` on ES/IT. It describes content translation only. Keep `translation_warning=partial_translation` when actual content falls back to EN. Do not include hashes, provider errors or internal job state.

The response also includes optional `difficulty_level` (`easy`, `moderate`, `challenging` or `null`), ordered `images` with the selected cover first and a legacy-cover fallback, nullable `operator`, and `related_tours` (up to four existing TourCard objects). The public operator allowlist is `name`, `description`, `logo_url`, `tour_count`, `review_count`, `average_rating`; it is null without an approved active partner profile. No contact, tax, payout or account fields are returned. Operator review counts/averages include only visible or flagged reviews attached to bookable tours. Related tours exclude the current, unpublished and unbookable tours, favoring matching destination, then category, then genuine review quality.

## Selection

| Requested locale | Current derived translation? | Returned content | `content_locale` | Warning |
|---|---|---|---|---|
| EN | N/A | Current EN | `en` | absent |
| ES/IT | ready and source hash matches | Derived locale | requested locale | absent |
| ES/IT | missing, pending, stale or failed | Current EN | `en` | `partial_translation` |

`itinerary_locale` reflects actual itinerary text. Optional missing EN itinerary returns `[]`, not a fabricated one. SEO description and structured-data `inLanguage` follow actual content, not route locale. URL/canonical/alternate routes remain stable, but metadata must not claim fallback text is translated.

## Test contract

Backend feature tests cover all table rows, current/stale races, guide-language independence, no private fields, and unchanged 404/410/availability. Frontend type/component and Playwright tests cover visible warning, automatic ready transition on refresh and EN/ES/IT display.
