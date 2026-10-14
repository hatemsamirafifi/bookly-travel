# Feature Specification: Tour Detail Experience

**Feature Branch**: `codex/020-tour-detail-experience`  
**Created**: 2026-10-14 (Africa/Cairo)  
**Spec ID / Master-plan phase**: Spec 020 / Phase 3 — Tripadvisor-Style Tour Detail Experience  
**Governing Constitution**: [Bookly Constitution v2.1.0](../../.specify/memory/constitution.md)  
**Baseline / Dependencies**: Created from `codex/header-navigation-polish` at `4afdbc6e0bec2d29341ea229b7b7f1b2226ecf27`. After fetching origin on 2026-10-14, `origin/main` resolves to `9e241b9377d42f904081cb7b2bca1871e3b4388d`, the merge of Spec 019 PR #29, and is an ancestor of this baseline. Six subsequent header/footer/static-asset commits are retained as an explicit unmerged dependency; their presence does not establish main or release acceptance. No pending Spec 019 merge dependency remains.  
**Status**: Draft — specification quality validated; planning/design and execution task document created on 2026-10-14; implementation pending. Runtime verification, CI, merge and release acceptance are not claimed.  
**Input**: User description: "Spec 020", resolved through the approved [redesign master plan, Phase 3](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-3---tripadvisor-style-tour-detail-experience).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Understand the experience before booking (Priority: P1)

As a traveler, I can understand what a tour offers, how it runs and what its conditions are without searching through an unstructured page.

**Why this priority**: Accurate practical information is necessary to decide whether a tour fits my needs before committing money or time.

**Independent Test**: Open a published tour with complete content and one with sparse content; confirm each presents its supplied facts in the prescribed order without fabricated or empty sections.

**Acceptance Scenarios**:

1. **Given** complete content, **When** I open the tour, **Then** I see breadcrumbs; title, genuine rating/review link, location and wishlist; photos; section navigation; overview; facts; highlights; ordered itinerary; inclusions/exclusions; meeting point/cancellation conditions; operator; reviews; and related experiences, in that order.
2. **Given** absent optional itinerary, difficulty, operator or related experiences, **When** I inspect the page and navigation, **Then** empty sections and their entries are absent, without invented replacements.
3. **Given** a long overview or inclusion/exclusion lists, **When** I expand or collapse them, **Then** all supplied text is reachable, controls expose expanded state and collapsing preserves usable focus.
4. **Given** important information, **When** I inspect practical details, **Then** it remains accessible alongside meeting point/cancellation conditions; facts show supplied duration, party limits and live-guide languages.

### User Story 2 - Choose a date and party and continue to checkout (Priority: P1)

As a traveler, I can select an offered date and permitted traveler count, understand the estimated total, and carry those choices into the existing checkout on desktop or mobile.

**Why this priority**: The redesigned journey must preserve correct booking behavior and avoid misleading totals or availability.

**Independent Test**: From search, open a tour, choose a non-default offered date and valid count, then continue using desktop and mobile actions. Compare checkout selections and the estimate with supplied tour facts.

**Acceptance Scenarios**:

1. **Given** a bookable tour with multiple dates, **When** I select an offered date and permitted count, **Then** I see the true per-person price, currency and an estimated total equal to base price multiplied by count.
2. **Given** valid selections, **When** I continue from either action or resize before continuing, **Then** checkout receives the same tour, selected date, traveler count and locale; mobile does not silently reset selections.
3. **Given** minimum/maximum limits, **When** I attempt an out-of-range, fractional or otherwise invalid count, **Then** checkout cannot receive it and an accessible explanation identifies the constraint.
4. **Given** an unbookable tour, no valid dates, invalid pricing or invalid selections, **When** I inspect either booking surface, **Then** an unavailable/validation state is shown and no enabled checkout action uses invented data.
5. **Given** price or inventory changed since the page loaded, **When** I continue, **Then** existing checkout revalidation/recovery remains authoritative; the estimate neither overrides the payable total nor promises reserved inventory.
6. **Given** no cancellation policy, **When** I inspect booking, **Then** no cancellation promise is shown; a present summary remains faithful to supplied conditions.
7. **Given** a signed-out traveler or a session that expires at checkout, **When** I continue through existing login or registration and return, **Then** the full safe booking destination retains tour/date/count/locale, fresh validation still applies and no booking is automatically submitted.

### User Story 3 - Inspect genuine tour photographs (Priority: P2)

As a traveler, I can browse and enlarge the actual tour photographs and return to the detail page without losing context.

**Why this priority**: Photos support an informed decision, and accessible browsing keeps the page usable.

**Independent Test**: Exercise zero/one/sparse/large galleries with pointer, touch and keyboard interactions.

**Acceptance Scenarios**:

1. **Given** at least five photos, **When** I view desktop, **Then** one large cover and up to four supporting tiles appear with an all-photos control exposing the true total count.
2. **Given** several photos, **When** I open the enlarged viewer, navigate by controls or arrow keys, and close by Escape or its close control, **Then** every supplied photo is reachable, descriptions and position are accessible, focus stays inside while open and returns to the trigger on close.
3. **Given** mobile, **When** I swipe or use visible equivalent controls, **Then** I can browse a full-width gallery with a current/total counter, no thumbnail strip and no image-loading layout jumps.
4. **Given** no photos or a broken image, **When** I browse, **Then** an honest localized missing-image state appears; surviving photos stay reachable and stock images are not presented as tour media.

### User Story 4 - Move between relevant sections (Priority: P2)

As a traveler, I can jump to practical information or reviews and know which section I am reading on a long page.

**Why this priority**: Travelers need direct access to the facts relevant to their decision.

**Independent Test**: Navigate full and sparse pages by pointer/keyboard, scroll manually and repeat on a narrow screen.

**Acceptance Scenarios**:

1. **Given** a long page, **When** I activate a section entry, **Then** its heading is visible below fixed surfaces and the active entry identifies the section.
2. **Given** manual scrolling, **When** a different section becomes current, **Then** the active entry follows reading position without unexpectedly moving keyboard focus.
3. **Given** entries exceed mobile width, **When** I navigate, **Then** entries remain horizontally reachable, the active one can be seen and the whole page has no unintended horizontal overflow.
4. **Given** absent optional content, **When** I inspect navigation, **Then** no entry targets a missing section.

### User Story 5 - Evaluate trust, save and compare experiences (Priority: P2)

As a traveler, I can inspect genuine reviews and public operator facts, save the tour through existing wishlist behavior or open a related experience.

**Why this priority**: Trust and comparison matter only when claims and operation outcomes are accurate.

**Independent Test**: Use tours with/without reviews, public operators and related results; exercise wishlist signed in and signed out.

**Acceptance Scenarios**:

1. **Given** eligible reviews, **When** I follow the header review link, **Then** I reach the full review section with existing browsing behavior and supplied averages/counts.
2. **Given** zero reviews, **When** I inspect the page, **Then** no fabricated average or scored stars appear and no navigation entry targets an absent section; a localized no-reviews message may appear without an empty review section.
3. **Given** a supplied operator, **When** I inspect it, **Then** only public facts appear, verification requires explicit support, and absent optional logos/descriptions/averages do not generate claims.
4. **Given** eligible related tours, **When** I select one, **Then** its existing detail route opens in the current locale; an empty list is not padded with unrelated/unavailable records.
5. **Given** a signed-in or signed-out traveler, **When** I use wishlist, **Then** existing authentication, saved-state and recovery behavior remains intact; failed operations do not display saved success or initiate a booking.

### User Story 6 - Use the page in my language and interaction mode (Priority: P1)

As a traveler using EN/ES/IT, including with a keyboard or assistive technology, I can identify the content language, operate the page and recover from loading or errors.

**Why this priority**: The critical journey must work across supported languages, devices and access needs.

**Independent Test**: Repeat reading, gallery, navigation and booking preparation in all three locales and four acceptance widths, including independent translation fallback and error cases.

**Acceptance Scenarios**:

1. **Given** current translated content, **When** I open its locale, **Then** controls/notices use that locale and tour text uses the supplied current translation; guide-language names describe spoken languages independently.
2. **Given** translated general content and English itinerary, **When** I read, **Then** an itinerary-specific notice identifies its English text without labelling the entire page English; other independent fallback combinations disclose actual languages correctly.
3. **Given** an intentionally empty current translated itinerary or absent source itinerary, **When** I read, **Then** no itinerary is invented, substituted for intentional emptiness or accompanied by a misleading fallback notice.
4. **Given** keyboard/assistive technology or reduced-motion preferences, **When** I operate the page, **Then** actions remain named, operable and visibly focused; selections, expanded states and errors are understandable without color, hover or motion alone.
5. **Given** loading, recoverable failure, denied access, an unknown/unpublished tour or an archived tour, **When** I open or retry, **Then** the appropriate localized state appears, protected content stays private and existing unavailable/not-found/gone behavior remains intact.
6. **Given** the visible selected tour content, **When** I inspect its public search-preview information and structured descriptions, **Then** language, facts, photos, reviews and offers agree with the visible page.

### Edge Cases

- Zero/one/four/many photographs, broken images, missing captions and descriptions in a language different from controls; no duplicated tiles to fill the gallery.
- Empty optional arrays, whitespace-only optional text, null difficulty/operator, absent verification flag, no related results and zero reviews; no fabricated facts or empty navigation targets.
- Multi-day itineraries and long descriptions; no invented stop times, coordinates, transport, addresses or maps.
- Long translated titles/controls at 390px; sticky surfaces and safe areas must not obscure content, focus or critical actions.
- One offered date, date-only values near time-zone boundaries, minimum equal to maximum count and stale inventory; dates must not shift a day.
- Missing/inconsistent next date: do not silently book it; an offered date may be selected explicitly only when otherwise bookable.
- Missing/non-positive price, unsupported currency or invalid group bounds: disable affected booking with an explanation rather than a misleading estimate.
- Independent general/itinerary fallback, intentionally empty translated itinerary and reload after translation completes; no polling or completion-time guarantee.
- Wishlist authorization failure, review-loading failure or broken related media must not erase readable tour content or display false success.
- Closing gallery, collapsing focused content, reading below sticky surfaces and changing viewport after selections.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Preserve existing tour URLs, locale prefixes, canonical/alternate-language links and published/unpublished/archived visibility behavior. (US6)
- **FR-002**: Present the ordered hierarchy in US1, preserving important information near practical conditions and access to booking while reading. (US1, US2)
- **FR-003**: Expose breadcrumbs, supplied title/location, genuine review access when present and existing wishlist action in the header. (US1, US5)
- **FR-004**: Omit absent optional sections and their entries; zero reviews MUST NOT generate ratings or links to absent content. Required-content failure MUST have an explicit page state. (US1, US5, US6)
- **FR-005**: Provide accessible expansion/collapse for shortened long overviews, keeping complete content reachable; short descriptions MUST NOT require expansion. (US1)
- **FR-006**: Show supplied duration, party bounds, localized live-guide language names and optional difficulty without inference or conflation with content language. (US1, US6)
- **FR-007**: Preserve supplied highlights and itinerary day/stop order, text and actual language without synthesized details. (US1, US6)
- **FR-008**: Use accessible inclusion/exclusion disclosures; keep supplied meeting point, cancellation and important information fully readable without invented maps/coordinates or rights. (US1, US2)
- **FR-009**: Desktop gallery MUST show a large cover plus up to four supporting tiles in supplied order and access to the complete gallery with its real count. Sparse galleries MUST adapt without duplicate or fake photos. (US3)
- **FR-010**: Enlarged gallery MUST support previous/next controls, keyboard arrows, Escape, accessible close, focus containment/restoration, supplied descriptions and current/total position. (US3, US6)
- **FR-011**: Mobile gallery MUST provide full-width swipe browsing, equivalent visible controls, a current/total counter, stable image space and no thumbnail strip. (US3)
- **FR-012**: Missing/broken images MUST use truthful localized states without obstructing usable photos, content or booking. Descriptions MUST retain their actual language. (US3, US6)
- **FR-013**: Section navigation MUST stay available during scrolling, identify the current section and reveal activated headings below sticky surfaces without taking focus during manual scrolling. (US4)
- **FR-014**: Entries MUST support keyboard operation and mobile horizontal reachability without whole-page overflow; active state MUST not depend on color alone. (US4, US6)
- **FR-015**: Provide a sticky desktop booking card and mobile safe-area-aware bottom bar with reachable selectors; neither may obscure content or focused controls. (US2, US6)
- **FR-016**: Show the supplied base per-person price and currency without introducing unsupported adult/child categories, pricing tiers, fees or discounts. (US2)
- **FR-017**: Select only offered dates satisfying existing booking validation (strictly after UTC today); initial next-date selection is permitted only if it is valid, belongs to that list and the tour is bookable. Date-only values MUST preserve their calendar day. (US2)
- **FR-018**: Count MUST be an integer within supplied bounds, initially the valid minimum; invalid counts/bounds MUST prevent continuation with an accessible localized reason. (US2)
- **FR-019**: Show an explicitly labelled estimate equal to base amount × count, formatted for the currency without fractional-money arithmetic errors. Checkout's confirmed amount remains authoritative. (US2)
- **FR-020**: Both booking actions MUST share current selections and retain them across viewport changes on the page; existing checkout MUST receive the same tour/date/count/locale. (US2)
- **FR-021**: Unavailable tours, absent valid dates, invalid prices and invalid selections MUST prevent enabled checkout actions everywhere. Selecting a date MUST NOT claim inventory reservation. (US2)
- **FR-022**: Booking-side cancellation copy MUST require a supplied policy and preserve its meaning. Preserve checkout validation, authentication, payments, confirmation and recovery rules; login/registration or expired-session recovery MUST retain the selected tour/date/count/locale through a safe return path without automatic booking submission. (US2)
- **FR-023**: Show only supplied public operator facts, with verification only when explicitly supported; missing averages/logos/descriptions MUST NOT produce claims. Private contact/operational/financial data MUST remain undisclosed. (US5)
- **FR-024**: Preserve full review browsing and truthful supplied counts/averages without changing review eligibility, submission, moderation or partner-response authorization. (US5)
- **FR-025**: Use supplied eligible related tours without padding and link to their existing detail routes in the current locale. (US5)
- **FR-026**: Preserve wishlist authentication, ownership, saved-state and failure recovery; announce actual outcomes. (US5)
- **FR-027**: New interface copy MUST ship in EN/ES/IT; general-content and nonempty-itinerary fallback notices MUST independently disclose actual languages in the page locale. Displayed text MUST identify its actual language. (US6)
- **FR-028**: Preserve current-source selection and intentional empty translated itineraries; MUST NOT display superseded translations or add polling/completion promises. (US6)
- **FR-029**: Target WCAG 2.1 AA: keyboard operation, accessible names/relationships, visible focus, contrast, understandable selection/error feedback and reduced-motion behavior. (US3, US4, US6)
- **FR-030**: Support 390px, 768px, 1024px and 1440px in all three locales without clipped critical information, unusable controls or overlap. (US1–US6)
- **FR-031**: Applicable loading, empty, error, success, disabled and permission-denied states MUST be localized and distinguishable. Recoverable errors MUST offer retry/recovery without protected-data disclosure or false success. (US2, US3, US5, US6)
- **FR-032**: Public search-preview information and structured descriptions MUST agree with visible selected current language, facts, photos, genuine reviews, prices and availability without invented offers/ratings. (US6)
- **FR-033**: Retain Bookly's established shared identity and interaction conventions. Tripadvisor inspiration is limited to hierarchy, interaction density and gallery/navigation patterns; copying proprietary text/imagery, trademarks or advertising is excluded. (US1–US6)
- **FR-034**: Consume established tour-content, commerce, review and wishlist behavior. New authoring, translation generation, pricing models, contracts and unrelated page redesigns require separate scope review. (US1–US6)

### Key Entities *(include if feature involves data)*

- **Published Tour**: Visible identity, location, overview, facts, practical content, actual content language and bookability; publication lifecycle is unchanged.
- **Tour Photograph**: Ordered genuine photo with cover designation, accessible description and its language.
- **Itinerary Day / Stop**: Ordered supplied experience content with independent selected language; missing and intentionally empty content remain distinguishable.
- **Booking Selection / Estimate**: Transient offered date, integer count and estimated amount/currency; neither a reservation nor a confirmed payable total.
- **Public Operator Summary**: Public identity, explicitly supported verification and review facts; no private partner data.
- **Review Summary / Review**: Existing eligible public review facts and browsable feedback with unchanged integrity rules.
- **Related Experience**: Supplied eligible tour linking to its own detail page.
- **Wishlist State**: Existing traveler-specific saved status and authenticated action outcome.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Complete/sparse acceptance cases show 100% of applicable supplied sections in prescribed order, with zero empty navigation targets, fabricated facts or lost important-information items. (FR-002–008)
- **SC-002**: Every valid search → tour → date/party → checkout acceptance case carries tour/date/integer count/locale unchanged through both actions; every invalid/unavailable case prevents continuation. (FR-015–022)
- **SC-003**: Every estimate, including fractional major-unit prices and minimum/maximum counts, exactly equals supplied base amount × count in the supplied currency with zero invented charges. (FR-016–019)
- **SC-004**: All usable photos are reachable in zero/one/sparse/large-gallery cases where present; keyboard close restores focus in 100% of viewer cases and image loading causes no gallery-induced layout shift. (FR-009–012)
- **SC-005**: All twelve locale/width combinations pass reading, section navigation, gallery and booking-preparation acceptance without clipped critical controls, unintended page overflow or sticky-surface obstruction. (FR-013–015, FR-027–030)
- **SC-006**: Every defined translation/intentional-empty, trust, wishlist and page-state case shows correct facts, language notices and outcomes, with zero false ratings/verification/saved successes or exposed private fields. (FR-001, FR-023–028, FR-031–032)
- **SC-007**: Critical journeys have no unresolved WCAG 2.1 AA violations after automated checks and manual keyboard/focus/reduced-motion acceptance; every critical action is operable without a pointer. (FR-029)
- **SC-008**: Production-built detail pages achieve Lighthouse Performance ≥90 in EN/ES/IT under documented reproducible conditions; failed/unreachable measurement is not a pass. This is a required target, not a current performance claim. (FR-030, FR-033)
- **SC-009**: Applicable regression checks pass for existing routes/visibility, review/wishlist and checkout revalidation; public metadata matches visible supplied content and offers. (FR-001, FR-022–026, FR-032–034)

## Assumptions

- Approved Phase 3 is the authoritative interpretation of "Spec 020". [Spec 018](../018-bookly-ui-redesign/spec.md) remains the program/design baseline; [Spec 019](../019-tour-content-data/spec.md) owns predecessor data requirements and is preserved.
- Spec 019 merge history provides the predecessor baseline. Its [public-detail contract](../019-tour-content-data/contracts/tour-detail-api.md) describes available data concerns; historical planning-status wording does not override recorded acceptance/Git ancestry. Deployment readiness must be checked separately during planning/release.
- Six retained header/footer/static-asset commits establish the current visual baseline. Planning must resolve their independent merge status or carry an explicit stacked dependency before shipping Spec 020.
- Existing offered dates and tour-wide party bounds are the available selection authority. Date-specific capacity, child prices, time slots, fees, promotions and guaranteed inventory holds are not assumed.
- Selections persist within the current detail-page session across viewport changes. Persistence across reloads/locale navigation is excluded; checkout's existing authentication/return behavior remains authoritative.
- Exact long-content thresholds and responsive breakpoints belong in planning while preserving these outcomes. No unsupported conversion increase or usability percentage is claimed.
- EN/ES/IT are supported; Arabic/RTL, new translation-generation behavior, maps/geocoding, new review workflows and schema redesign are excluded.
- Specs 021–027 retain their separate homepage, supporting pages, auth/traveler/checkout, partner, admin, program-wide quality and deployment scopes. Detail regression checks do not complete those phases.
- Implementation waits for a complete plan and applicable design/contract/execution artifacts; specification quality does not imply runtime, CI, merge or release acceptance.
- Planning source inspection on 2026-10-14 confirmed offered dates can include today although booking rejects it, and existing logged-out/401 handoff lacks full-selection recovery. FR-017/022 explicitly reflect the actual booking rule and required bounded auth continuation; this is not a new pricing/payment workflow or a claim that the existing UI already passes.
