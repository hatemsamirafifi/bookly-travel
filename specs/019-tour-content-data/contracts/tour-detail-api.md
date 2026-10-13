# Public Tour Detail Contract: Spec 019

**Governance**: [Constitution v2.1.0](../../../.specify/memory/constitution.md)
**Source requirements**: FR-014-029; [data model](../data-model.md).
**Status**: Target additive contract; implementation verification pending.

## Request and Existing Status Behavior

`GET /api/public/tours/{slug}?locale=en|es|it`

This is the actual existing route from backend bootstrap/public routes and frontend getTourDetail. Do not introduce the historical Spec 018 shorthand `/api/tours` as a replacement. Locale is required and limited to en/es/it; invalid input remains 422. Preserve rate limiting, JSON envelope and published-status behavior:

| Case | Result |
|---|---|
| Published tour | 200, `{data: ...}` |
| Published but currently unbookable | 200 with availability.is_unavailable=true; no Book Now offer in UI |
| Unknown / unpublished tour | 404 |
| Archived tour previously published | 410 |
| Existing detail rate limit exceeded | Existing 429 response/headers |

## Additive Fields and Preserved Semantics

Keep every existing field: identity, title, description, highlights/inclusions/exclusions, important_information, meeting_point, category, duration, languages, group_size, cancellation_policy, images, pricing, availability, rating, reviews, SEO, and content/itinerary language metadata. Money remains server-authoritative integer minor units. No renamed operator keys or changed field types.

| Field | Shape / target meaning |
|---|---|
| difficulty_level | Existing optional easy/moderate/challenging/null; never synthesize difficulty |
| languages / guide_languages | Stable spoken-code arrays with identical alias meaning; not content locales |
| itinerary | Ordered ItineraryDay array; `[]` for absent source; bounds in partner contract |
| content_locale | Actual general-content language, en/es/it |
| itinerary_locale | Actual nonempty itinerary language; for empty itinerary retain selected content locale without claiming translated text exists |
| translation_status | Optional compatible source/ready/pending/stale/failed enum describing general-content readiness; EN=source |
| translation_warning | Preserve optional partial_translation for a nonempty general-content or itinerary English fallback; no new value |
| images | Existing `{url,is_cover,alt}` objects, optionally adding alt_locale=en/es/it |
| operator | Existing nullable allowlisted object; add verified boolean, preserve every existing key |
| related_tours | Existing TourCard array, default at most four, never beyond eight internally |

Target operator shape:

```ts
interface PublicOperatorSummary {
  name: string;
  description: string | null;
  logo_url: string | null;
  verified: boolean;
  tour_count: number;
  review_count: number;
  average_rating: number | null;
}
```

Operator summary is null unless the partner is approved/active and its profile has a public name. A present verified flag is true only for those conditions. Before counts/averages, candidate tours must pass exact published/current-bookability checks, including future availability. Include only visible/flagged reviews for those tours; zero reviews yields null average. Public data never contains email, phone, address, tax, payout, Stripe/payment-provider IDs, internal user IDs, state hashes, provider errors, job payloads, or private notes.

## Locale Selection and UI Disclosures

A derivative is current only if its row exists, state is ready and translated_hash equals the current English source hash. Missing state/row, hash mismatch, pending/stale/failed means current English fallback. A late job result cannot change this rule.

| Requested page | General source selection | Itinerary selection | Notices |
|---|---|---|---|
| en | Current EN | EN array or [] | No locale fallback |
| es/it, current derivative | Requested content | Requested nonnull itinerary | No fallback |
| es/it, current general content but itinerary null | Requested content | Nonempty current EN itinerary | Itinerary-specific notice only |
| es/it, derivative unavailable/outdated | Current EN | Current EN array | General-content notice; identify any nonempty EN itinerary independently |
| es/it, no English itinerary | Current selected general content | [] | No itinerary section or itinerary notice |

An explicitly empty current derivative itinerary is a real empty array, not a missing translation; do not replace it with English simply because its length is zero. Null indicates unavailable itinerary. Empty English arrays must not trigger a misleading partial_translation solely because they are nonnull.

Keep partial_translation compatible as the summary flag. UI determines which notice to show from actual content_locale and itinerary_locale versus the requested page locale, with nonempty-content checks. It must not label all content English when only itinerary fell back. Localize notices in the page locale and set appropriate lang attributes on displayed source text. Do not link guide-code availability to readiness.

A subsequent full detail load after current completion returns the ready derivative. Preserve fresh SSR reads (explicit no-store where needed), with no new polling or time-to-translation guarantee. Search/index refresh is after commit; test that a delayed source/index update cannot make an old derivative appear current.

## Gallery and Related Selection

Gallery contains real usable HTTP(S) media. Read legacy image and new cover/gallery types; cover first, remaining sorted order, URL duplicates removed. Use legacy cover only when there is no usable gallery. Specific alt_text is English with alt_locale=en; otherwise alt is the selected title with alt_locale=content_locale. TourImage/ImageGallery consume that locale through appropriate lang attributes on image/caption text; action labels stay in the page locale. Older clients may ignore the optional locale field. No photos means [] rather than a stock placeholder claimed as tour media.

Related candidate selection preserves existing destination/category matching and existing TourCard fields. Exclude current/unpublished/unbookable candidates. Rank matching destination, then category, genuine review quality, review-count tie and stable ID. Fetch bounded candidate pages until four eligible cards or candidate exhaustion; do not stop simply after 32 invalid candidates. No public limit parameter is introduced. Lists are not padded with unrelated/ineligible records.

## SEO and Structured Content

Preserve canonical/hreflang routes; metadata title/description derive from the selected current content. Structured content describes the same visible facts and languages. Include the real ordered itinerary with actual day/stop text; never invent coordinates or classify an activity as an attraction without evidence. Represent an actual starting meeting point through documented tripOrigin with a Place name. Include all safe real gallery URLs; preserve JSON script escaping. No aggregateRating without eligible reviews and a genuine valid rating; no invented pricing or availability.

New property/type combinations must be checked against [TouristTrip](https://schema.org/TouristTrip) and [tripOrigin](https://schema.org/tripOrigin). Existing fallback language metadata is retained; this contract does not claim a rich-result entitlement or that historical markup has already passed schema validation.

## Contract Acceptance

Use backend response assertions and frontend type/component/SSR fixtures for all selection rows, exact privacy allowlists, independent/empty itinerary fallback, difficulty null, media/alt ordering, expired operator tours, >32 invalid recommendation candidates, deterministic ties, and unchanged 404/410/unavailable behavior. Verify existing consumer fields remain usable. Tests of fake fixture JSON are supplemented by integrated partner writes and actual backend detail responses.
