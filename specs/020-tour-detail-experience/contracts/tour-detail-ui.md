# UI Acceptance Contract: Spec 020

**Status**: Planned Bookly layout/interaction decisions for task generation. These are proposed acceptance rules, not screenshots or measured outcomes. [Specification](../spec.md), [model](../data-model.md).

## Layout and shared identity

- Use established Container/spacing/type/color/motion/z-index tokens and Plus Jakarta Sans; retain the current header/footer visual baseline. Gold is the main booking action, navy primary text/identity and green truthful trust/availability only.
- At <1024px use a single readable column and mobile booking bar; at ≥1024px use article/booking columns approximately2:1 after the full-width header/gallery/nav, with sidebar stickiness bounded by the content column through practical/review sections.
- Order: breadcrumbs; title/rating/review link/location/wishlist; gallery; sticky section navigation; overview; facts; highlights; ordered itinerary; inclusion/exclusion; meeting/cancellation/important information; operator; reviews; related tours. Omit absent optional sections and corresponding entries from one projection.
- Desktop gallery ≥1024px: one large cover plus up to four smaller tiles; five-image arrangement uses a large left tile and2×2 right grid. Sparse media adapts with no duplicate/fake tiles. Below1024px: full-width scroll-snap photos with counter and previous/next, no thumbnails.
- Use fixed responsive aspect ratios and accurate sizes; one initially prioritized hero, lazy supporting imagery. Preserve real total count with honest failed-image slots. Viewer is full-screen within shared Dialog behavior; no root lang that relabels localized controls.
- Overview preview appears beyond600 Unicode code points, with labelled expansion/collapse and accessible complete text. Do not clip short text or cut essential practical conditions into the preview.

## Interaction and focus

| Surface | Required behavior |
|---|---|
| Gallery viewer | Accessible dialog name/close, body lock/inert background, bounded arrow navigation and announced position; Escape closes; restore actual trigger. Arrow changes do not refocus close. |
| Mobile gallery | Swipe/scroll plus visible keyboard-operable controls; active counter; no motion dependency. |
| Section navigation | Native anchors, aria-current=location plus noncolor visual cue; IntersectionObserver follows visible headings. Explicit activation focuses revealed heading; passive scrolling never moves focus. Horizontal active-entry reveal must not move the page vertically. |
| Sticky layout | Current header is relative, not fixed. Measure actual nav/sticky obstruction, update on resize and use coordinated scroll-margin/observer offsets. Avoid guessed permanent header heights. |
| Overview/disclosures | Expanded state and related panel IDs; collapse moves focus before hiding focused descendants. Reuse Accordion for inclusion/exclusion. |
| Booking card | Labelled native offered-date selector and integer count controls; visible limits and estimate. All future offered dates reachable; no first30 cap. |
| Mobile bar | Price/action plus current selection summary; opens shared Dialog selectors when editing or selections incomplete. Dialog/card consume one owner. Full query handoff only when valid; no inventory reservation claim. |
| Responsive duplicate controls | Stable selection owner; unique IDs; inactive CSS-hidden controls are unfocusable. Closing mobile selectors restores the triggering bar action. Reserve page space based on actual bar height plus safe area. |
| Wishlist | Preserve existing guarded/optimistic behavior; failures roll back and announce failure. Replace its login prompt focus mechanics with shared Dialog if needed, retaining actual auth outcome. |
| Reviews/related/operator | Reuse existing flows; zero reviews has no scored stars/empty link; verified only when true; localized route links. |

## Locale and state matrix

Every public control/new notice uses EN/ES/IT catalogs. Content/itinerary/image descriptions identify their actual selected language independently. Guide-language names express spoken-language metadata, never translation readiness.

| State | Presentation / recovery |
|---|---|
| Loading | Reserved gallery/content geometry and accessible loading status; booking disabled until public facts loaded. |
| Empty optional content | Section/entry absent; zero reviews may show a localized neutral message. |
| Empty/broken media | Localized honest placeholder within reserved geometry; other photos remain reachable. |
| Unbookable/invalid selection | Accessible reason, selectors where meaningful, no enabled checkout href; both surfaces agree. |
| Validation/date rollover | Inline localized explanation and invalid state announcement; require reselection, no silent alternative. |
| Recoverable detail/review failure | Localized retry/recovery; preserve unrelated readable content. |
| Denied protected action | Existing auth flow/recovery, no private fields/false success. |
| Archived/not found | Distinct localized withdrawn/not-found behavior, no booking, noindex metadata. |
| Successful wishlist/selection | Announce actual saved outcome or selected date/count without excessive duplicate live regions. |

Review all12 EN/ES/IT ×390/768/1024/1440 combinations, plus keyboard, reduced motion, modal-open states and UTC clock boundary. Automated axe is supplemented by manual focus/overlap/contrast verification; screenshot similarity alone is not acceptance.
