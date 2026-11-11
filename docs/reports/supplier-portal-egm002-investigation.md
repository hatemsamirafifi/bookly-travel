# EGM-002 Phase 1 Investigation — Tour Publication Gate

**Task owner** EGM-002 Phase 1 (backend/tests/Feature/Admin/Filament/TourPublicationGuardTest.php — new; this report — new)
**Date** 2026-11-11
**Governance** Constitution v3.0.0 (EGM-001 Option B, last amended 2026-11-10); [Supplier Portal Goldfish Review](supplier-portal-v3-goldfish-review.md) (EGM-002 · Blocker · A4 publication bypass)
**Dispatch scope** Inventory of every tour-publishing entry point and bypass **before** any production modification; permanent expected-security Livewire regressions first; **no production code changes, no policy decisions on admin translations (EGM-003)**.

---

## 1. Method and sources read

This is the dated, pre-fix investigation. The final implementation and verification are recorded in the [publication follow-up](supplier-portal-v3-goldfish-review.md#egm-002-publication-follow-up-2026-11-11). Nothing was restored, stashed, reset, or cleaned during this dispatch; the original dispatch wrote the two files listed in §8.

| Source | Purpose |
|---|---|
| `.specify/memory/constitution.md` (v3.0.0) | §II publishing integrity, lifecycle matrix; §IV strict authorization, thin Filament actions; §VII regressions-before-fix; Security § audit mandate |
| `docs/reports/supplier-portal-v3-goldfish-review.md` | EGM-002 finding A4/Filament probe; EGM-003 finding; A4 gap matrix |
| `backend/app/Filament/Resources/TourResource.php` | Form fields, table actions, bulk actions, infolist |
| `backend/app/Filament/Resources/TourResource/Pages/{CreateTour,EditTour,ListTours,ViewTour}.php` | Record create/edit lifecycle hooks |
| `backend/app/Domains/Admin/Actions/ApproveTourAction.php` (+`RejectTourAction`, `UnpublishTourAction`, `ArchiveTourAction`) | Guarded transition actions |
| `backend/app/Domains/Admin/Policies/TourPolicy.php` | Per-action gates |
| `backend/app/Domains/Admin/Services/AdminAuthorizationService.php` | Permission flags / super_admin semantics |
| `backend/app/Domains/Admin/Services/GovernanceAuditService.php` | Audit writer (+ ip/ua metadata) |
| `backend/app/Models/Tour.php` | Lifecycle `canTransitionTo`, `isPubliclyBookable`, source-locale, fillables |
| `backend/app/Domains/Partner/{Services/TourService.php,Controllers/TourController.php,Requests/*}` | Partner status handling |
| `backend/routes/api/partner.php`, `backend/routes/api/admin.php` | Surface boundaries |
| `backend/app/Providers/Filament/AdminPanelProvider.php` | Panel middleware (auth-only + policy resolution) |
| `backend/app/Filament/Resources/AvailabilityResource.php` | Confirmed read-only (no tour mutation) |
| `backend/app/Console/Commands/*` | Confirmed: no tour status writers |
| `vendor/filament/...` `EditRecord::save`, `TestsForms::fillForm`, `Repeater` | Mechanism analysis for the stale-save and test-harness behavior |
| Existing tests: `TourResourceTest`, `TourPublishingSecurityTest`, `MarketplaceOperationsTest`, `Admin2SecurityBlockersTest`, `tests/Pest.php`, `tests/bootstrap.php`, `phpunit.xml` | Reusable patterns + harness constraints |

The initial grep sweep identified direct `tours.status` writes in
`App\Domains\Admin\Actions\*Tour*Transition*` actions (`TourStatus::Published/Rejected/Draft/Archived`) and
`App\Domains\Partner\Services\TourService.php` (`draft` L127/L547, `pending_review` L523) —
plus **the general Filament create/edit form data itself** (§3 rows 3–4). This initial sweep was incomplete: the web-triggered demo seeder is another runtime gateway, documented in §3 before production changes.
`backend/app` console commands never touch tour status (`PublishScheduledContentCommand` covers `StaticPage`/`BlogPost` only). No route under `routes/api/admin.php` writes tours. Payment/booking webhooks and jobs never write tour status.

---

## 2. Lifecycle contract being enforced (target of the gate)

`Tour::canTransitionTo()` (`app/Models/Tour.php:798-820`):

```
draft → pending_review            (partner submission)
pending_review → published | rejected   (admin; published additionally requires partnerRecord.onboarding_status === 'approved' and a non-blank source title/description at the approval action)
rejected → pending_review
published → archived | draft      (admin)
archived → []                     (terminal)
```

A tour is publicly bookable iff `status === 'published'` **and** valid pricing **and** upcoming availability (`isPubliclyBookable`, `app/Models/Tour.php:227-232`) — `published_at` is *not* part of the bookability gate, but the guarded action stamps it (`ApproveTourAction.php:50-53`) and the tour-detail endpoint keys 410-vs-404 semantics off it.

---

## 3. Entry-point inventory (every production writer of tour lifecycle state)

| # | Entry point | Reaches | Lifecycle matrix | Source validation | Partner-approval gate | `published_at` | Actor authorization | Audit event |
|---|---|---|---|---|---|---|---|---|
| 1 | Filament row action `publish` (`TourResource.php:235-246`) | `ApproveTourAction` | ✔ (action) | ✔ (action) | ✔ (action) | ✔ | ✘ trusts actor; **`visible()` only** at callback | ✔ `tour.publish` |
| 2 | Filament bulk `bulk_publish` (`TourResource.php:298-318`) | loop → `ApproveTourAction` per record | ✔ each | ✔ each | ✔ each | ✔ each | ✘ trusts `$actor = auth()->user()`; **`visible()` only** | ✔ per record |
| 3 | Filament general **create** page (`CreateTour` → `CreateRecord::create`) | raw `Tour::create($formState)` incl. `status` select (`TourResource.php:93-102`) | ✘ any status | ✘ (repeater title/description `required` only — blanks/whitespace/unbacked content pass) | ✘ (partner select lists all partners, unapproved included) | ✘ never stamped | ✔ policy `create` = `manage_tours` only | ✘ **none** |
| 4 | Filament general **edit** page (`EditTour::save` → `handleRecordUpdate`) | raw `$record->update($formState)` incl. `status` select | ✘ any status | ✘ | ✘ | ✘ | ✔ policy `update` = `manage_tours` only | ✘ **none** |
| 5 | Filament row/bulk `reject`, `unpublish`, `archive` (`TourResource.php:247-295`, `319-346`) | `Reject`/`Unpublish`/`ArchiveTourAction` | ✔ | n/a (un-pub) | n/a | n/a | ✘ trusts actor; **`visible()` only** | ✔ dedicated events with reason |
| 6 | Filament `DeleteAction` (edit header) | Eloquent delete | n/a (row removal, not a transition) | n/a | n/a | n/a | ✔ `delete` = `manage_tours` | (model delete — outside publication gate; noted) |
| 7 | Partner API `POST tours` (`routes/api/partner.php:43` → `TourService::createTour`) | forced `'status' => 'draft'` (`TourService.php:127`); `status` **`prohibited`** at `StoreTourRequest.php:67`; `forbiddenPresentPaths` blocks platform-owned keys | ✔ by construction | ✔ source boundary | n/a (draft) | n/a | ✔ partner middleware + scoping | (draft create — not a governance transition) |
| 8 | Partner API `PUT tours/{id}` (`TourController.php:61-80` → `updateTour`) | lifecycle fields unreachable; `status` **`prohibited`** (`UpdateTourRequest.php:86`); published/pending source-clearing refused (`TourService.php:209-225`) | ✔ by construction | ✔ | n/a | unreachable | ✔ scoped-404 ownership (`requireOwnedTour`) | (content save — EGM-005 revision design) |
| 9 | Partner API `POST {id}/submit` (`TourController.php:132-171` → `submitForReview`, `TourService.php:499-529`) | `'status' => 'pending_review'` | ✘ **no `canTransitionTo` check** — e.g. `published → pending_review` (matrix only allows `published → archived|draft`) and `archived → pending_review` are accepted | ✔ source title/description | n/a | n/a | ✔ partner-scoped | ✘ **no audit event** |
| 10 | Partner API `DELETE {id}` / `POST {id}/archive` (`TourController.php:82-94,173-188` → `archiveTour`, `TourService.php:531-539`) | `'status' => 'archived'` | ✘ no lifecycle check (e.g. `draft → archived` is out of matrix) | n/a | n/a | n/a | ✔ partner-scoped | ✘ **no audit event**; `archived_at`/`submitted_at` are **not** fillable on `Tour` so their writes are silently dropped |
| 11 | Partner drafts `POST/GET {id}/drafts/**` | `tour_drafts` only | n/a (snapshot payload; materialization goes through #7/#8) | n/a (validated on materialization) | n/a | n/a | ✔ | n/a |
| 12 | Admin REST (`routes/api/admin.php`) | **no tour endpoints** | — | — | — | — | — | — |

**Additional runtime gateway found during independent inventory, before production changes.** `app/Filament/Pages/DemoContentManager.php` exposes a `generate` action that calls `PartnerSeeder`, `BlogContentSeeder`, and `DemoContentSeeder`. The tour seeders write `published` directly. Its page allows any admin and does not require `manage_tours`. Therefore the web-triggered seeder path is a publication bypass, even though an operator's standalone CLI seeding and factory setup are outside the runtime approval workflow. Permanent real Livewire regressions in `DemoPublicationGuardTest.php` fail for both a tour-managing and a flagless admin. A temporary server-side refusal of this action is needed because it also rewrites source and derivative data covered by unresolved EGM-003.

**Setup writers outside the web-triggered gateway:**
`database/seeders/PartnerSeeder.php:104`, `DemoContentSeeder.php:192/218/250/276/308/334`, `AdminE2EFixtureSeeder.php:236`, test helpers (`tests/Pest.php:118,290` etc.), and the historical spec-019 evidence artifact `specs/019-tour-content-data/evidence/final-review-20261010/live-local/proof.php:36,71` (not invoked at runtime).

**Read-only confirmations:** `AvailabilityResource` is view-only (`canCreate/edit/delete` all `false`, `AvailabilityResource.php:60-78`); `PartnerResource` and `BlogPostResource` consume `tour_id` read-only; `ViewTour` has no write actions; `Tour::booted()` cache hooks never mutate status.

---

## 4. Bypass catalog (what the entry points allow today)

**B1 — General create publishes (`TourResource.php:93-102`, `CreateTour.php`).** The `status` Select offers `published`; `CreateRecord::create` writes the form state verbatim. A crafted create saves a brand-new tour as `published` with **no lifecycle check, no source validation (only field-`required`), no partner-approval gate (any partner selectable, unapproved included), no `published_at` stamp, and no `tour.publish` audit event**. Because `isPubliclyBookable()` keys off `status` alone, the ungated record is publicly bookable the moment it exists.

**B2 — General edit publishes (EGM-002 goldfish probe, `EditTour.php` + `EditRecord::save`).** Mounting the edit page and saving with `data.status = 'published'` flips the stored status through `handleRecordUpdate` with the same four gaps. The goldfish probe reproduced this on an `es`-source draft saved to `published` with **no `tour.publish` event recorded**. `EditRecord::authorizeAccess()` resolves only `TourPolicy::update` = `manage_tours`; every other gate the guarded action enforces (lifecycle matrix, source content, partner approval, stamp, audit) is absent.

**B3 — Stale record save retracts publication.** The edit form captures `status` at mount. `EditRecord::save()` updates `$this->getRecord()` (the mounted instance) with the stale form state; there is no version/staleness reconciliation. If the tour is legitimately published (guarded action) while an editor is open, saving that editor silently rewrites `published → pending_review` — an ungoverned unpublish with no audit event and no `UnpublishTourAction` semantics (verified in vendor `vendor/filament/filament/src/Resources/Pages/EditRecord.php:416-453`).

**B4 — Ownership reassignment on published tours.** The edit form's `partner_id` Select (`TourResource.php:87-92`) lists every partner, including unapproved ones. `TourPolicy::update` is flag-only; nothing at save time re-checks the target partner's `onboarding_status`. A `published` tour can be moved under an unauthorized partner while staying live — violating Constitution §I (“Every publicly bookable tour MUST belong to an authorized partner”).

**B5 — Source replacement / clearing through the translations repeater (`TourResource.php:133-158`).** The repeater writes `tour_translations` rows directly (`relationship('translations')`): it can blank the source title/description, rename a row's `locale`, delete the source row, or invent rows out of the source/derived model entirely. The approve-time source validation runs **only inside the approval action**; a later general edit can empty the source of an already-live tour with no guard. This is also the structural anchor of **EGM-003** (§5) — the same repeater bypasses the partner content boundary and the translation workflow.

**B6 — Individual/bulk callbacks: visibility is not authorization (`TourResource.php:242,260,272,291,303,324`).** The governance table actions carry `->visible(...)` checks only; the action closures then call the domain actions with `auth()->user()`. Today exploitation is blocked *incidentally* by the page gate (`TourPolicy::viewAny` = the same `manage_tours` flag on `ListTours` mount) — but no defense-in-depth exists at the callback/action level. Any future weakening of the page gate, a flag revoked between mount and call (bulk loop), or a non-Filament caller reaches the write. (`Admin2SecurityBlockersTest`-style HTTP checks cover only the page render.)

**B7 — Domain actions trust the actor (`ApproveTourAction.php:22-60`; same shape in `RejectTourAction`, `UnpublishTourAction`, `ArchiveTourAction`).** `execute(User $actor, Tour $tour)` never consults `AdminAuthorizationService`; `TourPolicy` is applied only by Filament's facade. Any caller — another domain service, a future console command, tests, or code that passes a partner-role `User` (or a flagless admin, e.g., mid-bulk-loop revocation) — publishes/rejects/unpublishes/archives without authorization. `Tour::canTransitionTo()` blocks *lifecycle* abuse, but the actor is never authenticated against permission flags. Covered by the two direct-action regressions (§6, tests 6–7).

**B8 — Adjacent partner-side lifecycle gaps (inventory per dispatch; not a publish-**into**-visibility bypass).** Partner `submit` (#9) and `archive` (#10) skip the lifecycle matrix, write ungoverned downgrades (`published → pending_review`) and unaudited lifecycle moves; `archived_at`/`submitted_at` writes are silently dropped because they are not in `Tour::$fillable` (`app/Models/Tour.php:125-145`), so even the intended timestamps never persist. Constitution (“Audit records are mandatory for … tour publishing decisions”) requires audit coverage here. Recommended as its own follow-up (A4/EGM-005 window), **not** silently bundled into the EGM-002 fix.

**`published_at` integrity across all ungated writes.** The general form never collects or stamps `published_at`; only the guarded action does. Every ungated publication therefore produces a live, bookable, publicly-listed tour with `published_at = null` — inconsistent with the guarded-action contract the tour-detail 410/404 semantics consume.

---

## 5. EGM-003 intersection (explicit, deferred by this dispatch)

| Surface | Where | Intersection with EGM-002 |
|---|---|---|
| Repeater writes translation rows directly | `TourResource.php:135-157` | Bypasses `TourService`/`TourContentRules` (no forbidden-path scan, no source-locale lock). The same general form that bypasses the publication gate also authors canonical *and* derivative content rows arbitrarily. **B5 and EGM-003 are one structural surface**; Phase 2 must not “fix” publication by silently deciding translation policy. |
| Save hook reconciliation | `CreateTour.php:14-20`, `EditTour.php:61-70` → `AutoTranslationService::syncAndDispatch` | Bookkeeping runs **only** for English-source tours with no configured options (`AutoTranslationService.php:126-128` guard). For non-`en` sources or option-configured tours a general save performs **zero** translation governance — derivative/state rows can be forged directly by the repeater. |
| Derivative freshness | `TourTranslationService::currentTranslation` (`:631-651`) | Public projection already falls back to the source unless the derivative is *genuinely* current (hash equality); the statically-inspected residual risk (a hand-edited derivative retaining `ready` while hashes coincide) is an EGM-003 integrity item, not a publication one. |

**No policy decision is taken here.** Whether admins may edit canonical content, and whether any derivative editing is permitted under the approved policy (revision validation, regeneration, authorization, audit; treatment of edits to already-published content before review snapshots exist) remains the open owner decision recorded by the goldfish review. The Phase-1 tests were deliberately written so they **do not predicate on derivative-state policy** (derivative readiness must not block publication — Constitution amendment / T036).

---

## 6. Regressions delivered (Phase 1, no production change)

**File** `backend/tests/Feature/Admin/Filament/TourPublicationGuardTest.php` (new, Pest, `RefreshDatabase`, patterns reused from `TourResourceTest.php` and `TourPublishingSecurityTest.php`). Expected-security style: every test asserts the **fail-closed constitutional property**, never a successful exploit; a compliant fix shape either **(A)** forbids lifecycle writes from the general form entirely, or **(B)** routes them through the guarded semantics (lifecycle matrix + source validation + partner gate + `published_at` stamp + `tour.publish` audit) — the assertions hold under both, so they can stay permanently green.

| # | Test (dataset cases) | Bypass encoded | Security assertion |
|---|---|---|---|
| 1 | `EGM002 refuses the crafted published status on the general Filament create form` ×3 (unapproved partner · blank source description · valid source + approved partner) | B1 | No tour exists in `published` state for the crafted slug; any record that was persisted carries `published_at = null` and **no** `tour.publish` audit. Fills the real repeater item key from the live form state so pre-fix failures prove the exploit executed (not a form-validation artifact). |
| 2 | `EGM002 refuses the crafted published status on the general Filament edit form` ×5 (`es`-source draft (exact goldfish probe) · `es`-source draft blank description · unapproved partner · rejected pre-state · archived pre-state) | B2 (+partner/source matrix variants) | Record never `published`; `published_at` null; no `tour.publish` audit; final state stays inside the lifecycle matrix. |
| 3 | `EGM002 publishing from the edit form must carry the full guarded semantics when allowed` (`pending_review` + approved partner + complete `es` source) | B2, compliant-publish branch | If published → must have `published_at` stamp **and** `tour.publish` audit (guarded semantics); if refused → stays `pending_review`, unstamped, no audit. |
| 4 | `EGM002 a stale edit save must not silently retract a legitimately published tour` | B3 | Publish via the guarded action while the editor is open; stale save must leave status `published`, stamp intact, and no `tour.unpublish` event substituted for the governed transition. |
| 5 | `EGM002 a published tour must never end up owned by an unapproved partner via the edit form` | B4 | Post-save invariant: not(`status = published` AND target partner unapproved) — holds under refusal *or* de-publication fixes. |
| 6 | `EGM002 the guarded approval action must refuse a non-admin actor` | B7 | `ApproveTourAction::execute($partnerActor, …)` → tour unpublishable, unstamped, no audit; refusal (if thrown) must be an authorization/HTTP exception, never an unrelated failure. |
| 7 | `EGM002 the guarded approval action must refuse an admin without the manage-tours flag` | B7 (+B6 defense-in-depth) | Same shape with a flagless admin — this is exactly the mid-bulk-loop revocation principal, since the bulk loop passes `auth()->user()` per record into the action. |

Pre-fix expectation: **13 failing entries** (3 + 5 dataset results + 4 single tests). Distinguishing real failures from harness breakage: a genuine expected failure reads like *"General Filament edit saved a 'es-source draft' tour as published …"*; a failure **inside form validation** or "no tour exists" on the create cases would indicate a harness problem to report back, not a security pass. No skips, no weak assertions, no exploitation asserted as desired behavior.

### How to run (parent; serial, disposable PostgreSQL harness)

```powershell
cd backend
php artisan test tests/Feature/Admin/Filament/TourPublicationGuardTest.php
# or: vendor/bin/pest tests/Feature/Admin/Filament/TourPublicationGuardTest.php
```

Constraints honored: `tests/bootstrap.php` pins `pgsql @ bookly-test-postgres/bookly_test` (single suite owns the disposable DB unless `TEST_TOKEN` present); harness uses `QUEUE_CONNECTION=sync` + `SCOUT_DRIVER=collection`, so the tests use `Queue::fake()` where saves dispatch translation jobs (matching the T036 pattern) and create no external traffic. `php -l` passed (Pint is not installed in this working copy's `vendor/`; file follows the Laravel preset + local `pint.json` rules; PHPStan analyzes `app/` only).

---

## 7. Recommended minimal production changes (Phase 2 — not applied here)

Ordered, smallest-first; every option is fail-closed by construction and does not pre-decide EGM-003.

1. **G1 (recommended minimum): strip lifecycle status from the general TourResource form.** Replace the editable `status` Select (`TourResource.php:93-102`) with a disabled read-only display, and force the lifecycle state at the page hooks: `CreateTour::mutateFormDataBeforeCreate()` sets `'status' => 'draft'` (unset `published_at`), `EditTour::mutateFormDataBeforeSave()` resets `'status'` to the *freshly-read* stored value and does not write `published_at`. Publication/unpublish/archive remain exclusively via the dedicated guarded actions (#1, #2, #5), which already validate lifecycle + source content, stamp `published_at`, and write governance audit events. Closes B1, B2, B3 (stale saves can no longer touch lifecycle at all), and the publish half of B6 in one small, thin-controller-conformant change.
2. **G2: actor authorization inside the transition actions.** In `ApproveTourAction` (and symmetrically `Reject`/`Unpublish`/`Archive`), assert `AdminAuthorizationService::can($actor, 'manage_tours')` for the **passed actor** before mutating; throw `AuthorizationException` (403) otherwise. Closes B7 and gives B6 real defense-in-depth for bulk loops and any future caller without weakening the action signature.
3. **G3: guarded ownership on live records.** At the general save boundary (G1's hook or `TourPolicy`), refuse partner reassignment on records currently `published` (or require the target partner's `onboarding_status === 'approved'`). Closes B4 regardless of which sub-option is chosen.
4. **G4 (separate follow-up, don't bundle silently): partner-side lifecycle + audit.** Route partner `submit`/`archive` through `canTransitionTo()` with governance audit events (`partner.submit`, `partner.archive`), and make `submitted_at`/`archived_at` fillable or drop those fields intentionally. Pair with the A4 review-snapshot / EGM-005 contract design.
5. **Not recommended as the sole fix:** making the general form route publishes through `ApproveTourAction` (Option B) is acceptable only if every save with a status field goes through the full guarded semantics; G1 is smaller, keeps Filament pages thin, and avoids session-versus-actor ambiguity inside domain actions (a fix that authorizes via `auth()->user()` instead of the `$actor` parameter would fail regressions #6/#7 deliberately).

**Explicit non-goals (this dispatch):** any change to admin-translation editing rights or the repeater's translation semantics (EGM-003 owner decision outstanding); UI/UX changes beyond the minimal guard; partner lifecycle redesign (G4); restoring any git state.

---

## 8. Files written by this dispatch

| File | Purpose |
|---|---|
| `backend/tests/Feature/Admin/Filament/TourPublicationGuardTest.php` | Permanent expected-security Livewire/Action regressions (§6) |
| `docs/reports/supplier-portal-egm002-investigation.md` | This investigation |

Workspace safety: 374 pre-existing modified/untracked entries verified at start; no `restore`/`stash`/`reset`/`clean`/`stage`/`commit` executed; production code untouched.

## Independent Phase 1 execution evidence

Before any production changes, the existing four moderation, audit and Filament suites completed with 65 cases and 297 assertions, with environment warnings. The copied runner initially lacked `.env`, which was corrected inside the disposable container. Pest also reports its read-only vendor result-cache warning.

The first new suite run exposed a missing `TourResource` import in the test harness. After that import was corrected, the 13 expected-security cases failed with 24 assertions against unchanged production code. The demo action's two cases separately failed because the action returned 200 instead of refusing with 403. These runs used real Filament Livewire components and disposable PostgreSQL.

```sh
php vendor/bin/pest tests/Feature/Admin/Filament/TourPublicationGuardTest.php --compact --do-not-cache-result
php vendor/bin/pest tests/Feature/Admin/Filament/DemoPublicationGuardTest.php --compact --do-not-cache-result
```

The inventory also identifies a lifecycle route back to publication. `TourService::submitForReview` can currently move an archived tour back to pending review, after which normal approval can publish it. The publication fix must reject submission from archived or already-published states and stop the general form from rewriting lifecycle state. Partner archive behavior itself remains a separate concern.

The chosen general-form behavior is to reject attempted lifecycle changes server-side, preserve the current persisted status on a valid metadata save, and leave publication to the dedicated approval action. The form transaction must acquire the current tour row before saving relationships. A temporary fail-closed validation must prevent a published form save from deleting or blanking its canonical source or assigning an unapproved owner. Other existing translation editing behavior remains under EGM-003 pending owner approval.

The pre-change full PHPStan run found 21 existing errors in booking, partner, search and option code. Its output is recorded separately so the final analysis can distinguish existing errors from errors introduced by this fix.

The expanded `TourPublicationInvariantsTest.php` initially had a fixture missing the schema-required `duration_minutes` value. That fixture was corrected before counting security results. Against the original copied production snapshot, the corrected suite passed 42 cases and failed 18 with 285 assertions. The failures covered stale authorization, per-item bulk authorization, destructive published-source saves, forced form publication, stale republishing, and invalid review resubmission. The passing cases established the existing valid source, derivative-independent approval, timestamp, and audit behavior to preserve.
