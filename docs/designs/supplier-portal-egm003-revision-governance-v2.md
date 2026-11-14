# EGM-003 Phase 1 (v2) — Tour Content Revision Governance

**Status** Design only. v2 supersedes v1 (`…-v1.md`, archived, **failed readiness**, not canonical). No schema, migration, code, test or Filament change written. Nothing committed; no git state reset/stashed/cleaned/restored/switched.
**Date** 2026-11-14 · **Baseline** `f07d3d0` · **Policy** [ADR 0024](../../adr/0024-tour-content-governance.md) (parent-owned, unedited) · **Review** [design review](../reports/supplier-portal-egm003-design-review.md) (parent-owned, unedited)
**Refs** [Constitution v3.0.0](../../.specify/memory/constitution.md) · [plan](../superpowers/plans/2026-11-10-bookly-supplier-portal.md) · [Goldfish](../reports/supplier-portal-v3-goldfish-review.md) · [Spec 019](../../specs/019-tour-content-data/spec.md) · [Spec 020](../../specs/020-tour-detail-experience/spec.md) · [Spec 018](../../specs/018-bookly-ui-redesign/spec.md)

**Owner policy (2026-11-14, unchanged by v2)** — partners own canonical tour content; admins request changes with a mandatory reason and specific fields; Gemini derivatives are never manually edited; published edits create a reviewable separate revision and the approved public version stays unchanged; exceptional admin corrections only through a dedicated authorized audited domain Action, never the general Filament form. Ordinary revision review mandatory; no emergency publishing path; the correction action cannot approve or publish; the EGM-002 approval guard stays authoritative. No new locale policy: source fixed `en/es/it`; derivatives from the current revision, async, non-blocking, localized source fallback; guide languages independent.

**Baseline (parent-verified, not re-run here):** PHPStan **0 errors**; PHP 8.3.36 **with** JPEG; backend **102 tests / 726 assertions**; frontend **4 Jest suites / 68 tests**. The "21 PHPStan errors" / "GD JPEG failure" results are **historical** (EGM-002 report). **No test result is claimed here.**

---

## 1. v1 → v2: every finding resolved

| # | Finding | Resolution (§) |
|---|---|---|
| 1 | Published-content writes; no writer matrix | Full field×writer matrix (4.1); governed set; **DB triggers** refuse unflagged writes and survive an app rollback (4.2); repeater removed; mixed payloads routed atomically (4.3) |
| 2 | Text-only option promotion overwrites commerce | `syncOptions` **not** reused (2); dedicated **text-only materializer** (5.2); structural changes fail closed (5.3); unchanged-text commercial round-trip preserved (5.4) |
| 3 | Published option saves lack a compatible contract | Classifier compares text/membership/order with live values: unchanged → existing contract; changed → 422 to the revision path (4.3, 5.4) |
| 4 | Unsafe activation window / pointer drift | **One coordinated activation**: migration (tables, columns, triggers) + backend + Filament + frontend together → **no legacy-writer window**; faithful baseline under the tour lock (11) |
| 5 | Missing epoch / legacy completion fences | Five identities (3.1); `generation_epoch` makes A‑B‑A safe; media-only approval keeps the epoch; null-epoch legacy jobs fenced; pre-publication jobs defined; legacy engine retired with a **completion-time** check + queue drain (9) |
| 6 | Public read/cache coherence unsupported | Detail read takes a **shared lock on the tour row** in a transaction (cannot straddle an approval); cache key **versioned** by `content_version`; index refresh **after commit**; search lag qualified (8) |
| 7 | Candidate not restorable via API | `GET …/revisions/{id}` returns the **full candidate payload**; replacement merges onto the **observed candidate** with mandatory `observed_payload_hash`; approval binds to the **exact** revision id; admin sees that candidate + diff (7) |
| 8 | Contradictory states / action contracts | Executable transition table; terminal-replay rules; one-active-candidate rule; typed signatures; reason **required/non-blank/≤500** (existing bound); no four-eyes rule; explicit grants; changed-paths ⊆ declared refs; refusal audits after rollback (6, 7.3) |
| 9 | Backfill / rollback under-specified | Faithful verbatim baseline, locked, idempotent, resumable; lazy capture; rollback fail-closed via triggers (11.2, 11.4) |
| 10 | Acceptance commands miss promised tests | `a11y-partner` matches **only** `tests/e2e/a11y/partner-a11y.spec.ts`; `tests/e2e/partner/*.spec.ts` runs in `chromium-partner`/`mobile-partner`; new spec → `tests/e2e/partner/`, Axe cases → the existing `partner-a11y.spec.ts` (no config change); ownership matrix + existing commands (13) |

**Removed from v1:** the two-release feature-flag activation, the `capabilities.revision_review` handshake, the optional-feature owner questions. v2 has one activation, no speculative options.

---

## 2. Verified facts

**Methods that are reused as-is** (behavior read, not assumed): `normalizeSourcePatch`/`sourceRules` (nested `translations.{source}.{field}` wins per field, top-level shorthand fills absent fields — **both aliases validated**); `forbiddenPresentPaths` (rejects a non-source locale, unknown source keys, platform-owned/provider keys); `TourService::syncTranslations` (present-key, `title` skipped when null, description sanitized); `TourService::syncMedia` (deletes + recreates `tour_media`, cover selection, writes `cover_image_url`); `withContentLock` (`Tour` FOR UPDATE → source translation → derivative states, platform locale order); `currentTranslation` (+ epoch check, 9.1); `planSourceDispatches`/`dispatchPlannedTranslations`/`reconcileTour` (+ epoch, 9.3); `GovernanceAuditService::log`; `AdminAuthorizationService` (JSONB flags, code-constant list → a new flag needs no migration); `TourPublicationGuardService::lockTourForMutation`; `ApproveTourAction` (+ additive snapshot maintenance, 11.3); `UnpublishTourAction` (+ epoch, 11.3); `RejectTourRequest` reason bound (required, ≤500).

**Methods that are NOT reused as-is**

| Method | Actual behavior | Consequence |
|---|---|---|
| `TourService::syncOptions()` | Rewrites **every** option column (`title`, `pickup_instructions`, `guide_languages`→`[]`, `price_amount`, `group_size_max`, `start_time`, `weekdays`, `sort_order`=index, `status`=active, `retired_at`=null); unlisted options retired | Unusable for text promotion → 5.1 |
| `TourTranslationService::sourceHash()` | SHA-256 of the normalized canonical projection (9 fields fixed order + canonicalized itinerary + **active** option texts keyed by id in `sort_order` order); excludes price/capacity/schedule/guide codes | A **value** identity, not a revision identity → 3.1 |
| `Tour::supportsFieldTranslation()` | `source_locale === 'en'` **and** no options configured | The legacy engine touches only that subset → 9.4 |
| `TranslateContentFieldJob` | Checks `supportsFieldTranslation()` **only before provider I/O** (L83), then writes the derivative row and marks `tour_translation_states` `ready` with `translated_hash = sourceHash` (L120‑139, L247‑257), no re-check | In-flight legacy jobs can write after retirement → 9.4 |
| `TourResource::form()` | Writes `slug`, `category_id`, `partner_id`, `status` (disabled/non-dehydrated), `location`, `location_slug`, `price_amount`, `duration_minutes`, `duration_label`, `group_size_min/max`, `is_featured`, `cover_image_url` and a **`translations` repeater** bound to the relationship (writes every locale) | The repeater is the manual content/derivative write path → 10.1 |
| `TourContentRules::optionRules()` | `options.*.title`, `options.*.guide_languages`, `options.*.price_amount` all **required** (price ≥1) | A text-only option array is invalid today → 5.4 |

**Cache/index.** The public detail **is** cached (`TourDetailController`: `Cache::remember('tour.'.$slug.'.'.$locale, 900, …)`). Eviction is **pre-commit** (`Tour`/`TourTranslation`/`TourTranslationState` `booted()` hooks call `Cache::forget` inside the transaction) → a concurrent reader can re-fill the old payload. List/search/homepage/destination caches (TTL 300–1800 s) are **not** evicted by those hooks — pre-existing, unchanged (8.4). `IndexTourAction` is queued and re-reads the model; `Tour::saved` also enqueues a Scout push inside the transaction.

**Schema.** `tour_translations` unique `(tour_id, locale)`; `tour_translation_states` unique `(tour_id, locale)`; `tour_option_translations` unique `(tour_option_id, locale)`; `tour_options` has `sort_order`/`status`/`retired_at`. `tours` has **no** revision table, pointer or `lock_version`. `content_revisions` exists only for `static_page`/`blog_post` (flat attribute snapshot; `manage_revisions|manage_cms|manage_blog` policy) → **not reused**. System role templates are code-defined (`super_admin` wildcard, `operations_admin`, `content_manager`, `finance_viewer`, `support_agent`, `media_manager`, `translator_editor`).

**Frontend.** The partner edit page `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx` issues a raw `PUT /api/partner/tours/{id}` with `translations:{[sourceLocale]:sourcePayload}` plus `options`/`media` when dirty; it never sends `source_locale` on update. Read shape is `translations: Array<{locale,…}>`, write shape `translations:{[locale]:{…}}`; the page keeps a **page-private duplicate** `Tour` type. Playwright: `PARTNER_A11Y = /e2e/a11y/partner-a11y\.spec\.ts$/` → `a11y-partner` matches **only** `tests/e2e/a11y/partner-a11y.spec.ts`; `PARTNER_AUTHED = /e2e/partner/[^/]*\.spec\.ts$/` → `chromium-partner`/`mobile-partner` run every `tests/e2e/partner/*.spec.ts`; the `setup` project runs `*.setup.ts` (`tests/e2e/auth.setup.ts`, `tests/e2e/partner.setup.ts`) and writes `tests/.auth/{traveler,partner}.json`.

---

## 3. Identities and data model

**3.1 Five identities (one job each)**

| Identity | Where | Changes when | Used for |
|---|---|---|---|
| Approved snapshot | `tours.published_revision_id` → `tour_content_revisions.id` | an approval materializes content | public content identity; candidate CAS base |
| Content version | `tours.content_version` | anything that changes the rendered public payload (content, media, a derivative becoming current) | versioned public cache key |
| Generation epoch | `tours.generation_epoch` | the **canonical source hash** changes | derivative generation identity; makes A‑B‑A safe |
| Publication epoch | `tours.publication_epoch` | publish / unpublish / republish | invalidates active candidates |
| Candidate CAS token | `tour_content_revisions.base_revision_id` | — | the observed base a candidate was derived from |

Plus `tour_translation_states.generation_epoch` (per-locale desired-state identity). The content **hash** is a value identity, **not** a revision identity.

**3.2 New tables.** `tour_content_revisions` (immutable content): `id`, `tour_id` (FK cascade), `revision_number` (monotonic per tour, allocated under the tour lock; unique with `tour_id`), `kind` (`baseline|partner_edit|admin_correction`), `state` (`draft|proposed|approved|rejected|superseded|withdrawn`), `source_locale` (copied from the tour), `content_hash`, `payload` (3.4), `payload_hash`, `base_revision_id` (self FK), `publication_epoch`, `author_type`, `author_id`, `submitted_at`, `decided_at`, `decided_by`, timestamps. **Model-level immutability**: `payload`, `payload_hash`, `content_hash`, `source_locale`, `revision_number`, `kind`, `base_revision_id`, `publication_epoch` cannot change; only workflow columns may.
`tour_revision_requests` (workflow): `id`, `tour_id` (FK), `revision_id` (FK, nullable — the candidate answering it), `kind` (`admin_change_request|admin_correction|partner_edit`), `state` (`open|answered|approved|rejected|superseded|closed`), `reason` (required, non-blank, ≤500), `field_refs` (jsonb `[{path,note?}]`), `response_note`, `requested_by_type`, `requested_by_id`, `decided_by`, `decided_at`, `decision_reason`, timestamps.

**3.3 Additive columns.** `tours.published_revision_id` (null FK, `nullOnDelete`), `tours.content_version` (0), `tours.generation_epoch` (0), `tours.publication_epoch` (0); `tour_translation_states.generation_epoch` (`NOT NULL DEFAULT 0`; existing rows backfilled to 0).

**3.4 Typed bounded payload — three tiers**

```jsonc
{ "content": {
    "canonical": { "title","description","highlights","inclusions","exclusions",
                   "important_information","meeting_point","cancellation_policy","itinerary" },
    "options":  [ { "id": 12, "title": "…", "pickup_instructions": "…" } ],
    "media":    [ { "url": "https://…", "alt_text": "…", "is_cover": true } ] },
  "presentation": { "location","location_slug","duration_minutes","duration_label",
                    "group_size_min","group_size_max","difficulty_level","guide_languages",
                    "cover_image_url" } }
```

Content tier = the 9 canonical source fields (existing bounds) + option authored text + media/alt → **stored in a revision**. Presentation tier = the partner-owned public facts on the tour row → **stored in a revision**. Commercial/operational tier = price, pricing tiers, availability, option price/capacity/schedule/guide codes, option membership/order/status, category, slug, featured, lifecycle, ownership → **not** in a revision; a payload containing it, an unknown key, a derivative-locale key or a platform-owned key is refused with the exact path (reusing `forbiddenPresentPaths()`). The API sends a **patch**; the service resolves it against the merge base and stores a **complete snapshot** (omitted preserves, explicit `null` clears nullable text, `[]` clears a list).

---

## 4. Writer matrix and enforcement

**4.1 Complete field × writer matrix (published tours).** "Governed" = writable only inside a governed transaction while the tour is `published`.

| Field / storage | Partner general `PUT` | Filament general form | Class |
|---|---|---|---|
| `tour_translations` **source-locale row** (9 fields, both aliases) | `syncTranslations` | **translations repeater** | **GOVERNED** |
| `tour_translations` **derivative rows** | rejected by `forbiddenPresentPaths` | **repeater writes them** | **NEVER manually editable** |
| `tour_options.title` / `.pickup_instructions` | `syncOptions` | none | **GOVERNED** |
| `tour_options` price/capacity/start time/weekdays/guide codes | `syncOptions` | none | operational (partner) |
| `tour_options` membership/order/status | `syncOptions` | none | operational (partner); **structural change on a published tour fails closed** (5.3) |
| `tour_option_translations` | forbidden | none | platform-only (job) |
| `tour_media` (url, alt_text, type, sort_order) | `syncMedia` | none | **GOVERNED** |
| `tours.cover_image_url` | direct field + `syncMedia` | **FileUpload** | **GOVERNED** |
| `tours.location`, `location_slug` | `updateTour` | **TextInput** | **GOVERNED** |
| `tours.duration_minutes`, `duration_label` | `updateTour` | **TextInput** | **GOVERNED** |
| `tours.group_size_min`, `group_size_max` | `updateTour` | **TextInput** | **GOVERNED** |
| `tours.difficulty_level` | `updateTour` | **TextInput** | **GOVERNED** |
| `tours.guide_languages` | `updateTour` | none | **GOVERNED** |
| `tours.price_amount` | `updateTour` (`price_from`) | **TextInput** | commercial — operational |
| `tours.category_id` | `updateTour` (`category`) | **Select** | taxonomy — operational; admin form read-only on a published tour |
| `tours.slug` | `updateTour` | **TextInput** | identity — operational; admin form read-only on a published tour |
| `tours.is_featured` | — | **Toggle** | operational (admin) |
| `tours.status`, `published_at` | `status` prohibited | disabled display (approve/reject/unpublish/archive actions) | governance (EGM-002 unchanged) |
| `tours.partner_id` | — | **Select** | governance (EGM-002 unchanged) |
| `tour_translation_states` | forbidden | none | platform-only (job / reconcile) |
| `pricing_tiers`, `availability_rules/exceptions` | dedicated partner routes | `AvailabilityResource` read-only | commercial — operational |
| `bookings.*` (option title, pickup, content locale, amounts) | — | — | snapshot — never rewritten by a revision |

Also written: the legacy `tours:adopt-itineraries` command writes the EN itinerary of a legacy tour → after activation it must refuse published tours (it would otherwise bypass the governed flow) and set the governed-write flag only for the tours it explicitly adopts.

*Decisions where the policy is silent (implementation choices):* `price_amount`, `category_id`, `slug`, `is_featured`, option commercial fields and option membership stay **operational** on the existing contracts, and the general admin form cannot change them for a published tour (no governed admin operation exists for them; a partner applies commercial changes through their own routes). The Constitution already assigns pricing and availability to partners, so no new policy is invented. A published tour's **slug** stays operable by the partner on the existing contract — URL stability is a pre-existing concern unchanged by EGM-003 — while the admin form cannot change it.

**4.2 Enforcement: the database is authoritative.** A migration installs PostgreSQL triggers refusing an unflagged write to a governed field of a **published** tour: `tour_translations` rows whose `locale = tours.source_locale`; `tour_options` `title`/`pickup_instructions`; `tour_media` insert/update/delete; `tours` `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`, `difficulty_level`, `guide_languages`, `cover_image_url`. Governed transactions set a transaction-local flag (`SET LOCAL bookly.governed_write='1'`) that only the domain Actions set. An old build, a command, a seeder or raw SQL is refused by the database; the application maps the SQLSTATE to `422 content_requires_revision`. Application layer: the Filament repeater is removed and the form is read-only for a published tour; the partner `PUT` returns a precise 422 before any write.

**4.3 Mixed payload atomic routing (partner `PUT`, published tour).** Inside the tour lock, **before any write**, the request is classified against live values: **G** = governed fields present **and changing**; **X** = governed fields present but unchanged (e.g. round-tripped option titles); **O** = operational/commercial fields. G non-empty → **the whole request is refused** (`422 content_requires_revision`, exact paths) and **nothing** is written — neither the governed nor the operational field (no partial save, no silent drop). G empty → the request proceeds exactly as today: O applied, X rewritten identically (a no-op). For a **non-published** tour the existing `PUT` behaviour is unchanged (a draft is the partner's working copy). The same classifier backs the Filament form: for a published tour every remaining field is refused (the form is read-only).

---

## 5. Options: a dedicated text materializer

**5.1** `syncOptions` cannot be reused: it rewrites price, capacity, start time, weekdays, guide codes, `sort_order`, `status` (→active), `retired_at` (→null) and retires unlisted options — promoting a text-only snapshot through it destroys live commerce.

**5.2 `TourOptionTextMaterializer`.** `apply(Tour $tour, array $texts): void` — for each `{id → {title, pickup_instructions}}` whose id belongs to the tour and whose row is active: `UPDATE tour_options SET title=?, pickup_instructions=? WHERE id=? AND tour_id=?`. It never writes price, capacity, start time, weekdays, guide codes, `sort_order`, `status` or `retired_at`; never inserts, deletes, retires, reactivates or reorders. Used only by the approval path.

**5.3 Structural option changes on a published tour fail closed.** The candidate's `options` must contain **exactly the tour's active option ids, in the live order**; any addition, removal or reordering → `422 option_structure_change_not_supported` with UI guidance (structural options belong to the A3 slice). Forbidding reorder also keeps the canonical hash stable across an approval (option order participates in the hash).

**5.4 The partner's existing option contract on a published tour.** `optionRules()` still requires title + guide languages + price, so the full commercial array is still sent. What changes is the comparison of text, membership and order against live values: unchanged text + unchanged membership/order → the existing commercial edit applies unchanged (price, capacity, schedule, guide codes) — the legitimate round-trip is preserved; any text change → `422 content_requires_revision`; any membership change or reorder → `422 option_structure_change_not_supported`.

---

## 6. State machines

**6.1 Revision.** `draft → proposed → approved | rejected`; `draft/proposed → withdrawn`; `draft/proposed → superseded`.

| Action | Preconditions | Result |
|---|---|---|
| create (partner) | tour owned; `base_revision_id == tours.published_revision_id`; `publication_epoch == tours.publication_epoch`; no active candidate unless `supersedes_revision_id` names it | `draft`, 201; else 409 `REVISION_SUPERSEDED` / 409 `REVISION_ALREADY_OPEN` (+`revision_id`) / 422 bounds |
| create (admin correction) | admin holds `correct_tour_content`; tour `published`; reason + declared refs; changed paths ⊆ declared refs | `proposed` (submitted immediately), 201, audit `tour.revision.correction_proposed`; never approves or publishes |
| submit (partner) | author is the partner; base still current; publication epoch current; merged canonical title+description non-blank | `proposed`, 200, audit `tour.revision.submitted` |
| approve (admin) | admin holds `manage_tours`; state `proposed`; base == published revision; publication epoch current | `approved`; materializes (9.3); advances the pointer; audit `tour.revision.approved` |
| reject (admin) | admin holds `manage_tours`; reason required ≤500 | `rejected` (terminal); audit; linked request → `rejected` |
| withdraw (partner) | author is the partner | `withdrawn` (terminal); audit |
| supersede | (a) replaced by a new candidate, (b) the base revision advanced, (c) the publication epoch advanced, (d) another candidate was approved | `superseded` (terminal); audit |

**One active candidate per tour** = at most one revision in `draft` or `proposed`. **Terminal replay**: submit/approve/reject/withdraw on a terminal revision → 409 `REVISION_TERMINAL`, audited as a refusal. **Double-approve of the same candidate**: the second attempt sees `approved` → 409 `REVISION_TERMINAL`, never a second approval. **Superseded vs. current**: only a candidate whose `base_revision_id` equals the current `published_revision_id` **and** whose `publication_epoch` equals the tour's is approvable — at most one candidate satisfies both, so two candidates are never simultaneously approvable.

**Refusal audits and rollback.** A conflict detected inside the governed transaction aborts it (nothing is written). The refusal audit is then written in a **separate transaction after the rollback**, and the domain exception is re-thrown. A refusal audit is never lost with the rolled-back transaction and never claims a state change that did not happen.

**6.2 Request.** `open → answered → approved | rejected`; `open → closed`; `open/answered → superseded`. A request may exist without a candidate (the partner has not answered). A response must link the tour's **current active candidate**; a superseded or foreign candidate → 422 `REVISION_NOT_OWNED` / 409 `REVISION_TERMINAL`. A rejected request is **terminal** — reopening means creating a **new** request explicitly.

**6.3 Field-reference allowlist.** `content.canonical.*` (9), `content.options[*].title`, `content.options[*].pickup_instructions`, `content.media[*].alt_text`, `presentation.*` (9) — correctable **and** requestable. Commercial/operational paths (price, inventory, option structure, category, slug, featured) — neither. Every reference must resolve against the **base snapshot** (the approved revision's payload, or the candidate's payload for a replacement): unresolvable → 422 `unknown_field_reference`; not allowlisted → 422 `field_not_referenceable`. For an admin correction the **changed paths must be a subset of the declared references** (typed diff base ↔ candidate) → otherwise 422 `undeclared_field_change`.

**6.4 Authorization.** Ordinary revision actions keep the existing review authorization (`manage_tours` for admin actions, partner ownership for partner actions). **No four-eyes / separation-of-duties rule is introduced.** The correction action requires a **new** flag `correct_tour_content`, granted **only explicitly** (role template or a direct `admin_permissions.flags` entry); it is **not** added to `operations_admin`, `content_manager`, `translator_editor`, `support_agent`, `media_manager` or `finance_viewer`; `super_admin` holds it only through the pre-existing wildcard. **No automatic grant to ordinary administrators.**

---

## 7. Contracts

**7.1 Partner read.** `GET /api/partner/tours/{id}` keeps its envelope and fields and adds one optional object (null when no governance data exists, so the client keeps its current behaviour):

```jsonc
"content_governance": {
  "source_locale": "es",
  "approved_revision": { "id": 12, "revision_number": 3, "kind": "baseline", "content_hash": "…", "created_at": "…" },
  "active_candidate": { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "proposed",
                        "payload_hash": "…", "submitted_at": "…", "change_summary": "…" },
  "open_requests": [ { "id": 9, "kind": "admin_change_request", "state": "open", "reason": "…",
                       "field_refs": [ { "path": "content.canonical.meeting_point", "note": "…" } ],
                       "created_at": "…" } ] }
```

**7.2 Candidate API** (owner-scoped; cross-partner/unknown → 404; `auth:sanctum` + `partner` + existing throttle).

| Method / path | Contract |
|---|---|
| `GET …/revisions/{revisionId}` | **Full candidate payload** + `{id, revision_number, kind, state, source_locale, content_hash, payload_hash, base_revision_id, publication_epoch, author_type, created_at, submitted_at, decided_at, change_summary, reason, field_refs, diff_vs_base}` — the endpoint the editor uses to restore an unsaved candidate |
| `POST …/revisions` | Body `{ base_revision_id, publication_epoch, change_summary?, translations:{source:{…}}, options:[{id,title,pickup_instructions}], media:[…], presentation:{…} }`; **merge base = the approved revision's payload**; 201 `{data:{…revision…},message}`; 409 `REVISION_SUPERSEDED`/`REVISION_ALREADY_OPEN`; 422 bounds/forbidden paths |
| `PUT …/revisions/{revisionId}` | Body `{ observed_payload_hash, publication_epoch, …patch keys… }`; **merge base = the candidate's stored payload** (not the approved one); `observed_payload_hash` ≠ stored `payload_hash` → 409 `REVISION_CONFLICT` (no write); terminal candidate → 409 `REVISION_TERMINAL`; base no longer current → 409 `REVISION_SUPERSEDED`; 200 `{data:{…revision…}}` |
| `POST …/revisions/{revisionId}/submit` | 200 (state `proposed`); 409 `REVISION_SUPERSEDED`/`REVISION_TERMINAL`; 422 `REVISION_CONTENT_INVALID` (merged canonical title/description blank); 404 |
| `POST …/revisions/{revisionId}/withdraw` | 200; 409; 404 |
| `POST …/revision-requests/{requestId}/respond` | Body `{revision_id, response_note?}` → 200 (state `answered`); 409 `REQUEST_NOT_OPEN`/`REVISION_TERMINAL`; 422 `REVISION_NOT_OWNED`; 404 |
| `GET …/revisions`, `GET …/revision-requests` | `{data:[…],meta:{…}}` |

**Conflict UX.** A 409 `REVISION_CONFLICT` / `REVISION_SUPERSEDED` / `REVISION_ALREADY_OPEN` returns the conflicting revision id; the client reloads it through the full-payload endpoint, shows the difference and asks the partner to re-apply or discard. Nothing is auto-merged.
**New error codes** (on the existing `{"message":…}` / `{"message":…,"errors":{…}}` shapes): `REVISION_SUPERSEDED`, `REVISION_ALREADY_OPEN`, `REVISION_CONFLICT`, `REVISION_TERMINAL`, `REVISION_CONTENT_INVALID`, `REVISION_NOT_OWNED`, `REQUEST_NOT_OPEN`, `unknown_field_reference`, `field_not_referenceable`, `undeclared_field_change`, `content_requires_revision`, `option_structure_change_not_supported`.

**7.3 Admin domain Actions** (no admin REST endpoint is added; admin surfaces are Filament actions over these Actions).

| Action | Signature | Refusals |
|---|---|---|
| `RequestTourChangesAction` | `execute(User $actor, int $tourId, string $reason, array $fieldRefs): TourRevisionRequest` | 403 (no `manage_tours`), 404, 422 (reason blank/>500; no refs; non-allowlisted; unresolvable), 409 (identical open request exists) |
| `ProposeTourCorrectionAction` | `execute(User $actor, int $tourId, string $reason, array $fieldRefs, array $patch): TourContentRevision` | 403 (no `correct_tour_content`), 404, 409 (tour not `published` / an active candidate exists), 422 (reason, refs, `undeclared_field_change`, bounds, `option_structure_change_not_supported`) |
| `ApproveTourRevisionAction` | `execute(User $actor, int $tourId, int $revisionId): Tour` | 403, 404, 409 (`REVISION_SUPERSEDED`/`REVISION_TERMINAL`), 422 (merged content invalid) |
| `RejectTourRevisionAction` | `execute(User $actor, int $tourId, int $revisionId, string $reason): TourContentRevision` | 403, 404, 409 (`REVISION_TERMINAL`), 422 (reason blank/>500) |
| `RespondToTourRevisionRequestAction` | `execute(User $actor, int $tourId, int $requestId, int $revisionId, ?string $responseNote): TourRevisionRequest` | 403 (not the owner), 404, 409, 422 |

Each re-authorizes the **passed** actor against persisted flags. The admin review view loads the **exact** candidate (full payload + diff vs. the base approved revision); approval binds to that exact `revisionId`, never to "the latest candidate".
**Audit events.** `tour.revision.submitted`, `.change_requested`, `.correction_proposed`, `.approved`, `.rejected`, `.withdrawn`, `.superseded`, `.responded`, plus refusal audits `tour.revision.approval_refused` / `.supersession_refused` written after rollback. Actor, action, target, timestamp and before/after are recorded; content payloads are **not** copied into audit rows.

---

## 8. Read coherence, cache versioning, indexing

**8.1 One coherent read boundary.** The public detail read runs in a transaction: (1) `SELECT … FROM tours WHERE slug=? FOR SHARE` — a **shared lock on the tour row**; (2) read `content_version` from that row; (3) eager-load translations, states, media, options, category, availability, partner inside the same transaction; (4) build the payload; (5) `Cache::put("tour.{slug}.{locale}.{content_version}", $payload, 900)`; (6) commit. The approval takes `FOR UPDATE` on the same tour row (`lockTourForMutation()`), so **a reader's multi-query read cannot straddle an approval** — the approver cannot commit while the reader holds the shared lock. The lock order is unchanged: the tour row is already the first lock in `withContentLock()`, `lockTourForMutation()` and the booking path.

**8.2 Versioned cache keys.** The key becomes `tour.{slug}.{locale}.{content_version}`. `content_version` is bumped **inside the same transaction** as anything that changes the rendered payload: the approval materialization, a derivative becoming current (the completion transaction already holds the tour row), and any partner content/media/option write. A pre-commit fill lands under a version that is never used again → it can never be served. The existing pre-commit `Cache::forget` calls remain as hygiene for the legacy unversioned key (nothing reads it afterwards; it expires within its TTL).

**8.3 Indexing.** The approval dispatches `IndexTourAction::dispatch($tourId)->afterCommit()` explicitly, so the final index state is the approved revision (FIFO: this push follows the model-event push). A **candidate is never materialized and therefore never indexed**. The index may still serve the previous approved projection for the queue delay — the pre-existing eventual-consistency property of an async index, **not** changed or promised away here.

**8.4 Scope.** The guarantee covers the revision-governed content and presentation facts: a public read never mixes two approved revisions. Live commercial values (price, option price/capacity, availability) keep their existing behaviour, including the pre-existing list/search/homepage cache TTLs (300–1800 s) the tour hooks never evicted.

---

## 9. Generation epoch, job fencing, legacy engine retirement

**9.1 Rules.** `tours.generation_epoch` starts at 0 and increments **only** when the canonical source hash changes (a partner save on a non-published tour, or an approval that changes the canonical text), in the same transaction as the derivative-state re-arm. `tour_translation_states.generation_epoch` is stamped with the tour's epoch; the migration adds it `NOT NULL DEFAULT 0`, so existing rows start at 0. `currentTranslation()` additionally requires `state.generation_epoch === tour.generation_epoch`.

**9.2 Consequences.** **A‑B‑A**: the third content state has a new epoch, so the earlier A-derivative is not current (epoch mismatch) → regenerated; the hash alone would not have caught it. **Media-only approval**: the canonical hash is unchanged → the generation epoch is **not** bumped (no regeneration) while the content version **is** (the gallery changed). **Legacy null-identity jobs**: a job dispatched before this change carries no epoch; the completion check requires `tour.generation_epoch === job.epoch` and `null !== 0` → the legacy job **cannot write** once the migration has landed. **Old draft jobs crossing first publication**: publication does not change the canonical content and therefore does **not** bump the generation epoch → an in-flight job for the same content stays valid and may complete; a job whose epoch is older is refused.

**9.3 Job contract.** `GenerateTourTranslationJob(tourId, locale, sourceHash, generationEpoch)`. Before any write, under the tour `FOR UPDATE` lock: the tour exists; the locale is a derivative locale; the fresh source hash equals `sourceHash`; `state.source_hash === sourceHash`; `state.generation_epoch === generationEpoch`; `tour.generation_epoch === generationEpoch`; the derivative is not already current. `planSourceDispatches()` carries the epoch in the plan tuple; `dispatchPlannedTranslations()` pushes after commit; `reconcileTour()` re-arms only rows whose epoch matches. The completion transaction bumps `content_version` so the public cache key changes when a derivative becomes current.

**9.4 Legacy field engine retirement.** `Tour::supportsFieldTranslation()` returns `false` for every tour → the engine never touches tour content; the three Filament header actions that call it are removed and replaced by a read-only derivative status plus a "Regenerate derivatives" action dispatching the revision-bound job. `TranslateContentFieldJob` gains a **completion-time** check — inside its write transaction it re-reads the tour under a lock and refuses to write when `supportsFieldTranslation()` is false or the source hash moved (today the check happens only before provider I/O). **Runbook:** before the release that retires the engine, the queue is **drained** (workers stopped, the queue allowed to empty or the 3-attempt/backoff window to expire) and the workers are restarted on the new build; no legacy job may be in flight across the switch.

---

## 10. Admin (Filament) and frontend contracts

**10.1 Filament.** The `translations` repeater is **removed** → no Filament surface writes a canonical or derivative translation row in any locale. For a **published** tour the remaining form is read-only; the dedicated actions remain: publish / reject / unpublish / archive (EGM-002 unchanged), **Request changes**, **Propose correction**, **Approve revision**, **Reject revision**, **Regenerate derivatives**, plus a read-only **Revisions** section (number, kind, state, author, timestamps, reason, declared refs, hashes) and an **open change request** block. The infolist shows the source and each derivative locale read-only with its actual language and readiness badge. Actions stay thin — one domain Action each. The web demo generator stays refused (it writes published content outside the governed workflow).

**10.2 Frontend (partner).** The editor loads the tour; when the tour is `published` and a governed change is detected, **Save** becomes **Propose changes** and targets `POST/PUT …/revisions`; operational-only edits keep using `PUT …/tours/{id}`. The editor restores an unsaved candidate through `GET …/revisions/{id}` (full payload) and sends `observed_payload_hash` on replacement; a 409 `REVISION_CONFLICT` shows a reload-and-reapply prompt (no silent merge). The partner sees open change requests with reason and referenced fields and can respond. The `translations` array/object type mismatch is resolved by new revision types in `frontend/src/lib/api/types.ts`, and the page-private `Tour` duplicate is replaced by `frontend/src/types/partner.ts`. New interface copy ships in `en`, `es`, `it` (`frontend/messages/*.json`).

---

## 11. Activation, baseline, reconciliation, rollback

**11.1 One coordinated activation (no window).** A single release contains the migration (tables, `tours` columns, the state column, the triggers), the backend services/actions/API, the Filament changes, the generation-epoch binding and the frontend changes. Because the **triggers land with the migration**, the moment the migration is applied every unflagged write to a governed field of a published tour is refused by PostgreSQL — including writes from a still-running old build. There is no interval in which a legacy writer can change published content.

**11.2 Baseline capture (faithful, locked, idempotent).** A bounded, resumable, idempotent command (`--dry-run`, `--after-id`, `--limit`) captures, for every published tour without a `published_revision_id`, a `baseline` revision whose payload is the **verbatim** live content (canonical source row, active option texts, media rows, presentation facts), under the tour `FOR UPDATE` lock. It never normalizes, repairs or invents content; it stores the content hash from the existing algorithm; it is **not** re-validated (a baseline records what exists). A **lazy** capture happens under the same lock when a correction or approval needs a base and none exists. A candidate merged onto a baseline must satisfy the existing bounds at submit time — legacy content that violates a bound must be corrected by the partner in that candidate; no invented content is introduced.

**11.3 Pointer / epoch reconciliation.**

| Event | Effect |
|---|---|
| First publication (`ApproveTourAction`) | After the existing guarded publish — all EGM-002 checks, the `published_at` stamp and the `tour.publish` audit **unchanged** — the action **additively** creates the approved revision snapshot of the published content, sets `published_revision_id`, increments `publication_epoch` and `content_version`, and re-arms existing derivative states for the current generation epoch. |
| Unpublish (`UnpublishTourAction`) | Increments `publication_epoch` → active candidates become `superseded` (audited). The tour is now a draft; the partner edits it on the existing contract. |
| Edit while unpublished | The draft path writes live content; `published_revision_id` still names the last approved snapshot, so a candidate created from it is based on the last **approved** content, not the unpublished draft. The partner submits the draft for review again; the next approval creates a **new** approved revision, advances the pointer and supersedes older candidates. |
| Republish | A new approved revision snapshot + pointer advance + `publication_epoch` increment; candidates based on the previous pointer are `superseded`. |
| Legacy write to a published tour's governed field | Refused by the trigger → the pointer can never drift. |

**11.4 Rollback.** **Frontend**: an old client PUTs published content → the backend refuses (`422 content_requires_revision`) → the old client surfaces the error; it **cannot** save published content (fail closed) and operational edits still work; no data is deleted. **Backend**: the triggers remain → published content stays protected; the new tables/columns are additive and ignored by the old build; operational writes still work. The migration may be rolled back only while no approved revision exists; afterwards the columns/tables are kept (additive), as the Constitution requires for destructive cleanup. The demo generator stays refused; restoring it needs a separately approved workflow.

---

## 12. Acceptance criteria (objective; existing identifiers not renumbered)

| AC | Statement | Traces to |
|---|---|---|
| AC-001 | Unflagged writes to a published tour's governed fields are refused **by the database**, including from a pre-existing build. | 019 FR-031/037; 018 FR-034 |
| AC-002 | The general Filament form exposes no translation field and no writable governed field for a published tour; a crafted `translations[...]` write changes no row. | 019 FR-031/037; 018 FR-022/034 |
| AC-003 | A published-tour `PUT` changing a governed field → `422 content_requires_revision` with exact paths and **zero writes** (governed and operational). | 019 FR-031/040 |
| AC-004 | A `PUT` round-tripping **unchanged option text** with changed commercial values applies the commercial change; every other option column stays byte-identical. | 019 FR-001/027 |
| AC-005 | An approval changes only `tour_options.title`/`pickup_instructions` of the listed options; all other option columns (active or retired) are byte-identical. | 019 FR-033 |
| AC-006 | A candidate whose option set differs from the tour's active ids/order → `422 option_structure_change_not_supported`; no option created, retired, reactivated or reordered. | 019 FR-033 |
| AC-007 | A candidate's stored `payload_hash` equals the hash recomputed from the payload; an `observed_payload_hash` mismatch → 409 `REVISION_CONFLICT`, no write. | 019 FR-032 |
| AC-008 | `GET …/revisions/{id}` returns the complete candidate payload; edit → restore round-trips every field, list order and day/stop. | 019 FR-032/036 |
| AC-009 | A replacement merges onto the **candidate's** payload, not the latest approved revision; a patch authored against the approved base is refused, never silently applied. | 019 FR-032 |
| AC-010 | Approval binds to the exact submitted revision id; another id changes nothing; the admin sees that exact candidate plus its diff. | 019 FR-035 |
| AC-011 | Real PostgreSQL: concurrent approvals of the **same** candidate → one approval + `409 REVISION_TERMINAL`; of **different** candidates on one base → one approval + `409 REVISION_SUPERSEDED`; no partial content. | 019 FR-035; SC-010 |
| AC-012 | A candidate whose base revision or publication epoch advanced is refused at submit and approve with `409 REVISION_SUPERSEDED` and marked `superseded` with an audit row. | 019 FR-035 |
| AC-013 | Every refusal writes a refusal audit **outside** the aborted transaction and claims no state change. | 019 FR-038 |
| AC-014 | Requests and corrections carry a reason non-blank after trim and ≤500 plus ≥1 allowlisted resolvable reference; blank/over-long reason, missing, non-allowlisted and unresolvable references are refused with distinct codes. | 019 FR-036 |
| AC-015 | A correction whose changed paths are not a subset of its declared references → `422 undeclared_field_change`; a subset → accepted. | 019 FR-038 |
| AC-016 | The correction action refuses an actor without `correct_tour_content`; only the `super_admin` wildcard carries it by default; it changes no lifecycle field, approval state or live content row. | 019 FR-038/039 |
| AC-017 | No administrator surface writes a derivative row (repeater gone, legacy engine inapplicable, trigger refuses a crafted write). | 019 FR-037 |
| AC-018 | A generation job with a null (legacy) or older epoch writes nothing; a matching epoch writes the derivative and bumps `content_version`. | 019 FR-011/012 |
| AC-019 | After A→B→A the earlier A-derivative is not current; after a media-only approval the epoch is unchanged, the content version increments and no regeneration is dispatched. | 019 FR-010/012 |
| AC-020 | A pre-publication job for unchanged content completes after first publication; a job for changed content does not. | 019 FR-012 |
| AC-021 | A detail read starting before an approval never returns a payload mixing two revisions (shared tour-row lock excludes the approver's commit). | 019 FR-040; 020 FR-035 |
| AC-022 | A cache entry filled before an approval is never served after it; the post-approval read returns the new payload. | 019 FR-040; 020 FR-035 |
| AC-023 | A candidate never appears in the public payload, the search index, a derivative row/state or the provider request. | 019 FR-037/040 |
| AC-024 | An approval materializes canonical source, option texts, media and presentation facts in **one** transaction, so gallery, itinerary, option text and body describe the same approved revision. | 019 FR-040; 020 FR-035 |
| AC-025 | No revision writes a booking row; bookings keep their option title, pickup, content locale, start time and amounts across the whole cycle. | 019 FR-040 |
| AC-026 | The public tour-detail shape, field names and status codes (200/404/410/429) are unchanged; the existing detail contract tests pass unchanged. | 019 FR-027; 018 FR-002/033; 020 FR-001 |
| AC-027 | The baseline capture is verbatim, idempotent, resumable; `--dry-run` writes nothing; no legacy content is normalized into invented approved content. | 019 FR-027/032 |
| AC-028 | First publication, unpublish and republish leave pointer and epochs consistent and supersede the candidates they invalidate. | 019 FR-035 |
| AC-029 | A tour becomes `published` only through `ApproveTourAction`; no revision, request or correction changes `status` or `published_at`. | 019 FR-039; 018 FR-025 |
| AC-030 | The browser flow covers propose → submit → change request → response → approval plus reload, rejection and supersession; the public page is unchanged until approval and the panel is Axe-clean at the acceptance widths. | 019 FR-030/036; 020 FR-029/030 |

---

## 13. Tests, ownership, commands

**13.1 Backend** (Pest, real PostgreSQL; `backend/tests/Support/TourApprovalWorker.php` + `pg_blocking_pids` for the races). All paths under `backend/tests/Feature/`.

| File (new unless noted) | Covers | ACs |
|---|---|---|
| `Partner/TourRevisionTest.php` | create/merge/replace, payload tiers, payload hash, observed-hash conflict, option structure refusal, transitions, terminal replay | 004–010, 014, 015 |
| `Partner/TourRevisionConcurrencyTest.php` | real races: double-approve same candidate, competing candidates, approval racing a partner write | 011, 012 |
| `Partner/TourRevisionBackfillTest.php` | faithful capture, idempotence, resumability, dry-run, lazy capture | 027, 028 |
| `Partner/TourRevisionEnforcementTest.php` | published-tour `PUT` refusal + atomicity, unchanged-text commercial round-trip, trigger-level refusal of raw SQL writes | 001, 003, 004, 006 |
| `Partner/TourGenerationEpochTest.php` | epoch rules, A‑B‑A, media-only approval, null-epoch legacy job, pre-publication job, reconcile | 018–020 |
| `Admin/TourRevisionGovernanceTest.php` | request/correction/approve/reject actions, permission, refs, `undeclared_field_change`, audit rows incl. refusals after rollback | 013–016, 029 |
| `Admin/Filament/TourRevisionActionsTest.php` (Livewire) | read-only form for a published tour, no repeater, the new actions, revision history rendering | 002, 010, 016 |
| `Admin/Filament/TourTranslationWriteGuardTest.php` (Livewire) | a crafted Filament `translations[...]` write changes no row; the legacy field-engine actions are gone | 002, 017 |
| `Search/TourRevisionPublicProjectionTest.php` | public payload identity while a candidate exists, read/cache-fill race vs. approval, versioned cache, a candidate is never indexed | 021–024, 026 |
| `Booking/TourRevisionBookingSnapshotTest.php` | no revision writes `bookings`; snapshots survive the whole cycle | 025 |

**13.2 Frontend.** `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx` (new): candidate state, change request with reason + refs, response action, conflict prompt. `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx` (extend): published tour → propose-changes path; operational-only edit → existing `PUT`; full-payload restore. `frontend/tests/e2e/partner/tour-revision.spec.ts` (new; runs in `chromium-partner` + `mobile-partner` via `PARTNER_AUTHED`): partner proposes → submits → admin change request → partner responds → admin approves → the public page only then changes; plus reload, rejection and supersession. `frontend/tests/e2e/a11y/partner-a11y.spec.ts` (extend; the **only** file the `a11y-partner` project matches): Axe on the revision panel at 390/768/1024/1440, keyboard operability, announced errors. Auth: the partner specs reuse `tests/.auth/partner.json` from the `setup` project (`tests/e2e/partner.setup.ts`); the admin side of the browser flow uses the existing Filament login pattern (`tests/e2e/filament-admin.spec.ts`).

**13.3 Commands (all exist in the repository).**

```sh
cd backend
php artisan test --compact tests/Feature/Partner tests/Feature/Booking tests/Feature/Search tests/Feature/Payment
php vendor/bin/pest tests/Feature/Partner/TourRevisionTest.php --compact --do-not-cache-result
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

cd frontend
npm run lint
npm run typecheck
npm test -- --runInBand
node node_modules/@playwright/test/cli.js test tests/e2e/partner/tour-revision.spec.ts --project=chromium-partner --project=mobile-partner --no-deps --reporter=line
node node_modules/@playwright/test/cli.js test tests/e2e/a11y/partner-a11y.spec.ts --project=a11y-partner --no-deps --reporter=line
npm run build
```

Playwright base URL and container mode follow `frontend/playwright.config.ts` (`DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx`, as in the EGM-002 follow-up).

---

## 14. Implementation slices

| # | Slice | Intended files | Gate |
|---|---|---|---|
| S1 | Schema, models, triggers, baseline command | `backend/database/migrations/2026_11_14_00000{1,2}_*.php`, `backend/app/Models/{TourContentRevision,TourRevisionRequest,Tour,TourTranslationState}.php`, `backend/app/Console/Commands/BackfillTourContentRevisions.php`, `backend/tests/Feature/Partner/TourRevisionBackfillTest.php` | `php artisan test tests/Feature/Partner/TourRevisionBackfillTest.php`, Pint, PHPStan |
| S2 | Revision domain service + option text materializer | `backend/app/Domains/Partner/Services/{TourRevisionService,TourOptionTextMaterializer,TourTranslationService}.php`, `backend/app/Domains/Partner/Requests/TourRevisionPayloadRules.php`, `backend/tests/Feature/Partner/TourRevisionTest.php` | `php artisan test tests/Feature/Partner`, Pint, PHPStan |
| S3 | Partner API + published-tour routing | `backend/routes/api/partner.php`, `backend/app/Domains/Partner/Controllers/TourRevisionController.php`, `backend/app/Domains/Partner/Requests/{Create,Update,Respond}TourRevisionRequest.php`, `…/UpdateTourRequest.php` (classifier), `…/Services/TourService.php` (governance projection), `backend/tests/Feature/Partner/TourRevisionEnforcementTest.php` | `php artisan test tests/Feature/Partner`, Pint, PHPStan |
| S4 | Admin actions, permission, publication maintenance | `backend/app/Domains/Admin/Actions/{RequestTourChangesAction,ProposeTourCorrectionAction,ApproveTourRevisionAction,RejectTourRevisionAction,ApproveTourAction,UnpublishTourAction}.php`, `…/Services/AdminAuthorizationService.php` (`correct_tour_content`), `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php` | `php artisan test tests/Feature/Admin`, Pint, PHPStan |
| S5 | Filament read-only + dedicated actions; legacy engine retirement | `backend/app/Filament/Resources/TourResource.php`, `…/Pages/EditTour.php`, `backend/app/Models/Tour.php` (`supportsFieldTranslation` → false), `backend/app/Domains/Translation/{Services/AutoTranslationService,Jobs/TranslateContentFieldJob}.php`, `backend/tests/Feature/Admin/Filament/{TourRevisionActionsTest,TourTranslationWriteGuardTest}.php` | `php artisan test tests/Feature/Admin/Filament`, Pint, PHPStan |
| S6 | Generation epoch, read coherence, cache versioning | `backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php`, `…/Services/TourTranslationService.php`, `backend/app/Domains/Search/Controllers/Public/TourDetailController.php`, `backend/app/Domains/Search/Actions/GetTourDetailAction.php`, `backend/tests/Feature/Partner/TourGenerationEpochTest.php`, `backend/tests/Feature/Search/TourRevisionPublicProjectionTest.php` | `php artisan test tests/Feature/Partner tests/Feature/Search`, Pint, PHPStan |
| S7 | Frontend partner surfaces | `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx`, `frontend/src/components/partner/tours/TourRevisionPanel.tsx` (new), `frontend/src/lib/api/{partner,types}.ts`, `frontend/src/types/partner.ts`, `frontend/messages/{en,es,it}.json`, `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx` (new), the edit-page test | `npm run lint`, `npm run typecheck`, `npm test -- --runInBand`, `npm run build` |
| S8 | Browser acceptance | `frontend/tests/e2e/partner/tour-revision.spec.ts` (new), `frontend/tests/e2e/a11y/partner-a11y.spec.ts` (extend) | the two Playwright commands in §13.3 |

Order: S1 → S2 → (S3, S4) → (S5, S6) → S7 → S8. S1's triggers make the published-content surface fail-closed as soon as the migration is applied, so S2–S4 must land before any partner or admin surface is used against a published tour.

---

## 15. Open items

**No blocking owner question remains.** Every technical decision the owner policy left open is closed above as an implementation choice, with the reason recorded in place (4.1 field classes, 4.3 atomic routing, 5.3 option scope, 6.4 permission and the absence of a four-eyes rule, 8.4 coherence scope, 11.3 reconciliation). One policy item is stated for the record rather than decided here: whether a correction may be authored by an administrator who is not the tour's partner — ADR 0024 permits a dedicated correction action, and this design keeps it permission-gated and grants it to no default role.

**Out of scope** (unchanged): A1 navigation, A3 departures/shared capacity, A5 booking/voucher completion, Phase B, Phase C, the legacy demo generator, and any new public or admin REST endpoint.

---

## 16. Files changed by this dispatch

`docs/designs/supplier-portal-egm003-revision-governance.md` (**rewritten**, v2) · `docs/designs/supplier-portal-egm003-revision-governance-v1.md` (**new** — v1 archive, failed readiness, not canonical; byte-identical copy of the pre-rewrite target, verified before the archive status banner was added; SHA-256 of the archived bytes before the banner: `81900b177d928cc7693abb1d349df73de33acd9b63b67ac5f345ad1dd4ef7367`) · `specs/019-tour-content-data/{spec.md,data-model.md,contracts/partner-tour-content-api.md,contracts/translation-generation.md}` (requirements, model and contracts aligned to v2) · `specs/020-tour-detail-experience/spec.md` (FR-035, SC-010) · `specs/018-bookly-ui-redesign/spec.md` (FR-034, SC-012) · `docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md` (decision + v2 pointer) · `docs/reports/supplier-portal-v3-goldfish-review.md` (dated decision + v2 pointer; **EGM-003 not closed**).

Not edited (parent-owned): `docs/reports/supplier-portal-egm003-design-review.md`, `docs/adr/0024-tour-content-governance.md`. No code, migration, schema, test or configuration file was created or modified. Nothing was committed.
