# Tasks: Admin Moderation

**Input**: Design documents from `/specs/013-admin-moderation/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/
**Constitution**: v1.1.0 — critical flows (tour publishing approval, auth/authz gates) require automated tests before completion.

**Organization**: Tasks grouped by user story (spec.md US1–US9) for independent implementation/testing. Admin surface is Laravel Filament (server-rendered) — the ratified exception to API-first for the internal admin surface (constitution v1.1.0, API-First §Internal Admin Exception).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: parallelizable (different files, no deps on incomplete tasks)
- **[Story]**: user story (US1–US9); Setup/Foundational/Polish have NO story label
- Exact file paths included; `[ST-013-xxx]` = Stitch admin screen traceability ID

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Admin domain scaffolding, new tables, seeders.

- [x] T001 Create Admin domain structure: `backend/app/Domains/Admin/{Actions,Services,Models,Settings,Policies,Requests,Enums}/` directories
- [x] T002 Create migration `backend/database/migrations/2026_06_20_100001_create_governance_audit_logs_table.php` (append-only; see data-model.md §1)
- [x] T003 [P] Create migration `backend/database/migrations/2026_06_20_100002_create_admin_permissions_table.php` (user_id unique, flags jsonb)
- [x] T004 [P] Create migration `backend/database/migrations/2026_06_20_100003_create_static_pages_table.php` (localized JSONB title/body/meta)
- [x] T005 Create seeder `backend/database/seeders/AdminUserSeeder.php` — admin user + full permission flags (see quickstart.md)

**Settings infrastructure (spatie/laravel-settings — US9/FR-015, see data-model.md §4)**:
- Install & configure: `composer require spatie/laravel-settings`; publish
  `config/settings.php`; register the four settings classes
  (`GeneralSettings`, `SeoSettings`, `ContactSettings`, `BookingSettings`).
- Settings table migration: publish the package `create_settings_table`
  migration (`vendor:publish`); it creates the `settings` table owned by the
  package (no custom key/value table).
- Settings migrations: add `backend/database/settings/` migrations seeding
  default values per group (one file per settings class, sequential timestamps:
  `…100000_…general…`, `…100001_…seo…`, `…100002_…contact…`,
  `…100003_…booking…`) — run via the standard `php artisan migrate`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared services, authorization, enums, and audit that EVERY user story depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T006 Create `GovernanceAuditLog` model in `backend/app/Domains/Admin/Models/GovernanceAuditLog.php` (append-only: `UPDATED_AT=null`, boot guards against update/delete; morph maps for actor/target)
- [x] T007 Create `GovernanceAuditService` in `backend/app/Domains/Admin/Services/GovernanceAuditService.php` — `log(actor, action, target, before, after, metadata)` (FR-011/FR-012)
- [x] T008 Create `AdminPermission` model in `backend/app/Domains/Admin/Models/AdminPermission.php` (flags jsonb cast; `User::hasOne(AdminPermission)`)
- [x] T009 Create `AdminAuthorizationService` in `backend/app/Domains/Admin/Services/AdminAuthorizationService.php` — `can(User, string $flag): bool` (role=admin + flag check)
- [x] T010 Extend `backend/app/Policies/UserPolicy.php` `hasPermission()` to delegate to `AdminAuthorizationService` (persisted flags, seed inventory in data-model.md §2)
- [x] T011 [P] Create enum `backend/app/Enums/TourStatus.php` (draft/pending_review/published/rejected/archived)
- [x] T012 [P] Create enum `backend/app/Enums/PartnerStatus.php` (pending/approved/rejected/suspended)
- [x] T013 [P] Create enum `backend/app/Enums/ReviewStatus.php` (visible/hidden/flagged)
- [x] T014 Add `Tour::canTransitionTo()` guard in `backend/app/Models/Tour.php` (publish blocked if partner not approved — FR-005)
- [x] T015 Add `Partner::canTransitionTo()` + suspend/reinstate visibility hooks in `backend/app/Domains/Partner/Models/Partner.php` (FR-006)
- [x] T016 Add `Review::canTransitionTo()` in `backend/app/Domains/Reviews/Models/Review.php`
- [x] T017 Add `Booking::canTransitionTo()` non-financial guard in `backend/app/Domains/Booking/Models/Booking.php` (FR-009)
- [x] T018 Register "Content" nav group + new resources/pages in `backend/app/Providers/Filament/AdminPanelProvider.php` (discover Resources/Pages)
- [x] T019 Add per-resource `Policy` registration so Filament gates call `AdminAuthorizationService` (basis for FR-002)

**Checkpoint**: Foundation ready — governance audit + authorization + status enums in place.

---

## Phase 3: User Story 1 — Admin Access Control (Priority: P1) 🎯 MVP

**Goal**: Only admins reach the panel; per-action permissions enforced (FR-001, FR-002).
**Independent Test**: Non-admin hits `/admin` → denied; admin signs in → panel home; admin without a permission sees that action hidden.

- [x] T020 [US1] Verify/extend `User::canAccessPanel()` in `backend/app/Models/User.php` returns `role === 'admin'` only (FR-001)
- [x] T021 [US1] Wire Filament resources to per-action gates via policies in `backend/app/Domains/Admin/Policies/` (disallowed actions hidden, not server-rejected — FR-002 scenario 4)
- [x] T022 [US1] Add `RoleMiddleware` (`role:admin`) coverage assertion for `/api/admin/*` in `backend/routes/api/admin.php` (already applied; add test guard)
- [x] T023 [US1] Write feature test `backend/tests/Feature/Admin/AdminPermissionsTest.php` — 403/redirect for non-admin, hidden action for missing flag

**Checkpoint**: US1 functional — panel access + per-action permissions enforced.

---

## Phase 4: User Story 2 — Tour Moderation & Publishing Approval (Priority: P1)

**Goal**: Approve/reject/unpublish tours via Filament with audit + transition guards (FR-003 to FR-005, FR-016). [ST-013-006]

**Independent Test**: Partner submits tour → admin approves → tour public + audited; reject with reason; publish blocked for unapproved partner.

- [x] T024 [P] [US2] Create `ApproveTourAction` in `backend/app/Domains/Admin/Actions/ApproveTourAction.php` (assert guard, set published, write `tour.publish` audit)
- [x] T025 [P] [US2] Create `RejectTourAction` + `RejectTourRequest` in `backend/app/Domains/Admin/Actions/RejectTourAction.php` (reason required, write `tour.reject` audit)
- [x] T026 [P] [US2] Create `UnpublishTourAction` in `backend/app/Domains/Admin/Actions/UnpublishTourAction.php` (published→draft, write `tour.unpublish` audit)
- [x] T027 [US2] Extend `backend/app/Filament/Resources/TourResource.php` — call the three Actions (no direct `update()`); add `bulk_reject`; per-item audit (FR-016)
- [x] T028 [US2] Write feature test `backend/tests/Feature/Admin/TourModerationTest.php` — approve/reject/unpublish, FR-005 block, audit entries
- [x] T029 [US2] Write Filament test `backend/tests/Feature/Admin/Filament/TourResourceTest.php` — table actions + bulk_publish/bulk_reject

**Checkpoint**: US2 functional — tour publishing fully governed + audited.

---

## Phase 5: User Story 3 — Partner Approvals & Lifecycle (Priority: P1)

**Goal**: Approve/reject/suspend/reinstate partners with audit + visibility side-effects (FR-006, FR-007). [ST-013-005]

**Independent Test**: Approve partner → tours publishable; suspend → tours leave discovery; all audited + notified (Spec 014).

- [x] T030 [P] [US3] Create `ApprovePartnerAction` in `backend/app/Domains/Admin/Actions/ApprovePartnerAction.php` (mail via Spec 014, write `partner.approve` audit)
- [x] T031 [P] [US3] Create `RejectPartnerAction` + `RejectPartnerRequest` in `backend/app/Domains/Admin/Actions/RejectPartnerAction.php` (reason, mail, audit)
- [x] T032 [P] [US3] Create `SuspendPartnerAction` in `backend/app/Domains/Admin/Actions/SuspendPartnerAction.php` (remove tours from discovery, audit)
- [x] T033 [P] [US3] Create `ReinstatePartnerAction` in `backend/app/Domains/Admin/Actions/ReinstatePartnerAction.php` (restore, audit)
- [x] T034 [US3] Extend `backend/app/Filament/Resources/PartnerResource.php` — call the four Actions (no direct `update()`); remove all bulk actions (FR-016 — bulk is limited to tours + reviews only)
- [x] T035 [US3] Write feature test `backend/tests/Feature/Admin/PartnerModerationTest.php` — lifecycle transitions, suspension discovery effect, audit, notification dispatch

**Checkpoint**: US3 functional — partner lifecycle governed + audited.

---

## Phase 6: User Story 4 — Booking Oversight & Status Management (Priority: P2)

**Goal**: List/filter/inspect bookings; non-financial status transitions; financial side-effects delegate to Spec 008 (FR-008, FR-009). [ST-013-007/008]

**Independent Test**: Filter bookings; transition confirmed→completed (audited); refund-requiring transition delegates to 008 and logs status only.

- [x] T036 [P] [US4] Create `TransitionBookingStatusAction` + `TransitionBookingStatusRequest` in `backend/app/Domains/Admin/Actions/TransitionBookingStatusAction.php` (non-financial only; financial side-effect → dispatch to Spec 008; write `booking.transition` audit)
- [x] T037 [US4] Extend `backend/app/Filament/Resources/BookingResource.php` — add allowed transition actions; fix `actor_description` accessor / route audit through `GovernanceAuditService`; no Create, no bulk
- [x] T038 [US4] Write feature test `backend/tests/Feature/Admin/BookingStatusTransitionTest.php` — allowed transitions, blocked financial execution, audit, 403 non-admin

**Checkpoint**: US4 functional — booking oversight with safe, audited transitions.

---

## Phase 7: User Story 5 — Review Moderation (Priority: P2)

**Goal**: Hide/reinstate reviews (Filament + existing API); recompute aggregate ratings; audit (FR-010, FR-016). [ST-013-009]

**Independent Test**: Hide review → disappears from public tour detail + rating recomputes; reinstate → reappears; audited.

- [x] T039 [P] [US5] Extend existing `HideReviewAction`/`ReinstateReviewAction` in `backend/app/Domains/Reviews/Actions/` to inject `GovernanceAuditService` directly and write unified `review.hide`/`review.reinstate` audit entries (fix actor morph map). Note: actions are enhanced in-place (not wrapped from Admin domain) — they remain in the Reviews domain but depend on the Admin audit service.
- [x] T040 [US5] Create `ReviewResource` in `backend/app/Filament/Resources/ReviewResource.php` — list/view, hide/reinstate actions, `bulk_hide`/`bulk_reinstate`, filters (status/tour/date/flagged), `moderate_reviews` gate
- [x] T041 [US5] Extend `backend/app/Domains/Reviews/Policies/ReviewPolicy.php` (or `backend/app/Policies/ReviewPolicy.php`) to per-action flags via `AdminAuthorizationService`
- [x] T042 [US5] Write feature test `backend/tests/Feature/Admin/Filament/ReviewModerationFilamentTest.php` — hide/reinstate, aggregate recompute, bulk, audit

**Checkpoint**: US5 functional — review moderation via Filament + API, audited.

---

## Phase 8: User Story 6 — Audit Trail of All Admin Actions (Priority: P2)

**Goal**: Unified append-only governance audit viewer (FR-011, FR-012).

**Independent Test**: Any governance action → audit entry with actor/action/target/before-after; entries immutable; filterable.

- [x] T043 [US6] Create `GovernanceAuditResource` in `backend/app/Filament/Resources/GovernanceAuditResource.php` — read-only list, filters (actor/action/target_type/target_id/date), `view_audit_log` gate, no create/edit/delete
- [x] T044 [US6] Create `GovernanceAuditPolicy` in `backend/app/Domains/Admin/Policies/GovernanceAuditPolicy.php` (view only)
- [x] T045 [US6] Write feature test `backend/tests/Feature/Admin/GovernanceAuditTest.php` — entries written by tour/partner/review/booking/settings actions; append-only (update/delete blocked); filtering
- [x] T046 [US6] Backfill migration to port existing `booking_audit_logs` + `review_audit_trails` rows into `governance_audit_logs` (one-off, in a dispatchable migration/step)

**Checkpoint**: US6 functional — single immutable governance audit trail.

---

## Phase 9: User Story 7 — Platform Overview Dashboard (Priority: P3)

**Goal**: Dashboard with pending counts + queue shortcuts (FR-013). [ST-013-001..004]

**Independent Test**: Open `/admin` → true pending counts + shortcuts into each queue.

- [x] T047 [US7] Extend `backend/app/Filament/Widgets/PlatformOverviewWidget.php` — pending partners/tours/reviews, recent bookings, monthly revenue, 7-day sparkline
- [x] T048 [US7] Create/extend custom `backend/app/Filament/Pages/Dashboard.php` hosting the widget + queue-shortcut tiles linking to filtered queues (`view_all_analytics` gate)
- [x] T049 [US7] Write Filament test `backend/tests/Feature/Admin/Filament/DashboardTest.php` — counts reflect DB state; shortcuts navigate

**Checkpoint**: US7 functional — admin landing dashboard.

---

## Phase 10: User Story 8 — Availability/Slots Oversight (Priority: P3)

**Goal**: Read-only availability view (FR-014). [ST-013-010]

**Independent Test**: Open a tour's availability → slots visible (empty/full/partially-booked); no edit actions.

- [x] T050 [US8] Create `AvailabilityResource` in `backend/app/Filament/Resources/AvailabilityResource.php` — read-only (`canCreate/canEdit=false`), no row/bulk actions, filters (tour/date/state)
- [x] T051 [US8] Create `AvailabilityPolicy` in `backend/app/Domains/Admin/Policies/AvailabilityPolicy.php` (view-only, `manage_bookings`)
- [x] T052 [US8] Write feature test `backend/tests/Feature/Admin/AvailabilityReadonlyTest.php` — viewable, empty/full/partially states, no mutation actions

**Checkpoint**: US8 functional — read-only availability oversight.

---

## Phase 11: User Story 9 — Settings, CMS & Static Pages (Priority: P3)

**Goal**: Manage platform settings + static pages with localized content + audit (FR-015). [ST-013-011/012/013]

**Independent Test**: Edit/publish a static page → public site updates; settings change audited.

- [x] T053 [P] [US9] Create `StaticPage` + `CmsContent` models in `backend/app/Domains/Admin/Models/StaticPage.php` (localized JSONB)
- [x] T054 [P] [US9] Create `UpdateStaticPageAction` + `UpdateStaticPageRequest` in `backend/app/Domains/Admin/Actions/UpdateStaticPageAction.php` (save/publish, write `cms.*` audit)
- [x] T055 [P] [US9] Create `UpdateSettingsAction` + `UpdateSettingsRequest` in `backend/app/Domains/Admin/Actions/UpdateSettingsAction.php` — validate the submitted group, fill + `save()` the matching spatie settings class (`GeneralSettings`/`SeoSettings`/`ContactSettings`/`BookingSettings`; see data-model.md §4), write `settings.update` audit (target_type=`setting`, target_id=null, metadata.group + changed-property before/after)
- [x] T056 [US9] Create `StaticPageResource` in `backend/app/Filament/Resources/StaticPageResource.php` (list/edit/view, localized fields, `manage_cms` gate) [ST-013-012/013]
- [x] T057 [US9] Create `Pages\Settings` in `backend/app/Filament/Pages/Settings.php` — one Filament section per settings class (`GeneralSettings`, `SeoSettings`, `ContactSettings`, `BookingSettings`), each form field mapping directly to a typed settings-class property; `manage_settings` gate [ST-013-011]
- [x] T057b [US9] Write Filament test `backend/tests/Feature/Admin/Filament/SettingsPageTest.php` — settings form renders per-group sections, save persists values, audit entry written, 403 non-admin
- [x] T058 [US9] Write feature test `backend/tests/Feature/Admin/StaticPageTest.php` — create/edit/publish, localized content, audit, 403 non-admin

**Checkpoint**: US9 functional — settings + CMS/static pages governed + audited.

---

## Phase 12: Polish & Cross-Cutting Concerns

**Purpose**: Coverage, hardening, and validation across all stories.

- [x] T059 [P] Add `backend/tests/Feature/Admin/Filament/PartnerResourceTest.php` — partner lifecycle actions via Filament (no bulk actions — FR-016)
- [x] T060 Verify all 13 ST-013 screens have Filament counterparts at 1280px+ (manual visual check; SC-006)
- [x] T061 Audit-log retention policy review + document in `specs/013-admin-moderation/research.md` (deferred item)
- [x] T062 Constitution amendment COMPLETE: Filament API-first exception ratified in constitution v1.1.0 (API-First §Internal Admin Exception)
- [x] T063 Run `php artisan test -- tests/Feature/Admin` full suite green; run quickstart.md validation loop
- [x] T064 Confirm `governance_audit_logs` immutability under concurrent admin edits (edge case: no contradictory double transition; FR-016). Covered by `backend/tests/Feature/Admin/ConcurrentGovernanceTest.php`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No deps — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–11)**: All depend on Foundational; can run in parallel if staffed, else sequentially P1→P2→P3.
- **Polish (Phase 12)**: Depends on the user stories it covers.

### User Story Dependencies

- **US1 (P1)**: After Foundational; no story deps. (MVP)
- **US2 (P1)**: After Foundational; uses `GovernanceAuditService` + `TourStatus` (foundational).
- **US3 (P1)**: After Foundational; depends on Spec 014 notifications (mail dispatch) but audited independently.
- **US4 (P2)**: After Foundational; delegates financial side-effects to Spec 008 (no blocking dep).
- **US5 (P2)**: After Foundational; wraps existing review actions.
- **US6 (P2)**: After Foundational; consumes audit written by US2/US3/US4/US5/US9 (can develop viewer in parallel; test after others land).
- **US7 (P3)**: After Foundational; reads counts produced by other resources.
- **US8 (P3)**: After Foundational; independent (read-only over existing slots).
- **US9 (P3)**: After Foundational; independent (new static_pages table).

### Within Each User Story

- Actions (different files) marked [P] run in parallel.
- Action before Filament resource wiring (resource calls the Action).
- Feature test before/alongside Filament test.

### Parallel Opportunities

- Phase 1: T003/T004 parallel with T002.
- Phase 2: T011/T012/T013 (enums) parallel; T014–T017 (guards) parallel across models.
- Stories: US2/US3/US4/US5/US8/US9 can be staffed in parallel after Foundational.
- Within a story: all `[P]` Actions (different files) run together.

---

## Implementation Strategy

### MVP First (User Story 1 + Foundational)

1. Phase 1 (Setup) + Phase 2 (Foundational) → audit + authz + enums ready.
2. Phase 3 (US1) → panel access + per-action permissions.
3. **STOP and VALIDATE**: non-admin denied; admin gated per action.

### Incremental Delivery

1. Foundational → US1 (MVP: access control).
2. US2 (tour moderation) + US3 (partner lifecycle) → core governance loop.
3. US4 + US5 → oversight + review moderation.
4. US6 → unified audit viewer.
5. US7 + US8 + US9 → dashboard, availability, settings/CMS.
6. Polish → full ST-013 coverage + validation.

### Parallel Team Strategy

- After Foundational: Dev A → US2/US3; Dev B → US4/US5; Dev C → US6/US7; US8/US9 lightweight.

---

## Notes

- Admin is Filament (server-rendered) — ratified API-first exception (constitution v1.1.0, §Internal Admin Exception).
- No financial execution here (refunds/ledger = Spec 008); bookings transitions are non-financial or delegate.
- Availability is read-only; bulk actions only tours + reviews.
- Commit after each task/logical group (remember: repo requires `lrc review --staged --skip` before `git commit`).
- Stop at any checkpoint to validate a story independently.