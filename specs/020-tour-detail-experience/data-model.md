# Data Model: Spec 020

**Status**: Planned presentation/transient state; no database migration, persistent entity or public response change. Authoritative predecessor: [Spec 019 model](../019-tour-content-data/data-model.md) and [public contract](../019-tour-content-data/contracts/tour-detail-api.md).

## Authoritative read model

Use existing `TourDetail`/`TourImage`/itinerary/operator/card types from `frontend/src/lib/api/types.ts`. Identity, content, live-guide codes, media order/alt locale, pricing, date list, bounds, reviews, SEO and related records remain supplied data. Consumers do not generate replacements. Select current general/itinerary values at the existing backend boundary; frontend flags disclose their actual languages without rerunning translation selection.

## Presentation entities

| Entity | Fields / constraints | Relationships / ownership |
|---|---|---|
| DetailSection | stable id, localized heading, present boolean | Pure ordered projection of supplied tour; same list drives rendering and navigation. |
| ContentDisclosure | expanded boolean; full text; 600-code-point preview only when longer | Local to overview; complete content/language retained. Collapse restores focus before hiding focused content. |
| GalleryState | active original image index; viewerOpen; failed-image-index set; trigger ref | Local gallery. Use supplied photo order, cover first; bounded index, no duplicate images. Failed item keeps stable slot/count and honest placeholder. |
| ReadingState | active section id; measured sticky obstruction/nav height | Local navigation; references only present section ids; passive updates never take focus. |
| BookingSelection | tour identity; selectedDate string or null; integer participants | One page-local client owner shared by card/bar. CSS viewport visibility does not remount/reset it. |
| BookingEstimate | safe integer amountCents; currency; formatted display or unavailable reason | Pure derivation of supplied base amount × valid count, never persisted/submitted as final payment amount. |
| SurfaceStatus | ready/loading/empty/error/disabled/denied/withdrawn as applicable | Page/media/review/wishlist/booking domains remain distinguishable; errors do not erase unrelated readable content. |

## Validation and transitions

- `selectedDate` must exactly match a real offered `YYYY-MM-DD` date strictly after current UTC today, matching existing server validation. Initialize from a listed future next date only; otherwise null until explicit selection. Server supplies initial UTC-day snapshot for hydration; recheck on interaction and UTC-day rollover. Display without shifting the day and keep the original string for checkout. No client inference of capacity.
- `participants` initializes to supplied minimum only when min/max are positive safe integers and min ≤ max; values must stay inside those bounds. Equal min/max is valid. Fractional/nonfinite inputs or impossible bounds are invalid.
- Base amount must be a positive safe integer, current currency compatible with the predecessor cents contract (currently EUR), and the multiplied result a safe integer. Invalid pricing disables continuation; formatCurrency is not validation and its zero fallback must not conceal invalid data.
- Overall continuation requires the server not-unavailable flag, nonempty offered valid dates, valid pricing/bounds and valid selections. Missing next date alone does not fabricate a default; an otherwise bookable tour can accept an explicit offered date.
- Date/count change recomputes one estimate and one checkout href for both actions. Resize changes presentation only. A new tour identity resets state; fresh supplied props invalidate selections that no longer belong to dates/bounds, show a localized reason and require reselection rather than silent substitution.
- Viewer open records actual trigger and active photo; next/previous wraps among original slots; failed slots are announced honestly. Closing restores connected trigger focus. Empty gallery cannot open a viewer.
- Optional text uses trimmed nonempty checks; arrays require actual usable entries. Missing optional fields suppress both section and entry. Review count zero suppresses scored rating/review section and may expose a localized no-review message.
- Content fallback derives actual content locale; itinerary fallback additionally requires nonempty itinerary. Intentional empty translated itinerary remains empty; image alt language uses alt_locale or actual selected title language.
- Backend visibility/bookability, booking/payment/wishlist/review state machines remain unchanged. Detail selections are never reservations or confirmed commerce records.
- Authentication continuation contains the full same-locale booking path/query as a safe returnUrl, never an external redirect. Logged-out/expired-session recovery preserves selections without automatic submission; checkout refetches/revalidates them on return.
