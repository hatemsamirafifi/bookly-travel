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

## Revision Governance Record (EGM-003 owner decision, 2026-11-14)

The project owner approved a content-ownership and revision-governance policy on 2026-11-14. It resolves the EGM-003 blocker recorded in the [Supplier Portal Goldfish review](../../docs/reports/supplier-portal-v3-goldfish-review.md): partners own and edit canonical tour content; administrators request changes with a mandatory reason and specific fields; Gemini derivatives are never manually edited; a published edit creates a separate reviewable revision while the approved public version stays unchanged; and an exceptional administrator correction is performed only through a dedicated, authorized, audited domain action and never through the general Filament form.

**Chosen correction semantics (implementation choice under the owner-approved policy).** Ordinary revision review is mandatory, there is no emergency publishing path, the correction action cannot approve or publish, and the existing EGM-002 approval guard remains the authoritative publication gate. This choice implements the owner's permitted correction mechanism; it is not an additional owner quote. The source locale stays fixed at `en`, `es`, or `it`; derivatives are generated asynchronously from the current revision, never block an eligible publication, and fall back to the canonical source with a localized notice; live-guide languages remain independent of content locales.

| Area | Active criterion (EGM-003) |
|---|---|
| Content ownership | Partners own and edit canonical tour content. Administrators request changes; they do not author canonical content through ordinary surfaces (FR-031, FR-038) |
| Review of published edits | A published edit creates a separate reviewable revision. The approved public version is unchanged until that revision is approved (FR-031, FR-040) |
| Derivatives | Gemini derivatives are never manually edited. Only the revision-bound generation job writes a derivative row (FR-037) |
| Change requests | An administrator change request carries a mandatory reason and specific fields drawn from a platform allowlist, resolved against the approved revision (FR-036) |
| Exceptional correction | A dedicated, permission-checked, audited domain action proposes the corrected content. It cannot approve, publish, or change tour lifecycle state (FR-038, FR-039) |
| Publication | `ApproveTourAction` and the EGM-002 publication guard remain the only path to `published`. There is no emergency publishing path (FR-039) |
| Compatibility | Public detail, search, SEO, image, itinerary, option and booking behaviour remain compatible; a pending revision alters no public projection and no booking snapshot (FR-040) |
| Approved-snapshot identity | Every first/re-publication establishes a **new** approved revision ID, even for equal source text. Revision approval requires the tour to currently be `published` and the observed base pointer to equal the current pointer. There is **no** separate publication epoch (FR-032, FR-035) |
| Immutable replacement | A candidate replacement inserts a **new** immutable revision row with a new ID and marks the previous row superseded; a payload is never mutated under a reviewed ID (FR-032) |
| Generation identity | The canonical generation epoch, not a publication epoch, identifies derivative work. Only the current source hash **and** the current generation epoch may change derivative rows or states (FR-037) |
| Enforcement boundary | Refusals are enforced in authenticated application boundaries, domain Actions/Services and the known writer inventory. No PostgreSQL `GUC` or trigger bypass mechanism is introduced (FR-031) |
| Activation | One coordinated cutover: old workers finish and exit, new workers no-op legacy null-epoch jobs and reconciliation queues replacements. The supported rollback is a compatible build that retains the governance guards (FR-040) |

Design of record: [Supplier Portal EGM-003 technical design v3](../../docs/designs/supplier-portal-egm003-governance-v3.md), with its [execution file/test manifest](../../docs/designs/supplier-portal-egm003-execution-plan.md); the [design index](../../docs/designs/supplier-portal-egm003-revision-governance.md) is the canonical entry point. Two independent Goldfish reviewers passed the v3 architecture on 2026-11-14; the v1 and v2 drafts are archived as `…-v1.md` and `…-v2.md`, both failed the independent readiness review and are historical review evidence rather than active contracts. This record was synchronized to v3 on 2026-11-14; no schema, migration, code or test has been written and EGM-003 remains open. FR-001–FR-030 and SC-001–SC-008 are unchanged by this record; the requirements below are additive.

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
- **FR-031**: Partners MUST own and edit canonical tour content. A content edit to a published tour MUST be submitted as a separate reviewable revision; the approved public version MUST remain unchanged until that revision is approved. Administrators MUST NOT author canonical tour content through ordinary surfaces, and no administrator surface MUST write a canonical or generated-derivative translation row. For a published tour, an unflagged write to a governed field MUST be refused by the platform's authenticated application boundaries — the domain Actions/Services, the known writer inventory and the request validation — and read-only controls alone MUST NOT be relied on. No PostgreSQL `GUC` or trigger bypass mechanism is introduced; after the coordinated cutover, an unpatched old application build and direct operator SQL are not supported mutation interfaces.
- **FR-032**: Each content revision MUST be immutable and uniquely identified per tour by a monotonic revision number, MUST carry a canonical-source content identity and the approved revision it was derived from, and MUST store a typed bounded payload whose content hash matches the canonical hash algorithm and whose separate payload hash covers the whole snapshot. Generation epochs belong to the tour and derivative states, not the candidate's approval CAS. At most one open candidate revision may exist per tour, and a newer candidate or a newer approved revision MUST supersede the earlier one. A replacement MUST merge onto the observed candidate payload, MUST refuse a stale observed base pointer or a stale observed payload hash, and MUST insert a **new** immutable revision row with a new ID and revision number while marking the previous row superseded — a payload MUST NOT be mutated under a reviewed ID.
- **FR-033**: A revision payload MUST separate canonical source content and partner-owned public presentation facts from commercial and operational metadata. The governed presentation facts are `category_id`, `slug`, `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`, `difficulty_level`, `guide_languages` and `cover_image_url`; a change to a category or a slug is reviewed like any other presentation change. Commercial and operational data MUST NOT be part of a content revision, MUST NOT be review-gated, MUST NOT be reverted by an approval, and MUST remain operable on their existing contracts. Revision-governed option content MUST cover option authored text only: promotion MUST NOT alter price, capacity, schedule, guide codes, membership, order or status, and structural option changes on a published tour MUST be refused rather than silently applied. Published option additions, reactivation, retirement and reordering are refused in this slice with a localized explanation, while an option list whose text, identity and order are unchanged MAY round-trip inside an operational price/capacity/schedule save. Derivative-locale content and platform-owned revision or provider state MUST be rejected inside a revision payload.
- **FR-034**: The canonical source locale of a tour MUST remain fixed and MUST NOT be altered by a revision creation, submission, approval, correction, or generation job.
- **FR-035**: A revision approval MUST be refused when the revision was derived from a revision that is no longer the approved revision, or when the tour is no longer `published`. Exactly one of two competing approvals MUST succeed, a terminal revision MUST NOT be approved or submitted again, and no partially applied content change may survive a refused or failed approval. Every first/re-publication MUST establish a new approved revision ID and advance the approved-revision pointer, even for equal source text; publication, unpublication and republication MUST leave that pointer consistent and MUST supersede the candidates they invalidate, so archive, unpublish and re-publish can never resurrect a candidate. There is no separate publication epoch.
- **FR-036**: An administrator change request MUST carry a reason that is non-blank after trimming and bounded at 2,000 characters, and 1–30 field references drawn from the platform allowlist. Published-review references resolve against the captured approved base; the owning partner responds with the tour's current owned submitted candidate for that base. Never-public initial-review requests instead capture source context/hash/epoch and accept a response with the current observed generation epoch, without inventing a revision ID; canonical schema fields remain referenceable when their value is missing. Option/media references must identify existing entries. Non-allowlisted or unresolvable references MUST be refused. The owning partner MUST be able to read and respond to its requests, and an answered request MUST NOT be silently reused for another revision.
- **FR-037**: Gemini derivatives MUST NOT be manually editable through any partner or administrator surface, and a legacy derivative-editing path MUST NOT apply to tours. Only the revision-bound generation job may write a derivative row. A generation job MUST be bound to the tour's canonical generation epoch as well as the source hash and the desired-state identity, MUST NOT write when its epoch is absent or older than the tour's, and MUST NOT write when the current source hash or the desired-state hash/epoch no longer match. Only the current source hash **and** the current generation epoch may change derivative rows or states; a hash alone is never sufficient after an A→B→A cycle. Candidate content MUST never reach the provider, a derivative row, a derivative state row, the search index, or a public projection.
- **FR-038**: An exceptional administrator correction MUST be performed only by a dedicated, permission-checked, audited domain action that proposes a corrected revision. It MUST declare the fields it changes, and the changed fields MUST be a subset of the declared references. The permission MUST be granted only explicitly and MUST NOT be granted to ordinary administrator roles by default. A correction MUST NOT approve, publish, or change tour lifecycle state, and MUST leave the live public content unchanged until the ordinary revision review approves it. Ordinary review of a correction is the selected implementation choice under the owner-approved correction policy, not an additional owner requirement.
- **FR-039**: Only the ordinary revision review may change the approved public version of a published tour. There MUST be no emergency publishing path, and no revision, request, or correction action may transition a tour to `published` or stamp `published_at`. The existing publication action MUST remain the only path to `published` and MAY additively maintain the approved-revision snapshot, the approved-revision pointer and the generation epoch without weakening any existing check or audit event; it MUST record the revision identity in the existing single publication audit event rather than emitting a duplicate one.
- **FR-040**: Existing public tour links, response fields, visibility behaviour, search descriptions, SEO metadata, structured facts, image ordering and alternative text, itinerary selection, option content, and booking snapshots MUST remain compatible. A pending or rejected revision MUST NOT alter any public projection or any booking snapshot, and an approved revision MUST NOT alter pricing, availability, capacity, reviews, or ratings. A public read MUST NOT combine content from two different approved revisions: the detail projection is built under a single Tour shared lock, card collections lock the sorted tour IDs in one batch, and a cached public payload MUST NOT outlive the content version it was built from. Every existing cache invalidation event MUST be preserved, including the homepage and list-cache hooks, and each invalidation MUST evict both the legacy key and the current versioned key after commit. Search and index projections MAY briefly show a previously approved projection, and MUST never show a proposed one. Activation is one coordinated cutover in which old workers finish and exit, new workers no-op legacy null-epoch jobs while reconciliation queues replacements, and the supported rollback is a compatible build that retains the governance guards; accepted snapshots, columns and stored booking snapshots are never destructively dropped or rewritten.

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
- **SC-009**: 100% of pending-revision scenarios leave the public tour-detail payload, the search projection, the SEO metadata, and every live content table identical to the pre-revision baseline; 0 derivative rows are written by any partner or administrator surface; 0 unflagged writes to a published tour's governed fields succeed through any supported surface, and mixed dirty commercial/source saves are neither partly applied nor silently dropped.
- **SC-010**: 100% of competing-approval races on real PostgreSQL produce exactly one approval, one refused approval, one materialized content set, and no partially applied state; 100% of approval refusals for a superseded base revision, a replaced candidate or a tour that left the `published` state leave the approved version unchanged; 100% of double-approval attempts on one candidate produce one approval only.
- **SC-011**: 100% of administrator change requests and exceptional corrections carry a bounded non-blank reason and 1–30 allowlisted, resolvable field references; 100% of corrections declare every field they change; 100% of corrections are recorded by exactly one audit entry; 0 corrections changed tour lifecycle state, approval state, or live public content.

## Assumptions

- The [master plan](../../docs/bookly-ui-redesign-spec-kit-master-plan.md#phase-2---tour-content-data-model-and-api) owns scope. Task 2.1 maps to FR-001-016; Task 2.2 to FR-017-021; Task 2.3 to FR-022-026; Task 2.4 to FR-027-030.
- [Spec 018](../018-bookly-ui-redesign/spec.md) remains the original program baseline. Its research and public/partner contracts inform planning; existing code and historical tests do not establish Spec 019 acceptance.
- Existing public field names and nullable no-review behavior are preserved when reconciling master-plan logical examples with detailed contracts. Verification is additive; four recommendations remain the compatible default within the eight-tour ceiling.
- Visible/flagged reviews mean existing review states eligible for public display; hidden/ineligible states are excluded. Existing public-bookability rules continue to govern recommendations and operator aggregates.
- The per-tour canonical source locale with platform-generated derivatives is an approved product decision (Constitution v3.0.0, [amendments approved under EGM-001, Option B](../../goldfish_review_report.md)). Existing derivatives remain usable when current; affected write consumers require an explicit compatibility transition during planning.
- Full tour-detail layout belongs to Phase 3 / Spec 020 and full partner-editor redesign to Phase 7 / Spec 024. This phase still verifies affected existing content entry and fallback/ready transitions.
- Provider configuration and live-generation evidence belong to implementation planning. No invented translation completion-time guarantee or unverified performance target is introduced.
- Planning design artifacts and [implementation tasks](tasks.md) were completed on 2026-10-02; this specification is not marked implemented. Implementation acceptance evidence remains a subsequent deliverable.
