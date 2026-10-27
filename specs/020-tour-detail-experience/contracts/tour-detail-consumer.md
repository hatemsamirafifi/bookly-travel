# Consumer and Checkout Contract: Spec 020

**Status**: Planned compatible frontend consumer; no endpoint/response/migration changes.  
**Authority**: [Spec 019 public contract](../../019-tour-content-data/contracts/tour-detail-api.md), actual GetTourDetailAction/CreateBookingAction, frontend API types and loaders. [State model](../data-model.md).

## Public detail read

`GET /api/public/tours/{encodedSlug}?locale=en|es|it` returns the existing `{ data: TourDetail }` envelope. Continue `getTourDetail` with explicit no-store; reuse API_INTERNAL_URL on the server and the configured public/proxy API in the browser. Do not expose server configuration or private partner/payment fields to client props.

| Supplied data | Consumer requirement |
|---|---|
| title/description, highlights/inclusions/exclusions, practical information | Use selected current text; trim only to determine presence, never rewrite facts. |
| content_locale/itinerary_locale, translation_warning/status | Independently disclose actual fallback language for nonempty content; no frontend translation generation or substituted intentional empty itinerary. |
| duration, group_size, difficulty_level, guide_languages/languages | Supplied facts; stable spoken-language code names localized for page. Null difficulty omitted. |
| itinerary days/stops | Preserve order, actual text/language and intentionally empty array; no coordinates/transport/map inference. |
| images `{url,is_cover,alt,alt_locale?}` | Genuine supplied gallery, cover first/remaining order; language only on image/description, not viewer controls. No duplicate padding. |
| pricing.base_price `{amount,currency,formatted}`, tiered_pricing=null | Positive safe integer cents; current backend EUR/divisor100; localized estimate uses existing formatter. No currency support/exponent reinterpretation. |
| availability `{available_dates,next_available_date,is_unavailable}` | Date projection is not remaining capacity. Require valid offered date > UTC today and not-unavailable flag; no fabricated fallback date. |
| rating/reviews, operator, related_tours | Genuine public counts/averages; zero/null produces no false stars or verification; related list not padded. |
| seo | Keep canonical/hreflang and visible selected facts/languages; no new rich-result entitlement. |

Unknown/unpublished backend 404 remains not-found; previously published archive 410 remains GoneError and localized withdrawn view, no booking and noindex. Published but unbookable is still a visible 200 detail with disabled booking. Existing 422/429/auth failures retain meaning; render localized recovery without leaking upstream private messages. A rendered archive state does not claim a new Next HTTP-410 mechanism.

## Booking deep link and authorization

`/{locale}/booking?tour={encodedSlug}&participants={integer}&date={encodedYYYY-MM-DD}` is unchanged. Both surfaces use one builder after shared selection validation. Do not send estimated total, currency overrides, arbitrary identifiers or new fee/tier parameters.

When not authenticated after restoration completes, continue through `/{locale}/auth/login?returnUrl={encodedFullBookingPath}`. Use existing safeAuthReturnUrl with complete path/query, and preserve it through registration as existing auth flow supports. Do not use pathname-only AuthGuard for this handoff. Session expiry/401 in checkout returns through the same validated path with localized notice and without auto-submitting a booking on return. Server refetch/revalidation still applies.

Existing `POST /api/public/bookings` retains `tour_slug`, `tour_date`, `participant_count`, `locale`, optional page_load_price cents, bearer authentication and stable Idempotency-Key. No modification to payment creation, provider confirmation, retry identity, price-drift acceptance or confirmation query `ref` is part of this detail redesign. Verify those existing behaviors survive bounded auth recovery.

## Selection guards

- All offered real future dates must remain reachable, including beyond index30; backend currently projects at most60 dates within90 days. Preserve that server bound rather than invent new paging/availability APIs.
- Valid group bounds are positive safe integers with min≤max; count lies inside, integer only. Estimate multiplication must remain a safe integer. Invalid/missing/unsupported pricing disables both actions instead of trusting formatter fallbacks.
- Current backend currency is EUR; do not expand currencies by assuming ISO display decimals change stored amount units. Any future backend currency expansion requires renewed contract review.
- Use original date-only strings, with frozen/injected UTC clocks in tests. Crossing UTC midnight invalidates a now-today selection with a localized reason; do not silently book an alternative.
- A missing/invalid next date leaves no default. Explicit selection of another offered future date is allowed only when remaining guards pass.
- 409 capacity conflict and 422 schedule/count/date rejection are authoritative; detail date choices are not inventory holds. Checkout still handles rate limits, changed prices and recoverable payment failure.

## Contract verification

Combine real API assertions, pure-helper unit cases, built SSR fixtures, and integrated browser handoff. Required cases: unavailable/404/410/422/429/denied; independent and intentionally empty locale selection; alt language/media order; zero reviews/operator privacy; all future offered dates including index31; today/past/malformed dates and UTC rollover; integer counts/overflow; non-default selection across card/bar/resize; logged-out login/register and expired-session return preserving full query; stale/full inventory and price drift without duplicate operations.
