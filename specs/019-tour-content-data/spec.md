# Feature Specification: Tour Content Data Model and API

**Feature Branch**: `codex/019-tour-content-data`
**Created**: 2026-10-01
**Spec ID / Master-plan phase**: `019`; UI redesign Phase 2, Tasks 2.1-2.4
**Governing Constitution**: Original delivery: [Bookly Constitution v2.1.0](../../.specify/memory/constitution.md). Active specification and release acceptance: [Constitution v3.0.0](../../.specify/memory/constitution.md) as amended on 2026-11-10; the tour-content source policy in the Amendment Record below supersedes the v2.1.0 English-source wording retained in the historical record.
**Baseline / Dependencies**: Original source `codex/bookly-ui-redesign-all-work` at `84ae4e5`; [PR #27](https://github.com/hatemsamirafifi/bookly-travel/pull/27) merged on 2026-10-02, while [Phase 1 PR #28](https://github.com/hatemsamirafifi/bookly-travel/pull/28) merged on 2026-10-03 (Africa/Cairo). The feature branch was rebased onto `f1a1f37`, retaining the original baseline and later CI fixes. Main `803048e` is now integrated by merge `60ce24d` after verified foundation commit `fb5c718`; the added Phase 1 evidence/ignore rules leave reviewed runtime source unchanged. Existing content work requires verification against this specification.
**Status**: The 2026-10-12 [acceptance record](verification.md) covers 78/78 tasks under v2.1.0. The source-language requirements below govern current acceptance under v3.0.0. Current release status is tracked separately.
**Input**: Specify master-plan Phase 2, Tour Content Data Model and API, as Spec 019. Preserve Spec 018 and align numbering and constitution.

## Amendment Record (Constitution v3.0.0, 2026-11-10)

Bookly Constitution v3.0.0 (last amended 2026-11-10 with owner approval EGM-001, Option B) replaced the mandatory English tour-authoring rule. ["Tour Content Source and Derived Locales"](../../.specify/memory/constitution.md) is the shared normative reference for source selection, validation, revision-bound derivatives, actual-language disclosure, and localized fallback. This record synchronizes Spec 019 to it; requirement IDs are unchanged. The amendment resolution and approval history are recorded in [goldfish_review_report.md](../../goldfish_review_report.md).

| Area | Superseded v2.1.0 wording (acceptance of 2026-10-12) | Active v3.0.0 criterion |
|---|---|---|
| Authored source | English is the sole partner-authored source (FR-003) | One canonical partner-authored source per tour in `en`, `es`, or `it`, selected at creation and fixed for that tour |
| Publication gates | Required English title/description at submission and approval (FR-004) | Submission and admin publication validate the title and description in the canonical source locale; derivative readiness never blocks an eligible tour |
| Derivatives | Spanish/Italian generated from the current English revision (FR-010-013) | Gemini generates both non-source locales asynchronously from the current source revision. Superseded jobs cannot replace current data |
| Display fallback | Missing derivative shows current English with a visible notice (FR-014-016) | Display uses the current derivative or the current source. Content and itinerary can fall back independently, with visible notices in the page language that identify the actual source |
| SEO | Metadata describes displayed content | Unchanged in principle; descriptions and structured content reflect the values displayed for every supported source locale |
| Guide languages | Independent of content locales | Unchanged (FR-017) |
| Legacy rows | Pre-amendment treatment of all tours as English | Existing rows and legacy callers may default to `en` where evidenced; a compatibility default, not a restriction on new authoring |

FR-008 and FR-009 retain the English-row scope of the legacy adoption path. Current tours use the fixed source locale. The dated [verification record](verification.md) preserves the original acceptance criteria and results.

| Active requirements | Current test coverage | Remaining acceptance evidence |
|---|---|---|
| FR-001, FR-003, FR-004 / SC-001, SC-003 | [Partner API and admin approval](../../backend/tests/Feature/Partner/TourOptionsTest.php) cover Spanish and Italian authoring, immutable source, rejection of required fields supplied only by a derivative, and eligible publication with pending derivatives. [English source and draft tests](../../backend/tests/Feature/Partner/TourCreateTest.php) retain legacy cases. | Browser create, reopen, submit, and admin review for each source. Verify every publication entry point uses the source guard. |
| FR-010–013 / SC-004 | [Translation tests](../../backend/tests/Feature/Partner/TourTranslationTest.php), [PostgreSQL concurrency tests](../../backend/tests/Feature/Partner/TourTranslationConcurrencyTest.php), and [option translation tests](../../backend/tests/Feature/Partner/TourOptionsTest.php) cover queued generation, provider failures, superseded results, and non-English source regeneration. | Staging worker smoke with the configured Gemini model for every source. |
| FR-014–017 / SC-002, SC-006 | [Public detail API tests](../../backend/tests/Feature/Search/TourDetailTest.php) cover revision states, independent itinerary fallback, and guide-language independence. [Public detail component tests](../../frontend/src/components/tour/__tests__/TourDetail.test.tsx) verify localized notices and actual language for English, Spanish, and Italian fallback. | Verify source, itinerary, and SEO language together on deployed public routes across the supported source and page locales. |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Author reliable tour content in the canonical source locale (Priority: P1)

As an authorized partner, I can save and edit my own tour's content and structured itinerary in the one canonical source locale I select at creation (`en`, `es`, or `it`) without authoring the other two locales, so that travelers receive accurate information about my experience.

**Why this priority**: Reliable source content and ownership precede traveler presentation.

**Independent Test**: Create, save, reopen, and edit an owned draft; compare values and ordering, exercise validation boundaries, and attempt edits as another partner.

**Acceptance Scenarios**:

1. **Given** an owned draft, **When** the partner saves canonical-source content and ordered itinerary days/stops, **Then** reopening preserves all accepted values and order.
2. **Given** missing canonical-source title or description, **When** the partner saves an incomplete draft, **Then** existing draft behavior remains available, but submission identifies the missing required content.
3. **Given** invalid itinerary values, **When** the partner saves, **Then** field-specific errors are provided and no partial content/media change is accepted.
4. **Given** another partner's tour, **When** a partner attempts to change it, **Then** access is denied and the tour remains unchanged.

### User Story 2 - Read current content in the requested language (Priority: P1)

As a traveler browsing in English, Spanish, or Italian, I see current tour information and a clear notice when the canonical source is being shown instead, so that I understand the content's actual language.

**Why this priority**: Stale or silently mixed-language content can misrepresent an experience.

**Independent Test**: Read a published tour in all three languages with current, missing, pending, stale, and failed derivatives; verify content, itinerary, notices, and guide languages independently.

**Acceptance Scenarios**:

1. **Given** a current Spanish derivative, **When** a traveler browses in Spanish, **Then** Spanish content is displayed and identified as Spanish.
2. **Given** an unavailable or outdated Italian derivative, **When** a traveler browses in Italian, **Then** the current canonical source is displayed with an Italian fallback notice and disclosed in its actual language.
3. **Given** current Spanish general content and an unavailable Spanish itinerary, **When** the tour is read, **Then** general content stays Spanish while the current canonical-source itinerary is separately disclosed.
4. **Given** German-speaking guides, **When** the traveler changes page language, **Then** guide-language codes remain unchanged while labels follow the page language.
5. **Given** a fallback that later gains a current derivative, **When** the traveler next loads the tour, **Then** the derivative replaces the fallback without partner re-entry or a route change.

### User Story 3 - Publish while translations progress safely (Priority: P1)

As a partner or administrator, I can submit and approve an otherwise eligible tour with complete canonical-source content while translations progress separately, so that generation delays do not prevent publication.

**Why this priority**: Publishing must retain approval controls without depending on provider availability.

**Independent Test**: Save source revisions, approve while derivatives are unavailable, retry failed generation, and complete older work after a newer source edit.

**Acceptance Scenarios**:

1. **Given** complete canonical-source content and satisfied existing approval requirements, **When** an administrator approves with any derivative pending or failed, **Then** publication succeeds and travelers receive the current canonical source with notices.
2. **Given** changed canonical-source traveler-facing content, **When** the partner saves, **Then** the save succeeds independently and both derivatives are scheduled for the new source revision.
3. **Given** generation for an older revision, **When** the canonical source changes before completion, **Then** the old result cannot become current or overwrite newer content.
4. **Given** failed or duplicate generation, **When** a retry or duplicate completion occurs, **Then** the current source remains intact and no duplicate or superseded derivative is introduced.
5. **Given** unchanged canonical-source content, **When** an unrelated setting is saved, **Then** current derivatives remain current without redundant generation.

### User Story 4 - Maintain truthful difficulty, guide languages, and photos (Priority: P2)

As a partner, I can maintain my tour's difficulty, spoken guide languages, and ordered photos; travelers receive those accepted facts and the complete real gallery.

**Why this priority**: These facts help travelers judge suitability and understand the experience.

**Independent Test**: Round-trip each difficulty and guide-language list; update ordered galleries with a cover and duplicate URLs; read a legacy cover-only tour.

**Acceptance Scenarios**:

1. **Given** supported difficulty and guide languages, **When** the partner saves and reopens, **Then** both persist independently of translations.
2. **Given** gallery images with a cover, **When** photos are displayed, **Then** the cover appears first, other images retain relative order, and each URL appears once.
3. **Given** an unacceptable media update, **When** saving fails, **Then** tour content and media remain consistent with no partial change.
4. **Given** a legacy cover-only tour, **When** it is displayed, **Then** that real image remains available and missing specific alternative text uses the selected tour title in its actual language.

### User Story 5 - Evaluate the operator and eligible alternatives (Priority: P2)

As a traveler, I can inspect a safe public operator summary and relevant bookable alternatives without receiving private account details or misleading review statistics.

**Why this priority**: Operator transparency supports discovery while privacy and review accuracy preserve trust.

**Independent Test**: Compare approved/inactive/unapproved profiles, eligible/ineligible tours, and review states; verify aggregates and recommendation ranking.

**Acceptance Scenarios**:

1. **Given** an approved active profile, **When** a traveler opens its tour, **Then** allowlisted public information and eligible aggregates are available with no private details.
2. **Given** an inactive or unapproved profile, **When** an otherwise publicly visible tour is read, **Then** no operator summary or verification claim is supplied.
3. **Given** zero eligible reviews, **When** operator information is displayed, **Then** review count is zero and no invented average is shown.
4. **Given** related candidates, **When** alternatives are selected, **Then** only other published bookable tours appear, destination matches precede category-only matches and then rating, and existing card information is preserved.
5. **Given** fewer than four eligible alternatives, **When** recommendations are displayed, **Then** only real eligible tours appear, without padding.

### User Story 6 - Preserve older tours and consistent search descriptions (Priority: P2)

As a traveler or existing Bookly consumer, I can continue using existing tour links and information after structured content is introduced; search descriptions agree with content I can read.

**Why this priority**: Existing tours must remain usable during incremental rollout.

**Independent Test**: Compare representative legacy tours and consumer expectations before/after adoption, including malformed itineraries, populated English content, no reviews, and language fallback.

**Acceptance Scenarios**:

1. **Given** a valid legacy itinerary with no newer structured English content, **When** it is adopted, **Then** meaningful days/stops and order survive in English and the legacy source is retained.
2. **Given** invalid or empty legacy data, **When** the tour is read, **Then** its itinerary is empty rather than fabricated or broken.
3. **Given** populated structured English content, **When** adoption repeats, **Then** that content is not overwritten or duplicated.
4. **Given** an existing tour link and consumer, **When** richer content is introduced, **Then** fields, spoken-language meaning, routes, and booking rules remain compatible.
5. **Given** real itinerary, images, meeting point, and no eligible reviews, **When** search descriptions are produced, **Then** they describe selected visible facts and omit an aggregate-rating claim.

### Edge Cases

- Empty itineraries, zero-stop days, exactly 30 days and 20 stops/day, maximum title/description lengths, and values just beyond each limit.
- Day numbers outside 1-30, fractional day/duration values, null descriptions/durations, durations outside 1-1,440 minutes.
- Repeated source edits during generation; one locale succeeds while another fails; duplicate completion; retries after a newer revision.
- Current general content with missing itinerary; empty canonical-source itinerary; unsupported content locale; translation state manipulated by a partner.
- Missing cover, repeated URLs, unchanged gallery update, missing alternative description, or no photos.
- Ineligible operator, zero eligible reviews/tours, hidden reviews, unbookable recommendations, and ranking ties.
- Malformed legacy data, partially valid legacy arrays, repeated adoption, and already populated structured English content.
- Unauthorized content/media edits and private operator information introduced into public fields.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Authorized partners MUST create, save, reopen, and update their own canonical-source content and structured itineraries with accepted values and day/stop order preserved.
- **FR-002**: Content, itinerary, difficulty, guide-language, and media writes MUST enforce existing authentication, permissions, and ownership.
- **FR-003**: Each tour MUST have one canonical partner-authored source for titles, descriptions, itineraries, inclusions/exclusions, important information, and other traveler-facing tour content, in `en`, `es`, or `it`. The partner selects the source locale at creation and it MUST remain fixed for that tour. Partners MUST NOT be required to author the two derived locales.
- **FR-004**: Incomplete draft snapshots MUST remain available. Submission and admin publication MUST require a trimmed nonempty title and description in the fixed source locale and satisfy existing approval requirements. Missing, pending, stale, or failed derivatives MUST NOT block eligible publication.
- **FR-005**: Itineraries MUST contain at most 30 ordered days, with integer day numbers from 1 through 30, required non-empty titles of at most 160 characters, nullable descriptions of at most 2,000 characters, and at most 20 ordered stops per day.
- **FR-006**: Stops MUST have required non-empty titles of at most 160 characters, nullable descriptions of at most 2,000 characters, and nullable integer durations from 1 through 1,440 minutes. Empty itineraries and zero-stop days MUST be supported.
- **FR-007**: Invalid new content MUST receive field-specific errors without partially changing the tour or media.
- **FR-008**: Valid legacy itineraries MUST become structured English content only when newer structured English content is absent. Invalid/empty legacy values MUST yield an empty itinerary; repeated adoption MUST preserve populated structured content.
- **FR-009**: Legacy itinerary data MUST be retained during adoption; new authored itinerary changes MUST use the structured source of the tour's canonical locale (pre-amendment rows store English) instead of new legacy writes.
- **FR-010**: A successful save of new or changed traveler-facing source content MUST schedule Gemini generation of the two other locales from the current source revision after commit. The save MUST NOT wait for generation. Unchanged source content MUST NOT invalidate derivatives or trigger redundant generation.
- **FR-011**: Each derived locale MUST have distinguishable current/ready, pending, stale, or failed state relative to the canonical-source revision. Partners MUST NOT forge platform-owned readiness or revision state.
- **FR-012**: Only results for the current canonical-source revision MAY become current. Superseded results, duplicate completions, and retries MUST NOT overwrite newer content, duplicate derivatives, or damage the source.
- **FR-013**: Failure MUST preserve the source and permit safe retry. Partners/admins MUST understand readiness without receiving provider secrets or internal failure details.
- **FR-014**: Public English/Spanish/Italian selection MUST use a current requested derivative when available and the current canonical source otherwise; stale content MUST NOT appear as current.
- **FR-015**: General content and itinerary MUST select their languages independently when necessary and disclose the actual language of each. A visible notice in the page language MUST identify non-empty canonical-source fallbacks; empty itineraries MUST not create a misleading notice or fabricated content.
- **FR-016**: Subsequent reads MUST select newly current derivatives without partner re-entry, republishing, or route changes.
- **FR-017**: Spoken guide languages MUST persist as independent stable tour-level codes; labels MUST follow page language and the existing spoken-language alias MUST retain its meaning.
- **FR-018**: Supplied difficulty MUST persist as easy, moderate, or challenging. Older tours without difficulty MUST remain usable without an invented value.
- **FR-019**: Media and tour updates MUST form one consistent accepted change. Public galleries MUST use real ordered media, put the selected cover first, retain remaining order, and deduplicate URLs.
- **FR-020**: A real legacy cover MUST remain a fallback when no usable gallery exists; a tour without photos MUST remain empty.
- **FR-021**: Photos MUST use specific alternative descriptions when available or a truthful fallback from the selected tour title in its actual language.
- **FR-022**: Operator summaries MUST require an approved active profile and contain only public name, description, logo, verification indicator, eligible tour count, review count, and average rating. Verification MUST mean approved and active.
- **FR-023**: Public content MUST NOT expose operator email, phone, address, tax/payout information, internal user IDs, payment-provider IDs, or private operational notes.
- **FR-024**: Operator aggregates MUST count eligible published currently bookable operator tours and their visible/flagged reviews only. Zero eligible reviews MUST yield zero count and absent/null average, preserving existing eligibility rules.
- **FR-025**: Related tours MUST be other published currently bookable tours, without duplicates, retaining existing card information. Preserve four as the default limit and eight as the absolute ceiling; return fewer when necessary.
- **FR-026**: Ranking MUST prioritize same destination, then same category, then higher genuine rating; ties MUST have deterministic order.
- **FR-027**: Existing public fields, tour links, locale prefixes, canonicals, visibility, booking rules, and spoken-language meaning MUST remain compatible. Private translation revisions and provider errors MUST NOT become public fields.
- **FR-028**: Search descriptions and structured tour facts MUST use the same selected current content and actual language as public display, preserve real itinerary ordering and meeting point, and include all real gallery images.
- **FR-029**: Search-visible aggregate ratings MUST be omitted without eligible reviews; content MUST NOT invent itinerary, operator claims, images, prices, availability, or ratings.
- **FR-030**: Affected entry, source-save, approval, fallback, and error feedback MUST have English/Spanish/Italian interface copy and satisfy existing accessibility/responsive requirements.

### Key Entities

- **Tour**: Partner-owned experience, existing lifecycle, difficulty, guide-language codes, and legacy compatibility information.
- **Canonical Source Revision**: Partner-authored traveler-facing content in the tour's fixed source locale, including itinerary and revision identity determining derivative currency.
- **Derived Tour Content**: Platform-generated content in the two non-source locales, tied to a canonical-source revision and readiness state.
- **Itinerary Day / Stop**: Ordered activities with bounded titles, nullable descriptions, and optional stop durations.
- **Tour Media**: Real ordered photos, cover designation, URLs, and optional alternative descriptions.
- **Public Operator Summary**: Allowlisted eligible operator information and truthful aggregates.
- **Related Tour Selection**: Bounded ordered eligible alternatives using existing card information.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In the acceptance set, 100% of valid source saves reopen with identical accepted values/order; every invalid boundary is rejected without partial content/media changes.
- **SC-002**: Across all three page languages and all documented derivative states, 100% of content selections use current values and correctly disclose non-empty canonical-source fallbacks, including independent itinerary fallback.
- **SC-003**: Every eligible publication scenario with unavailable derivatives succeeds when canonical-source content and existing approval rules are satisfied; every missing-required-canonical-source submission/approval is refused.
- **SC-004**: All source-race, retry, duplicate-completion, and unchanged-save scenarios preserve the current canonical source and prevent older derivatives from appearing as current.
- **SC-005**: All valid meaningful legacy itinerary content/order survives adoption; invalid/empty records yield empty itineraries; repeated adoption overwrites zero populated structured English itineraries.
- **SC-006**: All gallery/difficulty/guide-language scenarios display saved supported facts, correctly ordered unique real images, and guide-language codes unchanged by content-locale selection.
- **SC-007**: All operator/recommendation scenarios expose zero prohibited private fields, truthful eligible aggregates, no current/ineligible tours, correct ranking, and the four-default/eight-maximum limits.
- **SC-008**: Every existing-link/consumer compatibility scenario remains usable; all search-description scenarios agree with selected visible content, language, itinerary, meeting point, images, and review facts, with zero fabricated claims.

## Assumptions

- The [master plan](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-2---tour-content-data-model-and-api) owns scope. Task 2.1 maps to FR-001-016; Task 2.2 to FR-017-021; Task 2.3 to FR-022-026; Task 2.4 to FR-027-030.
- [Spec 018](../018-bookly-ui-redesign/spec.md) remains the original program baseline. Its research and public/partner contracts inform planning; existing code and historical tests do not establish Spec 019 acceptance.
- Existing public field names and nullable no-review behavior are preserved when reconciling master-plan logical examples with detailed contracts. Verification is additive; four recommendations remain the compatible default within the eight-tour ceiling.
- Visible/flagged reviews mean existing review states eligible for public display; hidden/ineligible states are excluded. Existing public-bookability rules continue to govern recommendations and operator aggregates.
- The per-tour canonical source locale with platform-generated derivatives is an approved product decision (Constitution v3.0.0, [amendments approved under EGM-001, Option B](../../goldfish_review_report.md)). Existing derivatives remain usable when current; affected write consumers require an explicit compatibility transition during planning.
- Full tour-detail layout belongs to Phase 3 / Spec 020 and full partner-editor redesign to Phase 7 / Spec 024. This phase still verifies affected existing content entry and fallback/ready transitions.
- Provider configuration and live-generation evidence belong to implementation planning. No invented translation completion-time guarantee or unverified performance target is introduced.
- Planning design artifacts and [implementation tasks](tasks.md) were completed on 2026-10-02; this specification is not marked implemented. Implementation acceptance evidence remains a subsequent deliverable.
