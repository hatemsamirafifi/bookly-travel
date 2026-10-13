# Feature Specification: Bookly Web Experience Redesign

**Feature Branch**: `018-bookly-ui-redesign`

**Created**: 2026-09-25

**Status**: Draft

**Input**: Phase 0 of the Bookly UI redesign in `docs/bookly-ui-redesign-spec-kit-master-plan.md`: use Tripadvisor-inspired information hierarchy for discovery and tour details while retaining Bookly's identity, tours-only model, and existing business rules.

**Governance**: Original specification baseline: Constitution v2.0.0. Active continuation and release acceptance use [Constitution v2.1.0](../../.specify/memory/constitution.md).

**Spec ID / phase ownership**: `018`; master-plan bootstrap and Phase 1 foundations. The original full-program scope below remains the design baseline. Phase 2 owns its active requirements and revised contracts in [Spec 019](../019-tour-content-data/spec.md); later phases follow the [master-plan mapping](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-to-spec-numbering).

## Clarifications

### Session 2026-09-25

- Q: If a tour translation or its itinerary is missing in the selected language, should its content fall back to English, and are live-guide languages related to that decision? → A: Show the missing content in English with a visible fallback notice; guide/spoken languages are separate tour metadata and never determine content translation availability.
- Q: Must partners provide Spanish and Italian content before publishing a new tour? → A: No. Partners provide required English source content; the platform generates Spanish and Italian with AI. Missing or outdated derived translations never block publishing, temporarily fall back to visibly identified English, and regenerate when English changes. Guide languages remain independent.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Discover a relevant tour (Priority: P1)

As a visitor, I can start with prominent destination/activity search, browse tour categories and curated collections, then narrow results with filters and sorting on desktop or mobile. Tour cards help me compare real offers without opening every result.

**Why this priority**: Discovery starts the principal booking journey.

**Independent Test**: Search from the homepage, filter and sort results, open a tour, and use browser back; the query and result context remain intact.

**Acceptance Scenarios**:

1. **Given** published matching tours, **When** a visitor searches and applies filters, **Then** the result count, active filters, sorting, and cards reflect the criteria and actual tour data.
2. **Given** a narrow viewport, **When** a visitor applies mobile filters, **Then** the filter panel is operable and the selected query survives navigation.
3. **Given** a data-dependent homepage section with no eligible content, **When** the homepage loads, **Then** only that section is omitted or given an honest empty state; search and populated sections remain usable.

---

### User Story 2 - Evaluate a tour and begin booking (Priority: P1)

As a visitor, I can scan genuine review evidence, photos, essential facts, highlights, itinerary, inclusions, meeting and cancellation details, operator information, and related experiences in a predictable order. I can select a real available date and valid traveler count before continuing to checkout.

**Why this priority**: This page is the main purchase decision point; missing or misleading details undermine trust.

**Independent Test**: Open a published tour, move through sections and photos by pointer and keyboard, select a valid date/count, then reach checkout with the same selection.

**Acceptance Scenarios**:

1. **Given** a published tour with complete content, **When** its detail page opens, **Then** sections follow the agreed order, section navigation reaches them, and desktop/mobile booking actions show the same truthful price and availability.
2. **Given** a tour without optional itinerary, difficulty, operator summary, or reviews, **When** it opens, **Then** empty sections and their navigation entries are absent while core facts and a valid booking state remain.
3. **Given** an unavailable date or out-of-range traveler count, **When** the visitor tries to proceed, **Then** checkout is blocked with a clear reason.
4. **Given** multiple photos, **When** the visitor opens and closes the enlarged gallery, **Then** order and available captions are preserved, keyboard controls work, and focus returns to the trigger.

---

### User Story 3 - Complete a trustworthy booking (Priority: P1)

As a traveler, I can review my tour, date, traveler count, and payable total in a clear checkout and understand payment success, price changes, or recoverable failure.

**Why this priority**: Visual changes cannot weaken existing booking and payment safeguards.

**Independent Test**: Complete or abandon checkout at desktop and mobile widths; repeat after refresh, back navigation, changed price, and payment failure.

**Acceptance Scenarios**:

1. **Given** a valid tour selection, **When** checkout opens, **Then** its summary matches the authoritative booking terms.
2. **Given** a price change, failed payment, expired state, or retry, **When** checkout responds, **Then** the traveler sees the correct next action and no duplicate confirmed booking or charge is implied.
3. **Given** a successful booking, **When** confirmation appears, **Then** it reflects the confirmed payment/booking outcome rather than an optimistic display state.

---

### User Story 4 - Manage identity and travel activity (Priority: P2)

As a traveler, I can sign in, register, recover access, maintain my profile, view saved tours and bookings, and submit eligible reviews in one coherent account experience.

**Why this priority**: Existing account journeys must keep their guards and redirects while adopting the new design.

**Independent Test**: Exercise each authentication and traveler page with valid, invalid, empty, and unauthorized states; permissions and destinations remain correct.

**Acceptance Scenarios**:

1. **Given** invalid or missing input, **When** a traveler submits a form, **Then** relevant field/server feedback appears without unnecessary loss of entered data.
2. **Given** a signed-in traveler, **When** they open the account, **Then** profile, wishlist, booking history/detail, and eligible review actions are available within consistent responsive navigation.
3. **Given** an unauthenticated or ineligible visitor, **When** they attempt a protected action, **Then** the existing access rule and appropriate recovery path apply.

---

### User Story 5 - Author accurate tour content (Priority: P2)

As an authorized partner, I can author the English source for ordered itinerary days and stops and other traveler-facing tour content, select and order gallery images, choose a cover, and preview what travelers will see. The platform generates Spanish and Italian versions; I do not have to author them. Existing tours remain editable without new optional content.

**Why this priority**: The new tour page needs structured, partner-owned content without disrupting published listings.

**Independent Test**: Edit a legacy tour and a new draft, save/recover changes, reorder itinerary and images, preview, and submit for review.

**Acceptance Scenarios**:

1. **Given** a partner-owned draft, **When** its owner adds, removes, or reorders English-source itinerary and images, **Then** saved and previewed content preserves that order and cover selection.
2. **Given** a tour page in English, Spanish, or Italian, **When** it opens, **Then** ready translations of its traveler-facing content and itinerary appear in the selected language; missing or outdated derived content falls back to English with a visible notice, independently of available live-guide languages.
3. **Given** a legacy tour without new optional fields, **When** its owner edits or resubmits it, **Then** those absent fields alone do not block saving or publishing.
4. **Given** a user who does not own a draft, **When** they attempt to access or change it, **Then** the existing ownership restriction applies.
5. **Given** complete required English source content but no Spanish or Italian translation, **When** the tour is submitted or approved, **Then** missing derived translations do not block publication and generation is queued.
6. **Given** a partner updates English source content, **When** it is saved, **Then** the affected Spanish and Italian translations are marked outdated and queued for regeneration; the public page does not display outdated derived content as current.

---

### User Story 6 - Operate a partner business (Priority: P2)

As a partner, I can find my tours, availability, pricing, bookings, reviews, profile, onboarding, and analytics in a responsive dashboard. Financial figures use their true currency scale; unavailable metrics are labeled honestly.

**Why this priority**: Partner workflows must stay usable, and misleading metrics erode trust.

**Independent Test**: Navigate every partner page family at wide and narrow widths with populated and empty states, including analytics with revenue and unavailable conversion data.

**Acceptance Scenarios**:

1. **Given** a partner with records, **When** they navigate and use filters/actions, **Then** the same authorized operational tasks remain available at narrow widths.
2. **Given** revenue represented internally in a smaller currency unit, **When** it is displayed, **Then** the visible amount and currency are correct.
3. **Given** unavailable conversion or optional metrics, **When** analytics displays, **Then** they are labeled unavailable or omitted, not presented as measured zero or invented values.

---

### User Story 7 - Moderate submitted content (Priority: P2)

As an internal administrator, I can inspect new itinerary/gallery content and perform existing moderation and operational actions in a Bookly-consistent interface without changing permissions or approval policy.

**Why this priority**: Partner-authored content must remain reviewable before publication.

**Independent Test**: An authorized administrator reviews a submitted tour with new content; a non-admin cannot perform the same action.

**Acceptance Scenarios**:

1. **Given** a submitted tour with itinerary/gallery changes, **When** an administrator opens it, **Then** the traveler-facing content can be inspected and the existing approval/rejection actions remain available.
2. **Given** an unauthorized user, **When** they attempt an administrative action, **Then** access is denied.

---

### User Story 8 - Reach supporting information and recover (Priority: P3)

As a visitor, I can read blog and legal pages, verify a voucher, or recover from missing, rate-limited, offline, empty, and failed pages in a consistent Bookly experience.

**Why this priority**: These routes are within the whole-site redesign, though not the primary purchase path.

**Independent Test**: Visit each supporting route and representative failure state; verify its content, visibility rules, navigation, and recovery action.

**Acceptance Scenarios**:

1. **Given** a blog, legal, or voucher-verification page, **When** opened, **Then** established content and behavior remain intact within the new visual system.
2. **Given** a missing or failed page, **When** shown, **Then** its message states the condition truthfully and offers a relevant recovery route.
3. **Given** non-public preview content, **When** accessed through its permitted path, **Then** existing search-visibility restrictions remain in force.

### Edge Cases

- A tour has one image, no gallery media, or invalid legacy media: show a safe fallback without broken photo controls.
- Legacy itinerary content is partial or malformed: show only valid content; absence must not block booking.
- A section is absent: its section-navigation entry is absent too.
- Availability or price changes before checkout: show the latest authoritative terms and require acknowledgement before a changed charge.
- Reviews are absent or ineligible for public display: no rating or review count is fabricated.
- Search has no results, times out, or fails: preserve entered criteria and offer a recovery action.
- A partner draft is interrupted or concurrently changed: preserve existing save/conflict behavior; do not falsely claim a save.
- A metric or translation is missing: show unavailable/fallback status rather than a fabricated number or untranslated placeholder.
- A selected-locale itinerary is missing while English itinerary exists: show the English itinerary with a visible language notice; do not use the tour's guide languages to choose content.
- AI translation is pending, fails, or completes after the English source changes again: keep an explicit translation state, show the current English source with a visible notice, and never publish an outdated AI result as current.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The redesign MUST cover every existing web page family: public discovery/tour pages, authentication, traveler account, checkout, partner operations/authoring, internal administration, blog, legal, voucher verification, and error/recovery pages.
- **FR-002**: Existing route addresses, language prefixes, search visibility/canonical behavior, and public/protected boundaries MUST remain stable unless separately approved.
- **FR-003**: All redesigned pages MUST retain Bookly's navy/gold brand identity; the reference site's structure MAY inspire layout, but its trademarks, imagery, wording, advertisements, and distinctive assets MUST NOT be copied.
- **FR-004**: Navigation, cards, forms, galleries, filters, booking actions, status indicators, loading, empty, and error states MUST be usable at desktop, tablet, and mobile widths.
- **FR-005**: The homepage MUST prioritize search, category discovery, eligible featured tours/destinations, relevant editorial content, and partner invitation; data-dependent sections MUST not make unsupported claims.
- **FR-006**: Tour cards MUST show actual location/category, duration, starting price, and review evidence when available; badges and ratings MUST be data-backed.
- **FR-007**: Search/listing pages MUST preserve current search behavior while exposing result context, filters, sort order, active criteria, and browser-history restoration; mobile filters MUST preserve the query.
- **FR-008**: Tour details MUST present, in order when content exists: breadcrumbs; title, location and genuine rating; gallery; section navigation; overview; facts; highlights; itinerary; inclusions; meeting/cancellation details; public operator; reviews; related tours; and booking action.
- **FR-009**: Optional empty tour sections and their navigation entries MUST be omitted; core information and a valid unavailable/booking state MUST remain visible.
- **FR-010**: Tour media MUST preserve cover selection and ordering; enlarged photos MUST support keyboard control, available captions, and focus restoration.
- **FR-011**: Tour section navigation MUST reach only visible sections and remain operable on narrow screens without hiding destination content.
- **FR-012**: Booking actions MUST allow only real available dates and valid traveler counts; they MUST show the supported base price and corresponding total without fabricated fees or pricing tiers.
- **FR-013**: Selected tour/date/travelers MUST carry into checkout without changing existing booking, payment, price-change, cancellation, retry, and confirmation rules; retries MUST NOT create duplicate bookings or charges.
- **FR-014**: Authentication, profile, wishlist, booking history/detail, and review journeys MUST retain existing guards, redirects, validation, and eligibility rules.
- **FR-015**: Authorized partners MUST be able to create, reorder, edit, save, recover, and preview the English-source itinerary days/stops and ordered gallery media, including the cover; they MUST NOT be required to author Spanish or Italian content.
- **FR-016**: New structured tour fields MUST remain optional for legacy tours; their absence alone MUST NOT create a new publication gate.
- **FR-017**: English MUST be the required canonical source for partner-authored traveler-facing tour content. Spanish and Italian MUST be platform-generated AI translations of that source, including title, description, itinerary, inclusions/exclusions, important information, and other traveler-facing fields. Public pages MUST use a current derived translation when ready; missing or outdated derived content MUST temporarily fall back to the current English source with a visible notice. New interface copy MUST be available in all three languages.
- **FR-018**: Public operator summaries MUST contain only approved traveler-safe information; private partner contact, tax, payout, account, and identity details MUST never be exposed.
- **FR-019**: Related tours MUST exclude the current tour and unpublished/unbookable tours; ordering MUST favor destination relevance, then category relevance, then genuine review quality.
- **FR-020**: Partner dashboard operations MUST remain available across overview, analytics, tours, pricing, availability, bookings, reviews, profile, and onboarding views at narrow and wide widths.
- **FR-021**: Partner analytics MUST display currency amounts at the correct scale and MUST identify unavailable conversion or optional metrics as unavailable, never as measured zero.
- **FR-022**: Internal administrators MUST be able to inspect new tour content and continue existing moderation actions with unchanged authorization and approval rules.
- **FR-023**: Blog, legal, voucher, preview, and error/recovery pages MUST retain their established content, visibility, and security behavior while adopting the shared visual language.
- **FR-024**: Interactive controls MUST be keyboard operable with visible focus, correct dialog focus behavior, practical touch targets, reduced-motion support, and WCAG 2.1 AA conformance.
- **FR-025**: Existing tour publishing, partner ownership, booking, payment, review-integrity, and administrative authorization rules MUST remain authoritative.
- **FR-026**: Public search-result descriptions, alternate-language links, and structured tour facts MUST match the visible page content and its actual content language, including a disclosed English fallback; non-public previews MUST remain excluded from public discovery.
- **FR-027**: Success, disabled, permission-denied, and failed states MUST be intentional on each applicable page; errors MUST not disclose private data.
- **FR-028**: Public rating and operator aggregates MUST derive only from reviews and tours eligible for public display.
- **FR-029**: A tour's available live-guide/spoken languages MUST be stored and exposed as separate metadata identified by stable language codes; those codes MUST NOT be used to infer which content translations exist.
- **FR-030**: The tour detail MUST clearly display available live-guide languages, with their names translated for the selected interface language when translations are available.
- **FR-031**: Publication MUST require the existing mandatory English title and description, but MUST NOT require Spanish or Italian translations; optional source fields remain optional for legacy tours.
- **FR-032**: Changes to English source content MUST mark affected Spanish and Italian translations outdated and queue regeneration. Translation state MUST distinguish current, pending, outdated, and failed results without allowing a result from an older English revision to become current.
- **FR-033**: Existing public route and API fields MUST remain compatible where possible; any added translation-status information MUST distinguish content locale from live-guide language codes.

### Key Entities *(include if feature involves data)*

- **Tour**: Partner-owned travel experience with publication state, localized content, base price, availability, group-size limits, media, and reviews.
- **Itinerary Day / Stop**: Ordered, localized schedule content, optional for legacy tours and subject to a defined language fallback.
- **Tour Media**: Ordered traveler-visible images with a selected cover, optional captions/alternative text, and a safe absence fallback.
- **Public Operator Summary**: Approved traveler-safe partner identity and genuine aggregates; excludes private operational/financial details.
- **Related Tour**: Distinct, published, bookable tour chosen for relevance and review quality.
- **Tour Selection / Booking**: Selected date, traveler count, authoritative pricing, payment outcome, and confirmation state.
- **Review**: Eligible traveler feedback governed by existing publication and integrity rules.
- **Partner Metric**: Measured business value with known currency/unit and availability state; unavailable is not zero.
- **Guide Language**: A stable language identifier for a live guide or spoken tour service; independent of the tour page's English, Spanish, or Italian content translation.
- **Tour Content Translation**: English canonical source plus Spanish and Italian AI-derived traveler-facing fields, with a state tied to the English source revision; it is independent of guide-language metadata.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of existing web routes are mapped to a redesigned page family, with primary action and recovery state verified at 390, 768, 1024, and 1440 pixel widths.
- **SC-002**: In moderated usability tests, at least 90% of participants can find a relevant tour, identify key terms, and reach checkout without help or a layout-related blocker.
- **SC-003**: 100% of agreed regression scenarios pass for search-to-checkout, checkout recovery/confirmation, traveler account, partner tour authoring, and admin moderation before release.
- **SC-004**: Homepage, search results, tour detail, and blog detail each score at least 90 in a performance audit under the same documented release-test conditions.
- **SC-005**: Representative page families have zero serious/critical accessibility findings, and primary journeys are completable by keyboard alone.
- **SC-006**: 100% of new visible interface messages are available in English, Spanish, and Italian, with no untranslated placeholder in representative journeys.
- **SC-007**: Acceptance tests show zero unsupported fees, availability claims, review values, conversion rates, or private partner fields in traveler/partner-facing views.
- **SC-008**: All sampled legacy tours without new structured fields remain viewable and editable and do not fail existing publication solely because those fields are absent.
- **SC-009**: Every representative public page's search-result description, language links, and structured tour facts match its visible content and actual content language on EN/ES/IT routes, including temporary English fallback.
- **SC-010**: For every sampled tour with live-guide languages, its guide-language codes remain identical across EN/ES/IT pages while the label and available language names display in each page's language; content fallback behavior does not change with the guide-language list.
- **SC-011**: Acceptance tests show that an English-complete tour can be published before ES/IT generation, ready translations appear automatically, missing/outdated/failed translations show visibly identified current English, and an English edit cannot make an older AI result appear current.

## Assumptions

- This is a tours-only redesign; hotels, flights, restaurants, forums, ads, rewards, and AI planning are not being added.
- Tripadvisor is a structural/interaction reference, not a pixel-perfect target or source of reusable assets.
- Current authentication, booking, payment, review, publishing, and moderation policies remain authoritative; presentation changes do not replace them.
- English, Spanish, and Italian are supported; Arabic and right-to-left layout are outside scope.
- Structured tour content is additive, and legacy content remains available for fallback and backward compatibility.
- Emails, generated PDFs, and native applications are outside this web redesign.
- Existing partner/admin access boundaries remain unchanged; only approved operator information can be public.
- Partners author required English source content; the platform derives Spanish and Italian using AI. English fallback is an explicitly disclosed temporary state, not a guide-language rule.
