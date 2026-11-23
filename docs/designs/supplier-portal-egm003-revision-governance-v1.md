> **ARCHIVED — FAILED READINESS, NOT CANONICAL.**
> This is the **first draft** of the EGM-003 revision-governance design. It was **rejected by the independent fresh-context Goldfish design review on 2026-11-14** (see `docs/reports/supplier-portal-egm003-design-review.md`) and must not be used as the implementation basis.
> The canonical design is **[`supplier-portal-egm003-revision-governance.md`](supplier-portal-egm003-revision-governance.md) (v2)**, which resolves every review finding.
> This file is preserved **unchanged** as historical evidence of the reviewed draft. Its SHA-256 before this banner was added was `81900b177d928cc7693abb1d349df73de33acd9b63b67ac5f345ad1dd4ef7367` (83,217 bytes).

# EGM-003 Phase 1 — Tour Content Revision Governance (Design Only)

**Status** Design only. No schema, migration, production code, test code, or Filament change was written by this dispatch. Nothing was committed. No git state was reset, stashed, cleaned, restored, or switched.
**Date** 2026-11-14
**Baseline** `f07d3d0` (working copy clean at dispatch start)
**Governing constitution** [Bookly Constitution v3.0.0](../../.specify/memory/constitution.md) (last amended 2026-11-10, EGM-001 Option B)
**Supersedes** the earlier EGM-003 A1-navigation task (the owner replaced it). **No navigation work is included.**
**Related** [Supplier Portal plan](../superpowers/plans/2026-11-10-bookly-supplier-portal.md) · [Goldfish review](../reports/supplier-portal-v3-goldfish-review.md) · [EGM-002 investigation](../reports/supplier-portal-egm002-investigation.md) · [Spec 019](../../specs/019-tour-content-data/spec.md) · [Spec 020](../../specs/020-tour-detail-experience/spec.md) · [Spec 018](../../specs/018-bookly-ui-redesign/spec.md) · [ADR 0024 — Tour content and translation governance](../adr/0024-tour-content-governance.md) (a separate, concurrently authored owner-policy record; this design is the technical elaboration of the same decision and does not restate or supersede it)

**Dispatch scope** Inventory the real repository, propose the smallest compatible immutable published-revision design under the owner's 2026-11-14 decision, define contracts/tests/slices, and synchronize the affected active documents. **EGM-003 remains open** — this document is the design input for a separate independent Goldfish design review, then implementation.

---

## 1. Owner policy of record (2026-11-14)

These are **owner decisions**, not implementation choices. They are stated here verbatim in substance and are treated as binding constraints on everything below.

| # | Owner policy | Binding consequence |
|---|---|---|
| P1 | Partners own and edit canonical tour content. | Canonical source content is partner-authored. Admins do not author it through ordinary surfaces. |
| P2 | Admins request changes with a mandatory reason and specific fields. | An admin change request must carry a non-empty reason and at least one field reference from a platform allowlist. |
| P3 | Gemini derivatives are never manually edited. | No admin or partner surface may write a derivative locale row. Derivatives are only produced by the generation job. |
| P4 | Published edits create a reviewable separate revision; the approved public version stays unchanged. | A published tour's traveler-facing content changes only through an approved revision. A pending revision must not alter any public projection. |
| P5 | Exceptional admin corrections happen ONLY through a dedicated, authorized, audited domain Action — never through the general Filament form. | A correction is a first-class domain action with its own permission and audit trail, not a form field. |

**Chosen correction semantics (owner, 2026-11-14).**

| Clause | Meaning as used in this design |
|---|---|
| Ordinary revision review is mandatory. | Every content change on a published tour — partner edit or admin correction — is reviewed and approved through the same ordinary revision review. There is no review-free content path. |
| No emergency publishing path. | Nothing in this design can publish a tour or make content public without the ordinary review. The existing EGM-002 approval guard remains the only publication path. |
| The correction action cannot approve or publish. | The correction action only *proposes* a candidate revision. It cannot approve it, publish the tour, or change tour lifecycle state. |
| The existing EGM-002 approval guard remains authoritative. | `ApproveTourAction` + `TourPublicationGuardService` are unchanged and remain the only way a tour becomes `published`. |

**No new locale policy.** The source locale stays fixed (`en`/`es`/`it`, one per tour). Derivatives are generated asynchronously by Gemini from the current revision, never block an eligible publication, and fall back to the canonical source with a localized notice. Live-guide/spoken languages stay independent of content locales.

### 1.1 Owner policy vs. implementation choice

Everything in §4–§12 that is not listed in the table above is an **implementation choice made by this design**, justified by least privilege and by the smallest compatible change. Those choices are marked **[IC]** and are reversible ordinary design decisions, except where §13 records a question that only the owner can settle.

---

## 2. Method and sources actually read

| Source | What was taken from it |
|---|---|
| `.specify/memory/constitution.md` (v3.0.0, full file incl. Sync Impact Report) | §II publishing/review integrity, §IV API-first + thin Filament actions, §VII evidence, Security § audit mandate, "Tour Content Source and Derived Locales", Delivery Workflow §Implementation/Verification/Deployment, Governance versioning |
| `docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md` (full) | Phase A scope, implemented vs. remaining columns, A4 review/publication gap list, data & booking model, verification commands, Constitution Check scope |
| `docs/reports/supplier-portal-v3-goldfish-review.md` (full) | EGM-002 closure and its boundaries, EGM-003 finding text, A1–A5 gap matrix, dated evidence, "EGM-003 stays open" wording |
| `docs/reports/supplier-portal-egm002-investigation.md` (full) | Runtime entry-point inventory pattern, expected-security test style, lock order, known B1–B8 gaps |
| `goldfish_review_report.md` | EGM-001 resolution, Option B adopted wording, sync obligations on Specs 018–020 |
| `specs/018/spec.md`, `specs/019/spec.md` (+ `data-model.md`, `contracts/*`), `specs/020/spec.md` | FR/SC numbering, existing contracts, data model, acceptance wording |
| Backend: `Tour`, `TourTranslation`, `TourTranslationState`, `TourOption`, `TourOptionTranslation`, `TourService`, `TourTranslationService`, `GenerateTourTranslationJob`, `GetTourDetailAction`, `TourController`, `TourContentRules`, `StoreTourRequest`, `UpdateTourRequest`, `ApproveTourAction`, `TourPublicationGuardService`, `Reject/Unpublish/ArchiveTourAction`, `GovernanceAuditService`, `GovernanceAuditLog`, `AdminAuthorizationService`, `Admin\Policies\TourPolicy`, `AdminRole`, `TourResource`, `CreateTour`, `EditTour`, `ListTours`, `AutoTranslationService`, `PartnerRoleMiddleware`, `AppServiceProvider` (policies + morph map) | Actual runtime behaviour and every writer of tour content |
| Backend migrations (all `tours`, `tour_translations`, `tour_translation_states`, `tour_options`, `tour_option_translations`, `tour_media`, `tour_drafts`, `content_revisions`, `admin_permissions`, `admin_roles`) | Actual schema; confirmation that **no immutable tour content revision table exists** |
| `routes/api/partner.php`, `routes/api/public.php`, `routes/api/admin.php` | Surface inventory (no admin REST tour endpoints) |
| Frontend: partner tour edit page, `TourWizard`, `tourWizard` store, `lib/api/partner.ts`, `lib/api/types.ts`, `types/partner.ts`, `types/tour.ts`, `TourList`, `TourCard`, `TourContentPreview`, `TourDetail`, `detail-presentation.ts`, `messages/{en,es,it}.json`, Jest + Playwright suites, `package.json` scripts | Existing partner/admin surfaces and citable gate commands |

**Not read:** any `.env`, secret, or credential file. No provider key was inspected.

---

## 3. Actual inventory (facts, with references)

Everything in this section was read in the working copy at `f07d3d0`.

### 3.1 Content storage

| Fact | Reference |
|---|---|
| A tour has no title column; titles live in `tour_translations`, unique `(tour_id, locale)` | `backend/app/Models/TourTranslation.php`, `backend/database/migrations/2026_05_01_000003_create_tour_translations_table.php` |
| Canonical source locale is fixed per tour in `tours.source_locale` (default `en`), never updated after creation | `backend/app/Models/Tour.php:639-646`, `backend/database/migrations/2026_11_10_000001_add_source_locale_to_tours_table.php` |
| Authored source fields are exactly 9: `title, description, highlights, inclusions, exclusions, meeting_point, cancellation_policy, itinerary, important_information` | `backend/app/Domains/Partner/Requests/TourContentRules.php:21-31` |
| Option authored text (`title`, `pickup_instructions`) lives on `tour_options`; derivative option text lives in `tour_option_translations` unique `(tour_option_id, locale)` | `backend/app/Models/TourOption.php:19-25`, `backend/database/migrations/2026_11_10_000003_add_option_sort_order_and_option_translations.php` |
| Media rows live in `tour_media` with `type`, `url`, `alt_text`, `sort_order`; the legacy `tours.cover_image_url` is only a fallback | `backend/database/migrations/2026_06_02_100004_create_tour_media_table.php`, `2026_10_02_000001_add_alt_text_to_tour_media_table.php`, `backend/app/Models/Tour.php:369-429` |
| Partner draft autosaves are opaque payloads in `tour_drafts`, distinct from materialized source | `backend/app/Models/TourDraft.php`, `backend/app/Domains/Partner/Services/TourService.php:549-573` |
| `tours` columns include `status, submitted_at, published_at, archived_at, difficulty_level, guide_languages, source_locale`; `submitted_at`/`archived_at` are **not** in `Tour::$fillable` | `backend/database/migrations/2026_06_02_100008_add_partner_columns_to_tours_table.php`, `backend/app/Models/Tour.php:125-145` |

### 3.2 Revision identity, hashing and locks

| Fact | Reference |
|---|---|
| Revision identity today is a SHA-256 hash of a **normalized canonical projection** (fixed field order, canonicalized itinerary days/stops, option texts keyed by stable option id, only **active** options) | `backend/app/Domains/Partner/Services/TourTranslationService.php:32-50,137-236` |
| The hash deliberately excludes price, capacity, start time, weekdays and guide codes | `backend/app/Domains/Partner/Services/TourTranslationService.php:583-593` (comment), `backend/app/Models/TourOption.php:19-25` |
| Writes and completions share one lock order: **Tour → authored source translation → derivative states in platform locale order** | `backend/app/Domains/Partner/Services/TourTranslationService.php:252-270` |
| Partner create/update/delete and submit all run under that lock; submit additionally refuses `archived`/`published` | `backend/app/Domains/Partner/Services/TourService.php:190-312,499-537` |
| `withContentLock` is the only content lock; there is no revision lock, no revision table, no revision number, no compare-and-set token anywhere in the tour domain | `backend/app/Domains/Partner/Services/TourTranslationService.php:252-270` (whole-file grep for `revision` returns only hash/state prose) |

### 3.3 Translation job and derivative state

| Fact | Reference |
|---|---|
| `tour_translation_states` is unique `(tour_id, locale)` with `source_hash`, `translated_hash`, `status` (`pending|stale|ready|failed`), `last_error_code` | `backend/database/migrations/2026_09_25_000002_create_tour_translation_states_table.php`, `backend/app/Models/TourTranslationState.php` |
| The job carries `(tourId, locale, sourceHash)` only; it re-reads the source row, refuses on hash mismatch, and refuses to overwrite a matching ready derivative | `backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php:45-49,75-90,118-172` |
| A derivative is "current" only when its row exists, state is `ready`, and `translated_hash` equals the freshly recomputed source hash | `backend/app/Domains/Partner/Services/TourTranslationService.php:632-652` |
| `publicStatus` maps an apparently-ready-but-mismatched row to `stale`; the source locale maps to `source` | `backend/app/Domains/Partner/Services/TourTranslationService.php:701-714` |
| Generation is planned inside the source transaction and dispatched **after commit**, one guarded push per locale | `backend/app/Domains/Partner/Services/TourTranslationService.php:286-362` |
| A second, legacy English-only field engine exists (`AutoTranslationService`) with a "manual override" concept; it **skips** tours unless `source_locale === 'en'` **and** no options are configured | `backend/app/Domains/Translation/Services/AutoTranslationService.php:114-128`, `backend/app/Models/Tour.php:287-290` |
| The Filament edit page wires that legacy engine into `beforeSave`/`afterSave` and exposes three header actions that call it | `backend/app/Filament/Resources/TourResource/Pages/EditTour.php:44-99` |

### 3.4 Public, search and SEO projections

| Fact | Reference |
|---|---|
| Public detail reads `tour_translations` + `tour_translation_states` and serves the source or a *current* derivative, with independent itinerary fallback and a truthful `content_locale` / `itinerary_locale` | `backend/app/Domains/Search/Actions/GetTourDetailAction.php:62-96,168-249` |
| SEO metadata is derived from the selected translation (title/description) with canonical + hreflang | `backend/app/Domains/Search/Actions/GetTourDetailAction.php:351-372` |
| The Scout projection fills one slot per content locale using the same current-derivative rule | `backend/app/Models/Tour.php:160-211` |
| Public gallery order/cover/dedup is authoritative in `Tour::allImageUrls()` | `backend/app/Models/Tour.php:369-429` |
| Option text served publicly is the current derivative else the source, with its own `content_locale` | `backend/app/Domains/Partner/Services/TourTranslationService.php:666-699` |

### 3.5 Booking snapshots

| Fact | Reference |
|---|---|
| Booking creation snapshots `tour_option_title`, `tour_option_pickup_instructions`, `tour_option_content_locale` plus start time and amounts; the tour row is locked for update | `backend/app/Domains/Booking/Actions/CreateBookingAction.php:62,105,129,155-157` |
| Bookings therefore keep what the traveler was served, independent of later content changes | `backend/app/Models/TourOption.php:19-25` (model contract), `backend/database/migrations/2026_11_10_000002_create_tour_options_and_booking_option_columns.php` |

### 3.6 Admin surface, authorization, audit

| Fact | Reference |
|---|---|
| The general Filament form has a `translations` **repeater bound to the `translations` relationship**, so it writes `tour_translations` rows directly (all locales, including derivatives) | `backend/app/Filament/Resources/TourResource.php:139-164` |
| `status` is a disabled, non-dehydrated display; lifecycle writes are refused server-side | `backend/app/Filament/Resources/TourResource.php:95-108`, `backend/app/Filament/Resources/TourResource/Pages/EditTour.php:27-42` |
| A temporary EGM-003 guard refuses destructive published-source edits and invalid owner changes, but explicitly preserves "existing canonical and derivative admin editing behavior" | `backend/app/Domains/Admin/Services/TourPublicationGuardService.php:94-130` |
| Publication is exclusively `ApproveTourAction` → `TourPublicationGuardService` (owner locks, actor re-authorization, lifecycle matrix, source title/description, `published_at` stamp, `tour.publish` audit) | `backend/app/Domains/Admin/Actions/ApproveTourAction.php`, `backend/app/Domains/Admin/Services/TourPublicationGuardService.php` |
| Audit is append-only at the model layer, morph-mapped (`tour` is a registered morph alias), and records actor/action/target/before/after/metadata | `backend/app/Domains/Admin/Models/GovernanceAuditLog.php`, `backend/app/Domains/Admin/Services/GovernanceAuditService.php`, `backend/app/Providers/AppServiceProvider.php:103-119` |
| Admin permissions are a JSONB `flags` map plus JSONB role templates; `AdminAuthorizationService::FLAGS` is a **code constant**, so a new flag needs **no migration** | `backend/app/Domains/Admin/Services/AdminAuthorizationService.php:20-42`, `backend/database/migrations/2026_06_20_100002_create_admin_permissions_table.php`, `2026_11_01_000001_create_admin_roles_tables.php` |
| Tour policy gates are all `manage_tours` | `backend/app/Domains/Admin/Policies/TourPolicy.php` |
| An append-only `content_revisions` table **already exists** for `static_page` and `blog_post` (morph `revisionable_type/id`, `version_number`, `snapshot` jsonb, `diff` jsonb, `change_summary`, `actor_id`), with a service that snapshots flat model attributes and a `manage_revisions` policy | `backend/database/migrations/2026_11_01_000004_create_cms_and_revisions_tables.php`, `backend/app/Domains/CMS/Models/ContentRevision.php`, `backend/app/Domains/CMS/Services/ContentRevisionService.php`, `backend/app/Domains/Admin/Policies/ContentRevisionPolicy.php` |
| `static_pages` and `blog_posts` have a `lock_version` optimistic-lock column and a Filament "loaded_lock_version" guard; **`tours` has no `lock_version`** | `2026_11_01_000004_create_cms_and_revisions_tables.php:26-50,73-80`, `backend/app/Filament/Resources/BlogPostResource/Pages/EditBlogPost.php:102-125` |
| The web-triggered demo seeder (which publishes and rewrites tour content) is currently refused with 403 | `backend/app/Filament/Pages/DemoContentManager.php` (EGM-002 follow-up §"Demo generation from the admin page") |

### 3.7 Partner surface

| Fact | Reference |
|---|---|
| Partner tour routes: `GET/POST /api/partner/tours`, `GET/PUT/DELETE /api/partner/tours/{id}`, `POST {id}/drafts/save`, `GET {id}/drafts/latest`, `POST {id}/submit`, `POST {id}/archive`, plus pricing/availability sub-routes | `backend/routes/api/partner.php` |
| `status` is `prohibited` on create and update; `source_locale` is immutable; non-source locales and platform-owned keys are rejected with exact paths | `backend/app/Domains/Partner/Requests/UpdateTourRequest.php:86,105-127`, `TourContentRules::PLATFORM_OWNED_KEYS` / `PROVIDER_STATE_KEYS` / `forbiddenPresentPaths` |
| Partner writes are scoped by ownership: cross-partner and unknown ids are 404 before validation | `backend/app/Domains/Partner/Services/TourService.php:48-56`, `backend/app/Domains/Partner/Middleware/PartnerRoleMiddleware.php` |
| `PUT /api/partner/tours/{id}` today writes canonical source, options, media and operational fields **directly into the live tables** for a published tour | `backend/app/Domains/Partner/Services/TourService.php:184-312` |
| Frontend partner edit page issues a raw `PUT` with `translations: { [sourceLocale]: sourcePayload }` and never sends `source_locale` on update | `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx:546-638` |
| Partner API client wrappers: `getTours`, `getTour`, `createTour`, `updateTour`, `submitTour`, `deleteTour`, `archiveTour`, `saveTourDraft`, `getLatestTourDraft` | `frontend/src/lib/api/partner.ts:35-106` |
| Frontend read shape is `translations: Array<{locale,...}>` while the write shape is `translations: { [locale]: {...} }`; the edit page also keeps a **page-private duplicate** of the `Tour` type | `frontend/src/types/partner.ts:82-141`, `frontend/src/lib/api/types.ts:168-209`, `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx:223-256` |
| There is **no** frontend admin surface for tours (Filament only) and **no** revision/version concept anywhere in the frontend | `frontend/src/app/**` has no admin route group; `frontend/src/components/admin/AdminReviewPanel.tsx` moderates customer reviews only |

### 3.8 Schema reality — no immutable tour revisions exist

Verified by reading every migration in `backend/database/migrations/`:

- There is **no** `tour_content_revisions`, `tour_revisions`, `tour_revision_requests`, `tour_content_snapshots` or equivalent table.
- `tour_translations` holds **one live row per locale** and is overwritten in place by every writer.
- The only revision table in the repository is `content_revisions` (CMS/blog), whose `snapshot` is a **flat attribute dump** of one Eloquent model, whose policy keys off `manage_revisions`/`manage_cms`/`manage_blog`, and which has no workflow state, no reason, no field references, no base-version CAS, and no tour-shaped payload.
- `tours` has no `lock_version`.

**Conclusion:** the immutable published-revision model does not exist and must be added; it is not a configuration change.

### 3.9 Reusable primitives that do exist

| Primitive | Reuse decision |
|---|---|
| `content_revisions` (append-only, morph, `version_number`) | **Not reused for tour content.** Its snapshot is a flat single-model attribute dump, its policy is CMS/blog-scoped (coupling tour review to `manage_cms`/`manage_blog` breaks least privilege), and it has no workflow state, reason, field references or CAS. A dedicated tour-scoped pair of tables is smaller in risk than bending a shared CMS primitive. **[IC]** |
| `TourTranslationService::normalizeSourceProjection()` + `sourceHash()` | **Reused verbatim.** The canonical hash algorithm is the existing derivative-currency identity and must not change. |
| `TourTranslationService::withContentLock()` | **Reused and extended** with the revision rows, preserving the lock order. |
| `TourService::syncTranslations()` / `syncMedia()` / `syncOptions()` | **Reused** for approval materialization, preserving their present-key and no-hard-delete semantics. |
| `TourPublicationGuardService::lockTourForMutation()` | **Reused** for approval locking (owner lock → tour lock → ownership re-check). |
| `GovernanceAuditService::log()` | **Reused** for every new governed event. |
| `AdminAuthorizationService::FLAGS` (JSONB-backed) | **Reused**; a new flag is a code-constant addition, no migration. |
| `ContentRevisionService` diff logic | The diff shape is trivial and will be reimplemented for the typed payload (the CMS diff is flat-key based and would not describe itinerary/option/media changes). **[IC]** |

---

## 4. Design summary

**One sentence.** A published tour gains an append-only *content revision* record; partner edits and admin corrections create *candidate* revisions that never touch live content; only the ordinary revision review can promote a candidate into the live tables, and the live tables remain the sole source of truth for every public projection.

**Pieces.**

1. `tour_content_revisions` — immutable content snapshot + identity (new table).
2. `tour_revision_requests` — the review workflow, reason and field references (new table).
3. `tours.published_revision_id` — the pointer to the approved revision (one new nullable column).
4. A `TourRevisionService` — payload build/validate/hash, locking, CAS, supersession, materialization.
5. Partner API endpoints under `/api/partner/tours/{id}/...` (new, additive).
6. Admin **domain Actions** (no admin REST endpoint exists for tours and none is added).
7. Filament: the translation repeater is removed (no raw writes), new dedicated actions, read-only revision history.
8. The generation job gains a revision binding.

**What deliberately does *not* change.** The public API, the search index projection, the SEO builder, the booking flow, the tour lifecycle matrix, `ApproveTourAction`, `TourPublicationGuardService`, the tour content field bounds, the canonical hash algorithm, the derivative fallback semantics, and every legacy source locale behaviour.

---

## 5. Data model

### 5.1 `tour_content_revisions` (new, append-only content)

| Column | Type | Meaning |
|---|---|---|
| `id` | bigint PK | |
| `tour_id` | FK `tours` cascade delete | owning tour |
| `revision_number` | int | monotonic per tour, `1..n`, allocated under the tour lock |
| `kind` | `baseline` \| `partner_edit` \| `admin_correction` | how the content was produced |
| `state` | `draft` \| `proposed` \| `approved` \| `rejected` \| `superseded` \| `withdrawn` | workflow state |
| `source_locale` | char(2) | copy of the tour's fixed source locale; never re-read from the payload |
| `content_hash` | char(64) | SHA-256 of the canonical projection + active option texts, using the **existing** hash algorithm |
| `payload` | jsonb | the typed bounded content payload (§5.3) |
| `base_revision_id` | FK self, nullable | the approved revision this candidate was derived from — the CAS token |
| `author_type` | `partner` \| `admin` \| `system` | |
| `author_id` | bigint nullable | |
| `submitted_at` | timestamp nullable | when it entered `proposed` |
| `decided_at` / `decided_by` | nullable | decision bookkeeping |
| `created_at` / `updated_at` | timestamps | |

Constraints: `unique(tour_id, revision_number)`; index `(tour_id, state)`; index `(tour_id, kind)`.
Immutability: the model rejects any update that changes `payload`, `content_hash`, `source_locale`, `revision_number`, `kind` or `base_revision_id` — only workflow columns may move. **[IC]**

### 5.2 `tour_revision_requests` (new, workflow)

| Column | Type | Meaning |
|---|---|---|
| `id` | bigint PK | |
| `tour_id` | FK `tours` cascade delete | |
| `revision_id` | FK `tour_content_revisions` nullable | the candidate that currently answers this request |
| `kind` | `admin_change_request` \| `admin_correction` \| `partner_edit` | |
| `state` | `open` \| `answered` \| `approved` \| `rejected` \| `superseded` \| `closed` | |
| `reason` | text | **mandatory, non-blank** for admin kinds; optional (≤500) for partner edits |
| `field_refs` | jsonb | `[{ "path": "content.canonical.title", "note": "…" }]` — validated against the allowlist and resolved against the approved revision |
| `response_note` | text nullable | the partner's reply text |
| `requested_by_type` / `requested_by_id` | | author of the request |
| `decided_by` / `decided_at` / `decision_reason` | nullable | decision bookkeeping |

### 5.3 The typed bounded payload

The payload has **three declared tiers** so that content, operational facts and commercial data can never be conflated.

```jsonc
{
  "content": {
    "canonical": {                     // the authored source locale; exactly the 9 existing source fields
      "title": "…",                    // string, ≤120
      "description": "…",              // string|null, ≤5000
      "highlights": ["…"],              // ≤30 items × ≤500
      "inclusions": ["…"],
      "exclusions": ["…"],
      "important_information": ["…"],
      "meeting_point": "…",            // ≤500
      "cancellation_policy": "…",      // ≤2000
      "itinerary": [                   // ≤30 days, ≤20 stops/day, integer day 1-30, integer duration 1-1440
        { "day": 1, "title": "…", "description": null,
          "stops": [ { "title": "…", "description": null, "duration_minutes": 30 } ] }
      ]
    },
    "options": [                       // ≤20; authored TEXT only, keyed by the stable option id
      { "id": 12, "title": "…", "pickup_instructions": "…" }
    ],
    "media": [                         // ≤20; distinct URLs; ordered with an explicit cover
      { "url": "https://…", "alt_text": "…", "type": "cover", "sort_order": 0 }
    ]
  }
}
```

| Tier | Where it lives | In a revision? | Why |
|---|---|---|---|
| **Content** (canonical source text, option authored text, media + alt) | revision payload | **yes** | It is traveler-facing, translated, and publicly visible; it must be reviewed. |
| **Operational** (`difficulty_level`, `guide_languages`, `location`, `location_slug`, `duration_minutes`, `duration_label`, `group_size_min`, `group_size_max`) | live `tours` row | **no** — explicitly rejected inside a revision payload | These are partner-owned facts, are not translated, and are already editable live. Storing a copy in a revision would let an approval revert a live operational edit. |
| **Commercial** (`price_amount`, `pricing_tiers`, `availability_rules`, `availability_exceptions`, option price/capacity/start time/weekdays/guide codes, departures, `status`, `published_at`, `category_id`, `partner_id`, `slug`) | live tables | **no** — explicitly rejected | Commercial and governance data. Money/availability must never be review-gated or review-reverted; partner authority over price and availability is unchanged. |

**Rejection rule.** A revision payload containing an `operational` or `commercial` key, an unknown key, a derivative-locale key, or a platform-owned key (`source_hash`, `translated_hash`, `translation_status(es)`, `readiness`, `last_error_code`, provider/job keys) is refused with **422** and the exact offending path — reusing the existing `TourContentRules::forbiddenPresentPaths()` machinery. **[IC]**

**Patch vs. snapshot.** The API accepts a **patch** (same present-key semantics as today's source patch: omitted preserves, explicit `null` clears nullable text, `[]` clears a list, nested `translations.{source_locale}` wins over shorthand). The service resolves the patch against the base revision's payload and stores the **resolved full snapshot** in the revision. A revision row is therefore always a complete, self-describing snapshot. **[IC]**

### 5.4 Pointer on the tour

`tours.published_revision_id` (nullable FK → `tour_content_revisions.id`, `nullOnDelete`). It names the revision whose content is materialized in the live tables.

- `null` on a never-published tour (nothing approved yet).
- `null` on a legacy published tour until the backfill (§11) creates its `baseline` revision.
- A `baseline` revision is a faithful snapshot of the live content at backfill time; it is `approved` by construction and is never re-approved, and it is exempt from the "title + description required" gate because it records what exists rather than proposing new content.

**[IC]** No `tours.lock_version` column is added. The CAS token is `base_revision_id` vs `published_revision_id`; a second optimistic-lock column would widen the schema without adding a guarantee the tour domain does not need.

### 5.5 State machines

**Revision**

```
baseline ──(approved, created by backfill or lazily on first correction)
partner_edit / admin_correction
   draft ──submit──▶ proposed ──approve──▶ approved
     │                  │
     │                  └──reject──▶ rejected
     ├──withdraw──▶ withdrawn
     └──superseded (a newer candidate replaced it, or a newer revision was approved)
approved ──superseded──▶ superseded   (when a newer revision is approved)
```

**Request**

```
admin_change_request:  open ──partner links a candidate──▶ answered ──revision approved──▶ approved
                                                                                      └─revision rejected──▶ rejected
admin_correction:      open ──▶ approved | rejected      (the request carries the candidate)
any open request not answered by an approved revision ──▶ superseded
```

**Invariants**

- At most **one** open candidate (`draft`/`proposed`) revision per tour. Creating another requires an explicit `supersede_revision_id`; otherwise **409 `REVISION_ALREADY_OPEN`**.
- A candidate's `base_revision_id` MUST equal `tours.published_revision_id` (both `null` allowed only while the tour has never been approved) at **submit** and at **approve**. Otherwise **409 `REVISION_SUPERSEDED`**.
- A candidate MUST NOT change `source_locale`. The tour's source locale is fixed; the revision copies it.
- Approval does **not** touch tour lifecycle, `published_at`, pricing, availability, capacity, bookings, reviews or ratings.

---

## 6. Concurrency, locking and CAS

**Lock order (extends the existing, proven order):**

```
Partner owners (ascending id)      [only when ownership may change]
  → Tour row (FOR UPDATE)
    → authored source translation row (FOR UPDATE)
      → derivative translation states, platform locale order (FOR UPDATE)
        → tour_content_revisions rows (FOR UPDATE), ascending id
          → tour_revision_requests row (FOR UPDATE)
```

The first four steps are exactly `TourTranslationService::withContentLock()` / `TourPublicationGuardService::lockTourForMutation()`, so no new lock-order inversion is introduced against the existing booking, publication and generation paths.

**Rules.**

1. `revision_number` is allocated as `max(revision_number)+1` for the tour **inside** the tour lock — no sequence gaps, no duplicate numbers, no cross-tour contention.
2. **CAS token** = `base_revision_id`. At submit and approve the service re-reads `tours.published_revision_id` under the tour lock and compares. A mismatch is **409 `REVISION_SUPERSEDED`**, and the candidate is marked `superseded` with an audit entry — it is never silently rebased.
3. Exactly one of two competing approvals can win: the loser sees `published_revision_id` already advanced and is refused. Because materialization happens inside the same transaction as the pointer advance, a crash mid-approval leaves **no** partial state (the transaction rolls back).
4. Approval re-uses `TourPublicationGuardService::lockTourForMutation()`, so it also re-checks owner approval of the partner and ownership stability against `SuspendPartnerAction` — the EGM-002 race fix is inherited, not re-implemented.
5. The generation job's existing hash guards are **kept and strengthened** (§9). No new lock is taken by the job.
6. `TourRevisionService` never calls the provider or the network inside a lock.

**Content hash.** The candidate's `content_hash` is computed with the **existing** algorithm over the candidate's canonical fields and its active option texts. To do that without touching the live tables, `TourTranslationService` gains one additive method that hashes a *given* payload instead of a stored row; the existing `sourceHash()` becomes a thin wrapper so that **previously persisted `source_hash` values stay byte-identical**. A regression test pins this against the current fixtures. **[IC]**

---

## 7. Field-reference allowlist and mandatory reason

**Allowlist** (server-side registry; the API and Filament both render from it):

| Namespace | Entries | Scope |
|---|---|---|
| `content.canonical.*` | `title`, `description`, `highlights`, `inclusions`, `exclusions`, `important_information`, `meeting_point`, `cancellation_policy`, `itinerary` | revision content — correctable by the correction action |
| `content.options[*].title`, `content.options[*].pickup_instructions` | per stable option id | revision content — correctable |
| `content.media[*].alt_text` | per media index in the approved payload | revision content — correctable |
| `operational.difficulty_level`, `operational.guide_languages`, `operational.location`, `operational.duration_minutes`, `operational.duration_label`, `operational.group_size_min`, `operational.group_size_max` | live partner-owned facts | **requestable only** — the partner fixes them on the live tour; the correction action cannot change them |
| — | price, pricing tiers, availability, capacity, departures, guide codes, lifecycle, ownership, slug, category, ratings, bookings | **not referenceable at all** |

**Rules.**

- A change request MUST carry `reason` (non-blank after trim, ≥10 chars, ≤1000) and ≥1 field reference. Both are enforced server-side; the Filament modal marks them required and the API refuses with 422.
- Every reference MUST resolve against the **approved revision's** payload (the `options[*]` id must exist; the `media[*]` index must exist). Unresolvable references → **422 `unknown_field_reference`** with the offending path.
- A reference to a non-allowlisted path → **422 `field_not_referenceable`** with the offending path.
- For an **admin correction**, the declared references MUST be a subset of the paths the correction actually changes (computed by a typed diff between the base payload and the candidate payload). A correction that changes a path it did not declare → **422 `undeclared_field_change`**. This prevents an undeclared edit from riding along on a declared one. **[IC]**
- Reasons are stored verbatim on the request and copied into the audit `metadata`. They are never logged to translation jobs or sent to Gemini.

---

## 8. Partner and admin workflows

### 8.1 Partner

| Action | Precondition | Effect |
|---|---|---|
| Create candidate | tour owned; `source_locale` unchanged; payload valid | creates a `draft` revision based on the current approved revision |
| Submit candidate | candidate is the partner's own; `base_revision_id` current; candidate canonical title + description non-blank | `draft` → `proposed`; `submitted_at` set; audit `tour.revision.submitted` |
| Withdraw candidate | candidate is the partner's own and `draft`/`proposed` | → `withdrawn` |
| Respond to a change request | request is `open`; candidate belongs to the partner | request → `answered`, `response_note` stored, `revision_id` linked |
| Edit a **draft/unpublished** tour | unchanged from today | the existing `PUT /api/partner/tours/{id}` behaviour is untouched (§8.3) |
| Edit a **published** tour | — | canonical/option-text/media fields go through the revision path only (§8.3) |

### 8.2 Admin

| Action | Permission | Effect |
|---|---|---|
| Request changes | `manage_tours` | creates an `admin_change_request` (reason + field refs); audit `tour.revision.change_requested` |
| Propose correction | **`correct_tour_content`** (new flag) | creates an `admin_correction` candidate revision (reason + declared field refs + corrected values); audit `tour.revision.correction_proposed`; **cannot approve, publish or change lifecycle** |
| Approve revision | `manage_tours` | materializes the candidate, advances `published_revision_id`, supersedes the previous approved revision, dispatches derivative generation; audit `tour.revision.approved` |
| Reject revision | `manage_tours` | `proposed` → `rejected` with a mandatory decision reason; audit `tour.revision.rejected` |
| Regenerate derivatives | `manage_tours` | re-dispatches the revision-bound generation for the current approved revision (replaces the three legacy field-engine actions) |
| Publish / unpublish / reject / archive | unchanged | `ApproveTourAction` etc. — **untouched** |

Every admin action re-resolves and re-authorizes the **passed** actor against persisted permission flags (the EGM-002 `resolveAuthorizedPublisher` pattern), so a mid-session revocation applies immediately.

**`correct_tour_content` flag.** Added to `AdminAuthorizationService::FLAGS` (a code constant). Because `admin_permissions.flags` and `admin_roles.permissions` are JSONB, **no migration is needed**; the flag is granted through the existing role templates in `backend/app/Domains/Admin/Models/AdminRole.php`. It is deliberately **not** implied by `manage_tours` and **not** granted to `super_admin` implicitly — wait: `super_admin` is a wildcard that grants every flag. That is the existing platform-wide behaviour and is retained; the flag still exists as a distinct, separately grantable permission for every non-super admin. **[IC]**

### 8.3 What happens to the existing partner `PUT`

`PUT /api/partner/tours/{id}` keeps its exact contract for **operational and commercial** fields on every tour, published or not.

For a **published** tour, the request is additionally scanned for **content** fields:

| Content present in a `PUT` on a published tour | Behaviour |
|---|---|
| `translations.{source_locale}.*` (canonical), option `title`/`pickup_instructions`, `media` | **422 `content_requires_revision`** with the exact paths, and a pointer to the revision endpoint |
| nothing content-shaped | accepted exactly as today |

**Deployment ordering (constitution §Implementation 3).** The refusal is **not** enabled in the same release that adds the revision endpoints. The sequence is:

1. Backend release **A** (additive): migration + domain + new endpoints + `capabilities.revision_review = true` in the owned-tour payload. `PUT` behaviour unchanged.
2. Frontend release: the partner editor reads `capabilities.revision_review`; when present and the tour is `published`, "Save" becomes "Propose changes" (revision endpoint). Against an older backend the flag is absent and the old `PUT` path is used.
3. Backend release **B** (enforcement): the `PUT` refusal above.

Rollback of any step leaves previously deployed clients working. **[IC]**

---

## 9. Translation generation binding

**Job payload.** `GenerateTourTranslationJob(tourId, locale, sourceHash, ?revisionId)`. `revisionId` is nullable so legacy dispatches and draft-tour dispatches are unchanged.

**Guards (all must hold before any write):**

1. The tour exists; the locale is a derivative locale of the tour's current source locale.
2. `service->sourceHash($source, $tour) === $this->sourceHash` (existing).
3. `tour_translation_states.source_hash === $this->sourceHash` (existing).
4. **New:** if `revisionId !== null`, then `tours.published_revision_id === $revisionId`. A job bound to a superseded revision is a no-op even if a hash collision were conceivable.

**Leak prevention.** The job reads **only** the materialized `tour_translations` source row and the live options — never a revision payload. A candidate revision is therefore invisible to the provider, to the derivative rows and to the public projection until it is approved and materialized. Candidate content never reaches Gemini.

**Dispatch.** `planSourceDispatches()` gains the approved revision id of the tour at dispatch time and passes it into the plan tuple; `dispatchPlannedTranslations()` pushes it after commit as today.

**Post-approval sequence.** Approval materializes the new canonical source row and the new option texts, so `sourceHash()` changes. `planSourceDispatches()` then marks each derivative `stale` (row exists) or `pending` (no row) with the new desired hash and dispatches after commit. While the derivative is not ready, the public page shows the **new approved source** with the existing localized fallback notice — exactly the constitution's behaviour for a source change, and never a candidate's content.

**Legacy field engine.** `AutoTranslationService` currently skips tours unless the source is English and no options exist, and it models *manual* derivative overrides. Under P3 that path must not exist for tours:
- `Tour::supportsFieldTranslation()` returns `false` for all tours, so the service never touches tour content.
- The three Filament header actions that call it (`translate_missing_fields`, `retranslate_outdated_fields`, `force_retranslate_all`) are **removed** from the Tour edit page and replaced by the read-only "Regenerate derivatives" action.
- The CMS/blog use of `AutoTranslationService` is untouched.

**[IC]**

---

## 10. Public, search, SEO, image, itinerary, option and booking compatibility

**Public projections are unchanged in code and in shape.** The live tables (`tour_translations`, `tour_options`, `tour_media`, `tour_drafts`) remain the only thing the public readers consult. A candidate revision writes nothing there, so:

| Surface | Before approval | After approval |
|---|---|---|
| `GET /api/public/tours/{slug}` | byte-identical to the pre-revision baseline | reads the newly materialized content; `content_locale` / `itinerary_locale` / `translation_status` / `translation_warning` follow the existing rules |
| Search index (`Tour::toSearchableArray`) | unchanged | refreshed by the existing post-commit `IndexTourAction` |
| SEO metadata + hreflang | unchanged | derived from the newly selected content, as today |
| Gallery (order, cover, dedup, alt, `alt_locale`) | unchanged | `tour_media` is materialized atomically with the content; alt text follows the source locale per the existing rule |
| Itinerary | unchanged | materialized into the source row; the derivative itinerary regenerates and may independently fall back |
| Option content | unchanged | option authored text is materialized; derivative option text regenerates; `content_locale` per option follows the existing rule |
| Booking snapshot | unchanged | bookings keep what the traveler was served; **no revision ever rewrites a booking** |
| Bookability (`isPubliclyBookable`), pricing, capacity, availability | unchanged | revisions never touch commercial data |

**Public image / itinerary / option consistency guarantee.** Because approval materializes the canonical source, the option texts **and** the media list in a single transaction under the same lock, the public gallery, the public itinerary and the public option titles always describe the same approved revision. A pending revision cannot produce a mixed state because it writes nothing.

**Search/SEO "no stale content" guarantee.** A candidate is never indexed and never projected; the index only ever sees materialized (approved) content.

**Cache invalidation.** `Tour::booted()` and `TourTranslation::booted()` already clear `tour.{slug}.{locale}` on save; materialization reuses those model events, so the detail cache is invalidated by the same path as today's partner save.

---

## 11. Filament UX contract

### 11.1 The general form (no raw writes)

- **Remove** the `translations` repeater from `TourResource::form()`. After this change **no** Filament surface can write `tour_translations` rows, in any locale, for any reason. This satisfies P3 outright and removes the structural anchor of the EGM-003 finding.
- Keep the existing operational/commercial form fields (slug, category, partner, location, price, duration, group size, featured, cover image). These are not canonical content, are already covered by the EGM-002 guards, and their removal is out of scope.
- The EGM-002 lifecycle protections (`disabled` status display, `assertGeneralSaveKeepsCurrentStatus`, `assertPublishedTourEditIntegrity`) remain **unchanged**.
- The temporary EGM-003 guard keeps its current behaviour (it now protects a surface that can no longer write content, so it becomes belt-and-braces).

### 11.2 Read-only content display

Extend the existing infolist (which already shows "Authored source (required)" and derivative status) with:

- A **per-locale read-only content block** for the source locale and each derivative locale: locale, actual language, title, description, itinerary summary, and the derivative readiness badge from `TourTranslationService::publicStatus()`. No editable translation fields exist anywhere in Filament.
- A **revision history section**: revision number, kind, state, author, timestamps, reason, declared field references, and the content hash (a hash is an operational identifier, not a secret; it is the same value already stored in `tour_translation_states` and is not exposed publicly).
- An **open change request** block with its reason and referenced fields, plus the candidate revision's state.

### 11.3 Dedicated actions (Filament)

| Action | Where | Visibility | Server behaviour |
|---|---|---|---|
| **Request changes** | edit page header + row action | `manage_tours` and the tour is `published` | modal: required `reason` (min 10) + required multi-select of allowlisted fields → `RequestTourChangesAction` |
| **Propose correction** | edit page header | `correct_tour_content` and the tour is `published` | modal: required `reason` + editable values **only** for the allowlisted content fields → `ProposeTourCorrectionAction` |
| **Approve revision** | edit page header | `manage_tours` and a `proposed` candidate exists | `ApproveTourRevisionAction` |
| **Reject revision** | edit page header | `manage_tours` and a `proposed` candidate exists | modal: required reason → `RejectTourRevisionAction` |
| **Regenerate derivatives** | edit page header | `manage_tours` | re-dispatch revision-bound generation (replaces the three removed field-engine actions) |
| **Revisions** (read-only) | view page infolist | `manage_tours` | history + open requests |

Filament actions stay **thin**: each calls exactly one domain Action and renders the Action's outcome. The domain Action owns authorization, validation, locking, CAS, materialization and audit. **[IC]**

**Demo seeder.** `DemoContentManager::generate` writes published tour content directly and therefore bypasses revision governance. It **stays refused** (its existing 403) until a separately approved workflow exists. Restoration is explicitly out of this scope, matching the EGM-002 follow-up wording.

---

## 12. Exact API contracts

All partner endpoints keep `auth:sanctum` + `partner` middleware and the `booking.get` throttle. Ownership is enforced by the scoped lookup (**404**, never 403/422, for another partner's tour). There is **no** new public endpoint and **no** new admin REST endpoint.

### 12.1 `GET /api/partner/tours/{id}` — additive

```jsonc
{
  "data": {
    /* …existing fields unchanged… */
    "content_governance": {
      "source_locale": "es",
      "approved_revision": { "id": 12, "revision_number": 3, "kind": "baseline",
                             "content_hash": "9f2c…", "created_at": "2026-11-14T09:00:00Z" },
      "open_revision": { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "proposed",
                         "submitted_at": "2026-11-14T10:12:00Z", "change_summary": "…" },
      "open_requests": [
        { "id": 9, "kind": "admin_change_request", "state": "open",
          "reason": "The meeting point no longer matches the printed ticket.",
          "field_refs": [ { "path": "content.canonical.meeting_point", "note": "…" } ],
          "created_at": "2026-11-14T08:00:00Z" }
      ],
      "capabilities": { "revision_review": true }
    }
  }
}
```

`content_governance` is `null` for a tour whose revision governance is not yet available (older backend) — the client then keeps the current behaviour.

### 12.2 `POST /api/partner/tours/{id}/revisions`

Request:

```jsonc
{
  "change_summary": "Correct the meeting point",            // optional, ≤500
  "base_revision_id": 12,                                    // optional; must match the approved revision
  "supersede_revision_id": null,                             // optional; required only when a candidate is already open
  "translations": { "es": { "meeting_point": "Plaza de Armas, puerta 3" } },
  "options": [ { "id": 12, "title": "Grupo pequeño", "pickup_instructions": "…" } ],
  "media": [ { "url": "https://…/a.jpg", "alt_text": "…", "is_cover": true } ]
}
```

| Condition | Status | Body |
|---|---|---|
| Created | **201** | `{ "data": { "id": 15, "revision_number": 4, "kind": "partner_edit", "state": "draft", "content_hash": "…", "base_revision_id": 12, "created_at": "…" }, "message": "Revision saved." }` |
| `base_revision_id` is not the approved revision | **409** | `{ "message": "…", "error_code": "REVISION_SUPERSEDED" }` |
| A candidate is already open and `supersede_revision_id` does not match it | **409** | `{ "message": "…", "error_code": "REVISION_ALREADY_OPEN", "revision_id": 14 }` |
| Payload contains `operational`/`commercial`/derivative-locale/platform-owned keys | **422** | `{ "message": "…", "errors": { "<path>": ["…"] } }` |
| Content bounds violated | **422** | `{ "message": "…", "errors": { "translations.es.itinerary.0.stops.2.duration_minutes": ["…"] } }` |
| Option id not owned by the tour | **422** | `{ "message": "…", "errors": { "options.0.id": ["The selected option does not belong to this tour."] } }` |
| Not the owner's tour / unknown | **404** | `{ "message": "Not found." }` |
| Partner not approved / suspended | **403** | `{ "message": "…", "error_code": "ONBOARDING_STATUS_BLOCKED", "onboarding_status": "…" }` |

### 12.3 `POST /api/partner/tours/{id}/revisions/{revisionId}/submit`

**200** `{ "data": { …revision, "state": "proposed", "submitted_at": "…" }, "message": "Revision submitted for review." }`
**409** `REVISION_SUPERSEDED` · **422** `REVISION_CONTENT_INVALID` (candidate canonical title/description missing) · **404** not owner / not found.

### 12.4 `POST /api/partner/tours/{id}/revisions/{revisionId}/withdraw`

**200** `{ "data": { …revision, "state": "withdrawn" }, "message": "Revision withdrawn." }` · **409** not withdrawable · **404**.

### 12.5 `POST /api/partner/tours/{id}/revision-requests/{requestId}/respond`

Request `{ "revision_id": 15, "response_note": "Fixed, see the new meeting point." }`
**200** `{ "data": { …request, "state": "answered", "response_note": "…" }, "message": "Response sent." }` · **409** `REQUEST_NOT_OPEN` · **422** `REVISION_NOT_OWNED` / `REVISION_STATE_INVALID` · **404**.

### 12.6 `GET /api/partner/tours/{id}/revisions` and `GET /api/partner/tours/{id}/revision-requests`

**200** `{ "data": [ … ], "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 3 } }`. Read-only, owner-scoped. Revision entries expose number, kind, state, timestamps, change summary, reason, field refs and content hash — never a payload of another partner's content (the list is owner-scoped anyway).

### 12.7 Error-code registry (new)

`REVISION_SUPERSEDED`, `REVISION_ALREADY_OPEN`, `REVISION_CONTENT_INVALID`, `REVISION_STATE_INVALID`, `REVISION_NOT_OWNED`, `REQUEST_NOT_OPEN`, `unknown_field_reference`, `field_not_referenceable`, `undeclared_field_change`, `content_requires_revision`, `revision_review_unavailable`.

All of them are machine-readable `error_code` values on the existing `{"message": …}` / `{"message": …, "errors": {…}}` shapes already used by the partner API. No new envelope is introduced. **[IC]**

---

## 13. Admin domain-Action contracts (no admin REST)

All four are `App\Domains\Admin\Actions\*`, take the **actor** as a parameter, re-authorize it against persisted flags, and write exactly one audit event.

| Action | Signature | Refusals |
|---|---|---|
| `RequestTourChangesAction` | `execute(User $actor, Tour $tour, string $reason, array $fieldRefs): TourRevisionRequest` | 403 if the actor lacks `manage_tours`; 422 if the reason is blank/short or a field reference is not allowlisted/unresolvable; 409 if an identical open request already exists |
| `ProposeTourCorrectionAction` | `execute(User $actor, Tour $tour, string $reason, array $fieldRefs, array $patch): TourContentRevision` | 403 if the actor lacks `correct_tour_content`; 422 for reason/field-reference/`undeclared_field_change`/bounds; 409 if the tour is not `published` or a candidate is already open without `supersede_revision_id` |
| `ApproveTourRevisionAction` | `execute(User $actor, Tour $revisionId): Tour` | 403 without `manage_tours`; 409 `REVISION_SUPERSEDED`; 422 if the candidate's canonical title/description are missing or the payload is invalid; 404 unknown |
| `RejectTourRevisionAction` | `execute(User $actor, Tour $revisionId, string $reason): TourContentRevision` | 403 without `manage_tours`; 422 if the reason is blank; 409 if not `proposed` |

**Audit events** (append-only, `governance_audit_logs`): `tour.revision.submitted`, `tour.revision.change_requested`, `tour.revision.correction_proposed`, `tour.revision.approved`, `tour.revision.rejected`, `tour.revision.withdrawn`, `tour.revision.superseded`, `tour.revision.responded`. `before_state`/`after_state` carry revision ids, numbers, states and hashes; `metadata` carries the reason, the declared field references and the actor's role. No content payload is copied into the audit rows (they would bloat the trail and duplicate content that already exists in the revision). **[IC]**

**Correction action cannot approve or publish** — enforced structurally: `ProposeTourCorrectionAction` has no code path that touches `status`, `published_at`, `tour_translation_states` or the live content tables. A regression test asserts that a correction leaves `status`, `published_at`, `tour_translations`, `tour_options`, `tour_media` and `tour_translation_states` byte-identical.

---

## 14. Requirements traceability (AC-001 … AC-0xx)

`AC` identifiers are **new and additive**. No existing FR/SC identifier in Specs 018, 019 or 020 is renumbered, reworded away, or removed. The "new requirement" column names the requirement added by this dispatch's documentation sync (§16).

### 14.1 Immutable revision and approval

| AC | Objective statement | Traces to | New requirement |
|---|---|---|---|
| AC-001 | A partner content save on a **published** tour MUST NOT change any row of `tour_translations`, `tour_options`, `tour_option_translations`, `tour_media`, `tour_translation_states` or `tours`, and MUST NOT change the public detail payload, the search projection or the SEO metadata. | 019 FR-004, FR-010, FR-014, FR-027, FR-028; 018 FR-017, FR-026, FR-032; 020 FR-007, FR-028, FR-032 | 019 FR-031, 018 FR-034 |
| AC-002 | An admin correction MUST leave `tours.status`, `tours.published_at`, `tour_translations`, `tour_options`, `tour_media` and `tour_translation_states` byte-identical and MUST create exactly one candidate revision, one audit row, and no lifecycle transition. | P5; 019 FR-004, FR-027; 018 FR-022, FR-025 | 019 FR-038, FR-039 |
| AC-003 | The only writer of `tour_translations` rows in any locale from an admin surface is zero: a crafted Filament request carrying `translations[...]` MUST NOT create, update or delete a translation row. | P1, P3; 019 FR-002, FR-011; 018 FR-022, FR-025 | 019 FR-037 |
| AC-004 | A candidate revision MUST be immutable: an update attempt that changes `payload`, `content_hash`, `source_locale`, `revision_number`, `kind` or `base_revision_id` MUST be rejected at the model layer. | 019 FR-004, FR-012 | 019 FR-032 |
| AC-005 | The revision payload MUST carry content tiers only; a payload containing `operational`, `commercial`, derivative-locale or platform-owned keys MUST be refused with 422 and the exact path. | 019 FR-003, FR-011, FR-017 | 019 FR-033 |
| AC-006 | The candidate `content_hash` MUST equal the `sourceHash()` computed from the same content after materialization, and the pre-existing hash of an unchanged tour MUST be byte-identical before and after the hashing refactor. | 019 FR-010, FR-012; 018 FR-032 | 019 FR-032 |
| AC-007 | A revision approval MUST be refused with 409 when `base_revision_id` no longer equals `tours.published_revision_id`, and the candidate MUST be marked `superseded`. | 019 FR-012; 018 FR-025 | 019 FR-035 |
| AC-008 | On real PostgreSQL, two concurrent approvals of two candidates based on the same approved revision MUST yield exactly one success, one 409, one materialized content set, and no partially applied state. | 019 FR-012; 018 FR-032 | 019 FR-035, SC-010 |
| AC-009 | A tour MUST have at most one open (`draft`/`proposed`) candidate; creating a second without an explicit `supersede_revision_id` MUST be refused with 409, and an explicit supersede MUST mark the old candidate `superseded` with an audit row. | 019 FR-012 | 019 FR-032 |
| AC-010 | The canonical source locale of a tour MUST be identical before and after any revision creation, approval, correction or generation job, and a request attempting to change it MUST be refused. | 019 FR-003; 018 FR-017, FR-029 | 019 FR-034 |

### 14.2 Change requests, reasons and field references

| AC | Objective statement | Traces to | New requirement |
|---|---|---|---|
| AC-011 | An admin change request MUST be refused with 422 when the reason is blank/whitespace or shorter than 10 characters, and when no field reference is supplied. | P2; 018 FR-022 | 019 FR-036 |
| AC-012 | A field reference outside the allowlist MUST be refused with 422 `field_not_referenceable`; a reference that does not resolve against the approved revision MUST be refused with 422 `unknown_field_reference`. | P2 | 019 FR-036 |
| AC-013 | An admin correction that changes a path it did not declare MUST be refused with 422 `undeclared_field_change`. | P5 | 019 FR-038 |
| AC-014 | A request and a correction MUST be readable by the owning partner with the reason and the referenced fields, and MUST NOT be readable by another partner (404). | 019 FR-002; 018 FR-020 | 019 FR-036 |
| AC-015 | A partner response MUST be accepted only for the partner's own candidate and an open request, and MUST move the request to `answered` with the stored note. | P1, P2 | 019 FR-036 |

### 14.3 Derivatives and generation

| AC | Objective statement | Traces to | New requirement |
|---|---|---|---|
| AC-016 | A generation job bound to a revision that is no longer `tours.published_revision_id` MUST NOT write a derivative row, even when the source hash still matches. | 019 FR-011, FR-012; 018 FR-032 | 019 FR-037 |
| AC-017 | A candidate revision's content MUST never reach the translation provider, a derivative row, a derivative state row, the search index or the public projection. | 019 FR-010, FR-011, FR-014 | 019 FR-037, FR-040 |
| AC-018 | After an approval, both derivative locales MUST be scheduled asynchronously for the new revision; the save/approval MUST NOT wait for generation, and publication eligibility MUST NOT depend on derivative readiness. | 019 FR-004, FR-010; 018 FR-017, FR-031 | 019 FR-040 |
| AC-019 | While a derivative is missing, pending, stale or failed, the public page MUST serve the current approved source with the existing localized notice, and the SEO description MUST reflect the displayed values. | 019 FR-014, FR-015, FR-028; 018 FR-026; 020 FR-027, FR-028 | 020 FR-035 |
| AC-020 | `Tour::supportsFieldTranslation()` MUST be false for every tour, and the three legacy field-engine header actions MUST no longer exist on the Tour edit page. | P3; 019 FR-011 | 019 FR-037 |

### 14.4 Compatibility

| AC | Objective statement | Traces to | New requirement |
|---|---|---|---|
| AC-021 | The public tour-detail response shape, field names, status codes (200/404/410/429) and envelope MUST be unchanged by this work; a contract test over the existing fixtures MUST pass byte-identically. | 019 FR-027; 018 FR-002, FR-033; 020 FR-001 | 019 FR-040, 020 FR-035 |
| AC-022 | The public gallery (order, cover, dedup, alt text and `alt_locale`) and the public option texts MUST describe the same approved revision as the public body content, after approval and before it. | 019 FR-019, FR-020, FR-021; 020 FR-007, FR-009, FR-012 | 019 FR-040 |
| AC-023 | An existing booking MUST keep its stored option title, pickup instructions and content locale after any revision is created, submitted or approved; no revision may write to `bookings`. | 019 FR-027; 020 FR-022 | 019 FR-040 |
| AC-024 | The `PUT /api/partner/tours/{id}` contract for operational/commercial fields MUST be unchanged on unpublished tours and unchanged in release A on published tours; the release-B refusal MUST return 422 `content_requires_revision` with the exact paths. | 019 FR-001, FR-002, FR-027 | 019 FR-040 |
| AC-025 | The backfill command MUST be idempotent and resumable, MUST create a `baseline` revision for every published tour exactly once, MUST NOT alter any live content, and `--dry-run` MUST write nothing. | 019 FR-027, FR-008 | 019 FR-032 |

### 14.5 Governance

| AC | Objective statement | Traces to | New requirement |
|---|---|---|---|
| AC-026 | Every governed operation MUST write exactly one `governance_audit_logs` row identifying actor, action, target, timestamp and before/after state, and MUST NOT store secrets or content payloads. | Constitution Security §; 018 FR-025 | 019 FR-038 |
| AC-027 | The correction action MUST refuse an actor without `correct_tour_content`, and MUST be callable only through the domain action — no Filament form field may write canonical or derivative content. | P5; 018 FR-022, FR-025 | 019 FR-038 |
| AC-028 | No tour may become `published` through any path other than `ApproveTourAction`; the revision actions MUST NOT change `status` or `published_at`. | Constitution §II; 018 FR-025; 020 FR-001 | 019 FR-039 |

---

## 15. Measurable test plan

### 15.1 Backend — Pest on real PostgreSQL

Command (from the plan §7 and the EGM-002 follow-up):

```sh
cd backend
php vendor/bin/pest <files> --compact --do-not-cache-result      # disposable bookly-test-postgres/bookly_test
php artisan test --compact tests/Feature/Partner tests/Feature/Booking tests/Feature/Search tests/Feature/Payment
```

| Test file (new unless noted) | Covers | ACs |
|---|---|---|
| `backend/tests/Feature/Partner/TourRevisionTest.php` | candidate creation, patch resolution, payload tier rejection, immutable model guard, hash parity with the live hash, one-open-candidate rule, supersede, submit/withdraw, response linking | AC-001, 004, 005, 006, 009, 010, 011, 012, 014, 015 |
| `backend/tests/Feature/Partner/TourRevisionConcurrencyTest.php` | **real** PostgreSQL races using the existing `tests/Support/TourApprovalWorker.php` pattern and `pg_blocking_pids`: two concurrent approvals → one success + one 409; concurrent candidate creation → one open candidate; approval racing a partner save | AC-007, 008 |
| `backend/tests/Feature/Partner/TourRevisionBackfillTest.php` | idempotency, resumability, `--dry-run` writes nothing, baseline content equals live content, no live row changes | AC-025 |
| `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php` | correction action refuses a flagless actor; correction leaves live tables byte-identical; correction cannot approve/publish/lifecycle; change-request reason and field-reference refusals; `undeclared_field_change`; audit rows and their shape | AC-002, 011, 012, 013, 026, 027, 028 |
| `backend/tests/Feature/Admin/Filament/TourRevisionActionsTest.php` (Livewire) | request-changes modal requires reason + fields; approve/reject revision actions; correction action hidden without the flag; read-only translation display; revision history rendering | AC-002, 011, 013, 027 |
| `backend/tests/Feature/Admin/Filament/TourTranslationWriteGuardTest.php` (Livewire) | a crafted Filament request carrying `translations[...]` creates/updates/deletes **no** translation row, in any locale; the three legacy field-engine actions are gone | AC-003, 020 |
| `backend/tests/Feature/Search/TourRevisionPublicProjectionTest.php` | public detail + index + SEO identical before/while a candidate exists; changed only after approval; fallback notice behaviour with pending derivatives; gallery/option consistency | AC-001, 019, 022 |
| `backend/tests/Feature/Booking/TourRevisionBookingSnapshotTest.php` | booking snapshot unchanged across revision create/submit/approve; no revision writes `bookings` | AC-023 |
| `backend/tests/Feature/Partner/TourTranslationJobRevisionTest.php` | a job bound to a superseded revision writes nothing; a job bound to the current approved revision completes normally; candidate content never reaches the fake provider | AC-016, 017, 018 |
| `backend/tests/Feature/Partner/TourContentInputTest.php` (existing, extended) | the release-B `content_requires_revision` refusal and the release-A compatibility of `PUT` | AC-024 |

**Real-race requirement.** Per the Spec 019 translation-generation contract ("sequential hash assertions alone are insufficient evidence of row-lock correctness"), the concurrency tests MUST use independent database connections/processes and assert on `pg_blocking_pids`, reusing `backend/tests/Support/TourApprovalWorker.php`.

### 15.2 Frontend — Jest

```sh
cd frontend
node node_modules/jest/bin/jest.js --runInBand --runTestsByPath <files>
```

| Test file | Covers |
|---|---|
| `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx` (new) | renders the candidate state, the admin change request with its reason and referenced fields, the partner response action, and the "propose changes" affordance |
| `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/__tests__/page.test.tsx` (existing, extended) | on a published tour with `capabilities.revision_review`, a canonical edit is routed to the revision endpoint and no `PUT` is issued; without the capability the old `PUT` path is used |

### 15.3 Frontend — Playwright (browser)

```sh
cd frontend
DOCKER_ENV=true PLAYWRIGHT_BASE_URL=http://nginx node node_modules/@playwright/test/cli.js test \
  tests/e2e/partner/tour-revision.spec.ts --project=chromium-partner --project=a11y-partner --no-deps --reporter=line
```

| Test file | Covers |
|---|---|
| `frontend/tests/e2e/partner/tour-revision.spec.ts` (new) | the partner edits a published tour, sees the propose-changes flow, submits a candidate, sees the admin's change request with reason + field, responds, and the **public page still shows the approved content** throughout; the new surface is keyboard operable and Axe-clean at 390/768/1024/1440 |

The Filament surfaces have no browser-level frontend project (admin is Laravel-rendered); they are covered by the Livewire tests above plus the existing `frontend/tests/e2e/filament-admin.spec.ts` smoke.

### 15.4 Repository gates (to run per slice, from the Constitution §Verification)

```sh
cd backend
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

cd frontend
npm run lint
npm run typecheck
npm test
npm run test:e2e
npm run test:a11y
npm run build
```

**Known baseline (not introduced here):** PHPStan reports 21 pre-existing errors in booking/partner/search/option code; `AdminE2EFixtureSeederTest` fails in environments without GD JPEG support. Both are recorded in the EGM-002 follow-up and must be reported as pre-existing, not silently ignored.

---

## 16. Documentation synchronization performed by this dispatch

| Document | Change | Nature |
|---|---|---|
| `specs/019-tour-content-data/spec.md` | Added FR-031…FR-040 and SC-009…SC-011 (revision governance) and an "EGM-003 owner decision" record. Existing FR-001…FR-030 and SC-001…SC-008 untouched. | additive |
| `specs/019-tour-content-data/data-model.md` | Added the two new tables, the `tours.published_revision_id` column, the three payload tiers and the revision/request state machines. | additive |
| `specs/019-tour-content-data/contracts/partner-tour-content-api.md` | Added the revision endpoints, error codes, payload shape and the published-tour `PUT` rule. | additive |
| `specs/019-tour-content-data/contracts/translation-generation.md` | Added revision-bound generation, the leak rules and the legacy field-engine retirement. | additive |
| `specs/020-tour-detail-experience/spec.md` | Added FR-035 and SC-010 (a pending revision never appears in any public surface). | additive |
| `specs/018-bookly-ui-redesign/spec.md` | Added FR-034 and SC-012. | additive |
| `docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md` | Added the EGM-003 decision and the A4 revision-governance status line. | additive |
| `docs/reports/supplier-portal-v3-goldfish-review.md` | Added the dated owner decision and the design pointer. **EGM-003 is explicitly NOT closed.** | additive |

No requirement identifier, acceptance identifier, evidence artifact or historical record was renumbered, removed or reworded away.

---

## 17. Implementation slices (independent, ordered)

Each slice is independently testable and shippable. Exact intended files; **no file in this table was created or modified by this dispatch.**

### S1 — Schema, models and backfill command (additive, no behaviour change)
- `backend/database/migrations/2026_11_14_000001_create_tour_content_revision_tables.php` (new)
- `backend/app/Models/TourContentRevision.php` (new)
- `backend/app/Models/TourRevisionRequest.php` (new)
- `backend/app/Models/Tour.php` (add `published_revisionId()` relation; no fillable change)
- `backend/app/Console/Commands/BackfillTourContentRevisions.php` (new)
- `backend/tests/Feature/Partner/TourRevisionBackfillTest.php` (new)
- Gates: `php artisan test tests/Feature/Partner/TourRevisionBackfillTest.php`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`

### S2 — Revision domain service (payload, hash, locks, CAS, materialization)
- `backend/app/Domains/Partner/Services/TourRevisionService.php` (new)
- `backend/app/Domains/Partner/Requests/TourRevisionPayloadRules.php` (new; allowlist, tiers, bounds, field-reference validation)
- `backend/app/Domains/Partner/Services/TourTranslationService.php` (add `sourceHashForPayload()`; keep `sourceHash()` byte-identical)
- `backend/app/Domains/Partner/Services/TourService.php` (additive helpers for materialization reuse)
- `backend/tests/Feature/Partner/TourRevisionTest.php` (new)
- Gates: as S1 plus `php artisan test tests/Feature/Partner`

### S3 — Partner API
- `backend/routes/api/partner.php` (new sub-routes)
- `backend/app/Domains/Partner/Controllers/TourRevisionController.php` (new)
- `backend/app/Domains/Partner/Requests/CreateTourRevisionRequest.php`, `SubmitTourRevisionRequest.php`, `RespondToTourRevisionRequestRequest.php` (new)
- `backend/app/Domains/Partner/Controllers/TourController.php` (additive `content_governance` in the owned detail)
- `backend/app/Domains/Partner/Services/TourService.php` (additive projection)
- Gates: `php artisan test tests/Feature/Partner`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`

### S4 — Admin domain actions and the correction permission
- `backend/app/Domains/Admin/Actions/RequestTourChangesAction.php` (new)
- `backend/app/Domains/Admin/Actions/ProposeTourCorrectionAction.php` (new)
- `backend/app/Domains/Admin/Actions/ApproveTourRevisionAction.php` (new)
- `backend/app/Domains/Admin/Actions/RejectTourRevisionAction.php` (new)
- `backend/app/Domains/Admin/Services/AdminAuthorizationService.php` (add the `correct_tour_content` flag — no migration)
- `backend/app/Domains/Admin/Models/AdminRole.php` (grant the flag in the default role templates)
- `backend/tests/Feature/Admin/TourRevisionGovernanceTest.php` (new)
- Gates: `php artisan test tests/Feature/Admin`, Pint, PHPStan

### S5 — Filament: read-only content, dedicated actions, no raw writes
- `backend/app/Filament/Resources/TourResource.php` (remove the `translations` repeater; add actions; extend the infolist)
- `backend/app/Filament/Resources/TourResource/Pages/EditTour.php` (remove the three field-engine actions; add the new ones)
- `backend/app/Models/Tour.php` (`supportsFieldTranslation()` → false)
- `backend/app/Domains/Translation/Services/AutoTranslationService.php` (tour branch disabled only)
- `backend/tests/Feature/Admin/Filament/TourRevisionActionsTest.php`, `TourTranslationWriteGuardTest.php` (new)
- Gates: `php artisan test tests/Feature/Admin/Filament`, Pint, PHPStan

### S6 — Revision-bound generation
- `backend/app/Domains/Partner/Jobs/GenerateTourTranslationJob.php` (add `revisionId` + guard)
- `backend/app/Domains/Partner/Services/TourTranslationService.php` (plan carries the revision id)
- `backend/app/Domains/Partner/Services/TourRevisionService.php` (pass the approved revision id on approval)
- `backend/tests/Feature/Partner/TourTranslationJobRevisionTest.php` (new)
- Gates: `php artisan test tests/Feature/Partner`, Pint, PHPStan

### S7 — Frontend partner surfaces
- `frontend/src/app/[locale]/(partner)/partner/tours/[id]/edit/page.tsx` (published → propose-changes path)
- `frontend/src/components/partner/tours/TourRevisionPanel.tsx` (new)
- `frontend/src/lib/api/partner.ts` (new client functions)
- `frontend/src/lib/api/types.ts`, `frontend/src/types/partner.ts` (revision types)
- `frontend/messages/{en,es,it}.json` (new copy in all three locales — Constitution §VI)
- `frontend/src/components/partner/tours/__tests__/TourRevisionPanel.test.tsx` (new), edit-page test extension
- Gates: `npm run lint`, `npm run typecheck`, `npm test -- --runInBand`, `npm run build`

### S8 — Browser acceptance for the partner revision flow
- `frontend/tests/e2e/partner/tour-revision.spec.ts` (new)
- Gates: the Playwright command in §15.3, plus `npm run test:a11y`

### S9 — Enforcement release (after S7 is deployed)
- `backend/app/Domains/Partner/Requests/UpdateTourRequest.php` (content-field refusal on published tours)
- `backend/app/Domains/Partner/Services/TourService.php` (the refusal message and paths)
- `backend/tests/Feature/Partner/TourContentInputTest.php` (extended)
- Gates: `php artisan test tests/Feature/Partner`, Pint, PHPStan

**Slices S1–S2 must precede S3–S6; S3–S4 must precede S5; S7 must precede S9.** S5 (removing the Filament repeater) may ship with S4 because it only removes an admin write path and adds read-only display.

---

## 18. Deployment, backfill and rollback

**Deployment order.**

1. **S1** — additive migration (new tables + one nullable column). No data movement, no destructive change. The `down()` drops the new tables and the column and touches nothing else.
2. **S2–S6** — backend features, additive. `PUT` behaviour unchanged (`capabilities.revision_review` advertises the new path).
3. **S5** — Filament: the translation repeater is removed and the correction/review actions appear. This is the moment P3 and P5 become structurally true.
4. **Backfill** — `tours:backfill-content-revisions --after-id=0 --limit=100` (repeat until exhausted), producing one `baseline` revision per published tour and setting `tours.published_revision_id`. `--dry-run` first; the command is idempotent and resumable. It writes no live content.
5. **S7** — frontend. Against a backend without the capability flag the old path is used, so this can ship before or after S2–S6.
6. **S9** — enforcement of the published-tour `PUT` refusal. Only after S7 is live.

**Rollback.**

- Rolling the backend back to a pre-revision build leaves the new tables unused and `tours.published_revision_id` ignored (nullable, additive) → no data loss, no migration rollback required for the application to run.
- Rolling the frontend back needs no data deletion.
- `migrate:rollback` of the new migration is available but is **not** part of an ordinary release rollback; the constitution requires destructive cleanup to be a separate follow-up after adoption is verified.
- Materialized content is written into the existing live tables, so a rollback leaves the public content at its last approved value — it never reverts to a candidate.
- The demo seeder stays refused throughout; restoring it requires a separately approved workflow.

**Monitoring after rollout.** Candidate counts by state, change-request turnaround, supersession rate, corrections per tour, approval latency, generation-failure rate, and the derivative fallback rate. No prompt, provider body, key or credential is logged (the existing sanitized error categories apply).

---

## 19. Unresolved questions (not silently chosen)

| # | Question | Why it is not decided here | Proposed default |
|---|---|---|---|
| Q1 | Must a correction be approved by a **different** admin than the one who proposed it (four-eyes)? | The owner policy does not mention separation of duties for corrections. | **No four-eyes requirement.** Any admin holding `manage_tours` may approve, including the proposer. Recorded as an open governance choice; introducing four-eyes later is additive. |
| Q2 | May an admin **request changes** to commercial fields (price, capacity, availability)? | "Specific fields" is not enumerated by the owner. | **No.** Commercial data is partner-owned; the allowlist exposes content and operational fields only. |
| Q3 | May an admin **correct** operational fields (difficulty, guide languages, location, duration)? | Operational fields are live partner-owned facts, not revision content. | **No.** The correction action writes revision content only; operational corrections go through a change request that the partner actioned. |
| Q4 | Should a partner be allowed to add/remove/retire **options** on a published tour through the normal `PUT` (commercial) while their titles are review-gated? | Splitting one array across two governance paths is unusual. | **Yes, as designed.** Option identity/commercial fields stay live; option authored text goes through the revision. A `PUT` that changes an option title on a published tour is refused with 422 and pointed at the revision endpoint. |
| Q5 | Should a **change request** be allowed on a `draft` tour (no approved revision)? | The owner policy speaks of admins requesting changes; it does not restrict the tour state. | **Yes**, and the partner resolves it in the normal draft flow (no revision needed). The request is recorded for the audit trail and the partner inbox. |
| Q6 | Should the exceptional correction be allowed on a `pending_review` or `rejected` tour? | Not specified. | **No** — corrections target `published` tours only; for other states the ordinary change-request path applies. |
| Q7 | Should a partner's *first* published revision be pre-seeded automatically, or must the backfill run? | Migration/data volume is unknown. | **Baseline lazily on first use** (the correction/approval path creates a `baseline` revision under the tour lock if `published_revision_id` is null) **plus** the bounded backfill command for completeness. |

---

## 20. Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Materialization diverges from the hash the derivative pipeline expects | Derivatives never become current; the public permanently falls back | AC-006 pins hash parity; the approval path computes the hash from the materialized rows |
| A candidate leaks into the public projection | P4 violated | Candidates are never written to live tables; AC-001 and AC-017 are permanent regressions |
| Concurrent approvals double-apply content | Data corruption | CAS on `base_revision_id` + materialization inside one transaction; AC-008 uses real PostgreSQL races |
| The Filament repeater is removed before the correction path ships | Admins lose the ability to edit content | Ship S4 (actions) **with** S5 (repeater removal); S5 may not ship alone |
| The release-B `PUT` refusal breaks the deployed frontend | Partner edits fail | Release ordering A → frontend → B, with the `capabilities.revision_review` flag; AC-024 |
| Derivative generation for the new revision fails | Public falls back to the new approved source with a notice | Existing constitution behaviour; generation failure never blocks approval; the reconciliation command re-queues |
| Backfill on a large production dataset is slow or partially applied | Inconsistent `published_revision_id` | Bounded, resumable, idempotent, `--dry-run` first; the lazy baseline covers any gap |

---

## 21. Files written by this dispatch

| File | Purpose |
|---|---|
| `docs/designs/supplier-portal-egm003-revision-governance.md` (new) | This design |
| `specs/019-tour-content-data/spec.md` | FR-031…FR-040, SC-009…SC-011, EGM-003 decision record |
| `specs/019-tour-content-data/data-model.md` | New tables, column, payload tiers, state machines |
| `specs/019-tour-content-data/contracts/partner-tour-content-api.md` | Revision endpoints, error codes, published-tour `PUT` rule |
| `specs/019-tour-content-data/contracts/translation-generation.md` | Revision-bound generation and legacy field-engine retirement |
| `specs/020-tour-detail-experience/spec.md` | FR-035, SC-010 |
| `specs/018-bookly-ui-redesign/spec.md` | FR-034, SC-012 |
| `docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md` | EGM-003 decision and A4 status |
| `docs/reports/supplier-portal-v3-goldfish-review.md` | Dated owner decision; **EGM-003 explicitly not closed** |

No code, migration, schema, test, or configuration file was created or modified. Nothing was committed. No git state was reset, stashed, cleaned, restored, or switched.
