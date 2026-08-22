---

description: "Task list for Spec 015 — Partner Onboarding"
---

# Tasks: Partner Onboarding

**Input**: Design documents from `/specs/015-partner-onboarding/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/api.md, quickstart.md — all present.

**Tests**: Included. The Bookly constitution (Testing & Quality Standards) mandates automated tests for critical business flows; partner onboarding lifecycle, authorization gating, and invitation flow are critical. Tests run serially via `docker exec bookly-backend vendor/bin/pest` (per project memory: serial, pgsql, RefreshDatabase, never concurrent) and `npm run typecheck`/`npm run lint` on the frontend.

**Organization**: Tasks are grouped by user story (US1–US5 from spec.md) to enable independent implementation and testing. The backend partner infrastructure already substantially exists from Spec 013 (Partner model, PartnerProfile, lifecycle Actions, GovernanceAuditLog, PartnerPolicy, PartnerRegistrationController) — most tasks are edits to existing classes plus the genuinely new additive surface (invitation system + onboarding status page + resubmission flow + Filament PartnerResource).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (e.g., US1, US5)
- Exact file paths are included in every task

## Path Conventions

- **Backend**: `backend/app/...`, `backend/database/...`, `backend/routes/...`, `backend/resources/...`, `backend/tests/...`
- **Frontend**: `frontend/src/...`, `frontend/messages/...`
- Pest feature tests live under `backend/tests/Feature/<Domain>/`
- Pest unit tests live under `backend/tests/Unit/<Domain>/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Database migrations for the new `partner_invitations` table, the `rejection_reason` column on `partner_profiles`, the `invited_by_admin` flag on `partners`, the scheduled command for invitation expiration, and the morph-map registration that later phases depend on.

- [X] T001 Create migration `backend/database/migrations/2026_08_18_100000_create_partner_invitations_table.php` creating `partner_invitations` with columns per data-model.md §2.1: `id`, `email` (varchar255, indexed), `company_name` (varchar255), `contact_person` (varchar255, nullable), `invited_by_admin_id` (FK→users.id), `token` (varchar64, UNIQUE, indexed), `status` (varchar20, default 'pending', indexed), `expires_at` (timestamp), `consumed_at` (timestamp, nullable), `partner_id` (FK→partners.id, nullable), timestamps. Indexes: `partner_invitations_token_unique`, `partner_invitations_email_status_idx`, `partner_invitations_expires_at_idx`
- [X] T002 [P] Create migration `backend/database/migrations/2026_08_18_100001_add_rejection_reason_to_partner_profiles_table.php` adding nullable `rejection_reason` TEXT column to `partner_profiles` — currently `RejectPartnerAction` writes to `profile.rejection_reason` but the column does not exist yet; this is the missing schema piece
- [X] T003 [P] Create migration `backend/database/migrations/2026_08_18_100002_add_invited_by_admin_to_partners_table.php` adding boolean `invited_by_admin` (default false) to `partners` — flag for partners created via admin invitation flow (auto-approved)
- [X] T004 [P] Register morph-map alias `invitation => PartnerInvitation::class` in `backend/app/Providers/AppServiceProvider.php` `boot()` alongside the existing `admin`, `partner`, `tour`, `booking`, `review`, `static_page` aliases — needed by `GovernanceAuditService` for `partner.invite` action targeting
- [X] T005 [P] Create the `ExpirePartnerInvitations` console command in `backend/app/Console/Commands/ExpirePartnerInvitations.php`: set `status = 'expired'` for all `partner_invitations` with `status = 'pending'` and `expires_at < now()`; register in `backend/app/Console/Kernel.php` or `routes/console.php` as `Schedule::command('partner-invitations:expire')->daily()` (research.md §4.1)

**Checkpoint**: Schema + command infrastructure in place. No runtime behavior changed yet beyond migrations.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The `PartnerInvitation` model, the enhanced `PartnerRoleMiddleware` (onboarding status gating), and the new `PartnerOnboardingService` that all user stories depend on. These block US1–US5 because they all touch the partner lifecycle, authorization, or invitation infrastructure.

**⚠️ CRITICAL**: No user story can begin until this phase is complete.

- [X] T006 [P] Create `backend/app/Domains/Partner/Models/PartnerInvitation.php` Eloquent model: `$fillable` = `[email, company_name, contact_person, invited_by_admin_id, token, status, expires_at, consumed_at, partner_id]`; `$casts` = `[expires_at => datetime, consumed_at => datetime]`; relationships: `invitedByAdmin()` → `belongsTo(User::class, 'invited_by_admin_id')`, `partner()` → `belongsTo(Partner::class)`; scopes: `scopePending($q)`, `scopeValid($q)` (pending + not expired); method `isExpired(): bool` (`expires_at < now()`), `isConsumed(): bool` (`status === 'consumed'`), `isValid(): bool` (`status === 'pending' && !isExpired()`); constant `EXPIRY_DAYS = 7` (data-model.md §2.1, research.md §2)
- [X] T007 Enhance `backend/app/Domains/Partner/Middleware/PartnerRoleMiddleware.php`: after the existing `is_active` + token-scope check, add onboarding-status gating — partners in `pending`, `rejected`, or `suspended` status may access ONLY profile (`/api/partner/profile*`), settings (`/api/partner/settings*`), onboarding-status (`/api/partner/onboarding*`), and notifications (`/api/partner/notifications*`) endpoints; all other partner endpoints (tours, bookings, reviews, analytics, uploads) return `403` with `{message, error_code: 'ONBOARDING_STATUS_BLOCKED', onboarding_status}` per contracts/api.md §6. Approved partners pass through unchanged (FR-004, research.md §4)
- [X] T008 [P] Create `backend/app/Domains/Partner/Services/PartnerOnboardingService.php` with methods: `getStatus(Partner $partner): array` (returns the onboarding-status response shape per contracts/api.md §2.1 — includes `onboarding_status`, `can_create_tours`, `rejection_reason`, `submitted_at`, `approved_at`/`rejected_at`/`suspended_at` from audit log lookup, `message`); `canCreateTours(Partner $partner): bool` (`onboarding_status === 'approved' && is_active`); `getRejectionReason(Partner $partner): ?string` (reads from `PartnerProfile->rejection_reason`); `getLifecycleTimestamp(Partner $partner, string $action): ?Carbon` (queries `GovernanceAuditLog` for the latest `partner.{action}` entry targeting this partner). Single source of truth — controllers and frontend stay thin (FR-003, FR-004, FR-008, SC-002)
- [X] T009 Add `rejection_reason` to `PartnerProfile::$fillable` in `backend/app/Domains/Partner/Models/PartnerProfile.php` (currently the action writes to it but the model does not allow mass-assignment; the column is added in T002)

**Checkpoint**: Foundation ready — model, middleware, service, and schema are in place. User story implementation can now begin in parallel.

---

## Phase 3: User Story 1 — Partner Self-Registration & Application Submission (Priority: P1) 🎯 MVP

**Goal**: A tour operator visits the partner registration page, submits valid business information, gets redirected to the pending onboarding screen, and the backend partner record exists with status `pending`. Platform admins receive an operational notification.

**Independent Test**: An operator visits the registration flow, submits valid business information, gets redirected to the pending onboarding screen, and verifies that the backend partner record exists with status `pending`. A duplicate email submission is rejected with a 422.

> Note: `PartnerRegistrationController` + `PartnerRegistrationRequest` already exist (Spec 012/013) and create the User + Partner + PartnerProfile with `onboarding_status = 'pending'`. The gaps are: (a) `PartnerApplicationReceivedMail` is not dispatched on registration, (b) the admin notification on registration, (c) the frontend registration page, (d) the frontend onboarding status page, (e) test coverage.

### Tests for User Story 1

- [X] T010 [P] [US1] Add `backend/tests/Feature/Partner/PartnerRegistrationTest.php`: (1) valid payload → 201, partner record exists with `onboarding_status = 'pending'`, `is_active = false`, user has `role = 'partner'`, Sanctum token returned; (2) duplicate email → 422 with `errors.email`; (3) missing `company_name` → 422 with `errors.company_name`; (4) invalid `business_address.country` (not 2 chars) → 422; (5) assert `PartnerApplicationReceivedMail` is queued to the partner's `contact_email`; (6) assert a `partner.register` governance audit entry is NOT written (registration is self-service, not admin governance — only admin actions are audited per existing pattern); (7) assert an in-app `Notification` row IS created for all admins with `manage_partners` permission, with `type = 'partner_application'`, `body` containing company name + contact email (FR-013, US1 acceptance 4); (8) assert NO email is dispatched to admin users for this notification (it is in-app only, distinct from FR-011 partner emails) (FR-001, FR-002, FR-003, FR-011, FR-013, SC-001, US1 acceptance 1–4)
- [X] T011 [P] [US1] Add `backend/tests/Unit/Partner/PartnerCanTransitionToTest.php`: assert `Partner::canTransitionTo()` returns true for `pending → approved`, `pending → rejected`, `approved → suspended`, `suspended → approved`, `rejected → pending`; returns false for `pending → pending`, `approved → approved`, `suspended → pending`, `rejected → approved`, `approved → rejected`, and any transition from `incomplete` (legacy) normalized to `pending` (data-model.md §4.1, FR-005)

### Implementation for User Story 1

- [X] T012 [P] [US1] Create `backend/app/Mail/PartnerApplicationReceivedMail.php` Mailable: queued, renders `resources/views/emails/partner/application-received/{locale}.blade.php` with EN fallback (follow `PartnerApprovedMail` pattern), subject localized ("Your Bookly Partner Application Has Been Received"), content confirms receipt + estimated review time + link to onboarding status page. Add `partner.application_received` to `PartnerProfile`/`User` locale resolution (FR-011, contracts/api.md §5.1)
- [X] T013 [P] [US1] Create the three locale views for `PartnerApplicationReceivedMail`: `backend/resources/views/emails/partner/application-received/{en,es,it}.blade.php` — EN is source, ES/IT translate subject + body; body includes partner name, company name, link to `/partner/onboarding`, and "we'll notify you within X business days" (FR-011, contracts/api.md §5.1)
- [X] T014 [US1] Update `backend/app/Domains/Partner/Controllers/Public/PartnerRegistrationController.php` `__invoke()`: after the existing DB transaction that creates User + Partner + PartnerProfile, (a) queue `PartnerApplicationReceivedMail` to the partner's `contact_email` (after commit, following the `ApprovePartnerAction` mail-after-commit pattern); (b) create an in-app `Notification` row for each admin user with `manage_partners` permission (query `AdminPermission` where `manage_partners = true`, join to `users`, create one `Notification` per admin with `type = 'partner_application'`, `title` localized, `body` with company name + email) — this is the admin-facing in-app operational notification required by FR-013 and US1 acceptance 4, distinct from the partner-facing transactional emails governed by FR-011 (no email is sent to admins); (c) return the existing 201 response unchanged (FR-011, FR-013, US1 acceptance 1 & 4)
- [X] T015 [P] [US1] Add i18n strings under `partnerOnboarding` namespace to `frontend/messages/en.json`, `frontend/messages/es.json`, `frontend/messages/it.json`: registration page title, field labels (name, email, password, company name, contact email, phone, website, description, address fields, tax id, payout country), submit button, validation error messages, success message, "pending" status message (parity across all three locales)
- [X] T016 [P] [US1] Create `frontend/src/app/[locale]/(auth)/partner-register/page.tsx` — a Next.js Server Component that renders the `PartnerRegistrationForm` client component, sets `<meta>` for SEO (noindex for auth pages), and uses `next-intl` for localization. Page title from `partnerOnboarding.register.title`. Form posts to `POST /api/public/auth/partners/register` (FR-001, US1 acceptance 1)
- [X] T017 [P] [US1] Create `frontend/src/components/partner/PartnerRegistrationForm.tsx` — client component with React state for all registration fields per `PartnerRegistrationRequest` rules; client-side validation matching server rules (email format, password min 8 + confirmation, required fields, country 2-char); on success, stores token + redirects to `/[locale]/partner/onboarding`; on 422, renders localized field errors; preserves form state on validation failure (Edge Case: Session Expiration During Application) (FR-001, FR-002, SC-001, US1 acceptance 1–3)
- [X] T018 [P] [US1] Add `registerPartner` function to `frontend/src/lib/api/partner.ts`: `POST /api/public/auth/partners/register` with the registration payload, no auth header required, returns `{data: {user, partner, token}, message}`. Add the `PartnerRegistrationPayload` type to `frontend/src/types/partner.ts` (FR-001)
- [X] T019 [P] [US1] Add `getOnboardingStatus` function to `frontend/src/lib/api/partner.ts`: `GET /api/partner/onboarding-status` with `authHeaders()`, returns `PartnerOnboardingStatus`. Add the `PartnerOnboardingStatus` type to `frontend/src/types/partner.ts` per contracts/api.md §2.1 (FR-003, FR-004)
- [X] T020 [US1] Create `frontend/src/app/[locale]/(partner)/partner/onboarding/page.tsx` — a Next.js Server Component (auth-gated by the `(partner)` layout) that fetches `GET /api/partner/onboarding-status` and renders the `OnboardingStatusBanner` component with the status data. This page is the post-registration redirect target and the home for pending/rejected/suspended partners (FR-003, US1 acceptance 1, US3 acceptance 1, US5 acceptance 1)
- [X] T021 [P] [US1] Create `frontend/src/components/partner/OnboardingStatusBanner.tsx` — client component that renders different content based on `onboarding_status`: `pending` → "Your application is under review" with submitted timestamp; `approved` → "Welcome to Bookly!" + link to dashboard/tours; `rejected` → rejection reason display + `ResubmissionForm`; `suspended` → suspension reason + support contact. Uses `next-intl` for all text. Renders `can_create_tours` as a visibility flag for tour-creation CTAs (FR-003, FR-004, FR-008, US1 acceptance 1, US3 acceptance 1–2, US5 acceptance 1)

**Checkpoint**: US1 fully functional and independently testable — an operator can register, see their pending status, and admins are notified.

---

## Phase 4: User Story 2 — Admin Review, Approval & Gating Enforcement (Priority: P1) 🎯 MVP

**Goal**: Platform administrators review pending partner applications via the Filament admin panel, approve/reject with mandatory reason, and the partner is notified by email. Unapproved partners attempting to create tours receive a 403.

**Independent Test**: An admin approves a pending partner via Filament; the partner logs in and gains full access to create tours; an unapproved partner attempting to create tours receives a 403 with `error_code: ONBOARDING_STATUS_BLOCKED`.

> Note: `ApprovePartnerAction`, `RejectPartnerAction`, `PartnerPolicy`, `GovernanceAuditService`, `PartnerApprovedMail`, `PartnerRejectedMail` all exist from Spec 013. The gaps are: (a) the Filament `PartnerResource` does not exist (admin actions were API-only in 013), (b) the enhanced `PartnerRoleMiddleware` onboarding gating from T007 has no test coverage, (c) `RejectPartnerAction` writes to `profile.rejection_reason` but the column does not exist yet (T002 fixes this).

### Tests for User Story 2

- [X] T022 [P] [US2] Add `backend/tests/Feature/Partner/PartnerLifecycleTest.php`: (1) admin approves pending partner → status `approved`, `is_active = true`, `PartnerApprovedMail` queued, `governance_audit_logs` row with `action = 'partner.approve'`, before/after state captured; (2) admin rejects pending partner with reason → status `rejected`, `is_active = false`, `PartnerRejectedMail` queued with reason in body, audit row `action = 'partner.reject'` with reason in metadata, `partner_profiles.rejection_reason` populated; (3) admin rejects without reason → 422; (4) non-admin user → 403; (5) admin cannot approve an already-approved partner → 422 (lifecycle guard); (6) assert the rejection reason is stored verbatim (unmodified original text) in `partner_profiles.rejection_reason` and audit metadata — no sanitization at storage time (spec Edge Case: Rejection Reason Sanitization — escaping is at render time, not storage time) (FR-005, FR-006, FR-011, SC-002, SC-003, US2 acceptance 1–2)
- [X] T023 [P] [US2] Add `backend/tests/Feature/Partner/PartnerOnboardingGateTest.php`: (1) pending partner `POST /api/partner/tours` → 403 with `error_code: ONBOARDING_STATUS_BLOCKED`, `onboarding_status: 'pending'`; (2) rejected partner `POST /api/partner/tours` → 403; (3) suspended partner `POST /api/partner/tours` → 403; (4) approved partner `POST /api/partner/tours` → passes the middleware (reaches controller, may 422 on validation); (5) pending partner `GET /api/partner/profile` → 200 (allowed); (6) pending partner `GET /api/partner/onboarding-status` → 200 (allowed); (7) pending partner `GET /api/partner/notifications` → 200 (allowed) (FR-004, SC-002, US2 acceptance 3)

### Implementation for User Story 2

- [X] T024 [US2] Create `backend/app/Domains/Admin/Filament/PartnerResource.php` Filament resource: table columns `id`, `company_name` (from profile), `contact_email` (from profile), `onboarding_status` (badge with color: pending=warning, approved=success, rejected=danger, suspended=gray), `is_active`, `created_at`; form fields read-only for `id`, `onboarding_status`, `created_at`, `rejection_reason`; editable `company_name`, `contact_email`, `contact_phone`, `business_description`, `website`, `business_address` (JSON), `tax_id`; navigation group `Partners`, icon `heroicon-o-building-office`, label `Partner Applications` (Constitution: Internal Admin Exception, FR-005, US2 acceptance 1–2)
- [X] T025 [US2] Add Filament actions to `PartnerResource`: (a) `ApproveAction` — visible when `onboarding_status = pending`, requires confirmation, calls `ApprovePartnerAction::execute(auth()->user(), $partner)`, success notification; (b) `RejectAction` — visible when `onboarding_status = pending`, requires confirmation modal with `rejection_reason` textarea (required), calls `RejectPartnerAction::execute(auth()->user(), $partner, ['rejection_reason' => $data['rejection_reason']])`, success notification; (c) `SuspendAction` — visible when `onboarding_status = approved`, requires confirmation modal with `reason` textarea (required), calls `SuspendPartnerAction::execute(auth()->user(), $partner)` (note: existing action does not take a reason — enhance to accept and audit reason); (d) `ReinstateAction` — visible when `onboarding_status = suspended`, requires confirmation, calls `ReinstatePartnerAction::execute(auth()->user(), $partner)`, success notification. All actions delegate to Actions (Constitution: Filament MUST delegate governance logic to Actions, MUST NOT mutate models directly) (FR-005, US2 acceptance 1–2, US5 acceptance 1–2)
- [X] T026 [US2] Enhance `backend/app/Domains/Admin/Actions/SuspendPartnerAction.php` to accept a `reason` parameter (matching `RejectPartnerAction` pattern): `execute(User $actor, Partner $partner, array $data = [])` where `$data['reason']` is required (abort 422 if empty); record the reason in audit metadata `['reason' => $reason]`; queue `PartnerSuspendedMail` with the reason after commit. This makes the suspension reason available to the partner (FR-005, FR-006, FR-011, SC-003, US5 acceptance 1)
- [X] T027 [P] [US2] Enhance `backend/app/Domains/Admin/Actions/ReinstatePartnerAction.php` to queue `PartnerReinstatedMail` after the transaction commits (following the `ApprovePartnerAction` mail-after-commit pattern) — currently no email is sent on reinstatement (FR-011, US5 acceptance 2)
- [X] T028 [P] [US2] Register `PartnerResource` in `backend/app/Providers/Filament/AdminPanelProvider.php` (or wherever the Filament panel is configured) so it appears in the admin navigation (Constitution: Internal Admin Exception)
- [X] T029 [P] [US2] Create `backend/app/Mail/PartnerSuspendedMail.php` Mailable: queued, renders `resources/views/emails/partner/suspended/{locale}.blade.php` with EN fallback, subject localized ("Your Bookly Partner Account Has Been Suspended"), content includes suspension reason + support contact. Follow `PartnerRejectedMail` pattern for reason pass-through (FR-011, contracts/api.md §5.4)
- [X] T030 [P] [US2] Create `backend/app/Mail/PartnerReinstatedMail.php` Mailable: queued, renders `resources/views/emails/partner/reinstated/{locale}.blade.php` with EN fallback, subject localized ("Your Bookly Partner Account Has Been Reinstated"), content notes tours must be resubmitted + link to partner dashboard (FR-011, contracts/api.md §5.5)
- [X] T031 [P] [US2] Create locale views for `PartnerSuspendedMail` and `PartnerReinstatedMail`: `backend/resources/views/emails/partner/suspended/{en,es,it}.blade.php` and `backend/resources/views/emails/partner/reinstated/{en,es,it}.blade.php` — EN is source, ES/IT translate; suspended and reinstated views MUST render the reason/output using escaped Blade syntax `{{ $reason }}` (NOT raw `{!! $reason !!}`) to prevent XSS from admin-provided text per spec Edge Case "Rejection Reason Sanitization"; reinstated notes tours must be resubmitted (FR-011, spec Edge Case: Rejection Reason Sanitization, contracts/api.md §5.4–5.5)
- [X] T031a [P] [US2] Add a static/regex verification assertion to `backend/tests/Feature/Partner/PartnerLifecycleTest.php` (T022) or a dedicated `backend/tests/Feature/Partner/EmailOutputEscapingTest.php`: render `PartnerRejectedMail` and `PartnerSuspendedMail` with a reason containing HTML tags (e.g., `'<script>alert(1)</script>Plain reason'`); assert the rendered email body contains the HTML-entity-encoded form (`&lt;script&gt;`) and does NOT contain the raw `<script>` tag — confirming output escaping is active in all locale views (en/es/it) for both rejection and suspension reasons (spec Edge Case: Rejection Reason Sanitization)

**Checkpoint**: US2 fully functional — admins can approve/reject via Filament, partners are notified, and the onboarding gate blocks non-approved partners from tour creation.

---

## Phase 5: User Story 3 — Partner Profile Management & Re-Application (Priority: P2)

**Goal**: An approved partner can update their business profile from the dashboard. A rejected partner can view rejection feedback, edit their details, and resubmit — transitioning back to `pending`.

**Independent Test**: A rejected partner logs in, views the rejection feedback on the onboarding page, updates their company description and contact details via the resubmission form, and submits; their status returns to `pending` and appears in the admin review queue with a `partner.resubmit` audit entry.

> Note: The `PUT /api/partner/profile` endpoint + `ProfileController` + `UpdateProfileRequest` already exist from Spec 012 for approved partners. The gap is the resubmission flow for rejected partners, which requires a dedicated action + endpoint + request because it transitions the lifecycle state (not just a profile edit).

### Tests for User Story 3

- [X] T032 [P] [US3] Add `backend/tests/Feature/Partner/PartnerResubmissionTest.php`: (1) rejected partner `POST /api/partner/onboarding/resubmit` with valid updated profile data → 200, status transitions to `pending`, `rejection_reason` cleared, audit row `action = 'partner.resubmit'` with before/after state; (2) pending partner resubmit → 403 (not in rejected state); (3) approved partner resubmit → 403; (4) suspended partner resubmit → 403; (5) rejected partner resubmit with missing `company_name` → 422; (6) after resubmit, partner appears in the admin Filament queue (queryable by `onboarding_status = 'pending'`) (FR-008, FR-006, US3 acceptance 1–2)
- [X] T033 [P] [US3] Add `backend/tests/Feature/Partner/PartnerProfileUpdateTest.php`: (1) approved partner `PUT /api/partner/profile` with updated `business_description` → 200, profile updated; (2) approved partner update `company_name` → 200; (3) pending partner `PUT /api/partner/profile` → 200 (profile edits allowed for pending partners per T007 middleware design); (4) rejected partner `PUT /api/partner/profile` → 200 (allowed, but does NOT transition status — only the resubmit endpoint does) (FR-010, US3 acceptance 1)

### Implementation for User Story 3

- [X] T034 [P] [US3] Create `backend/app/Domains/Partner/Requests/PartnerResubmitRequest.php` Form Request: authorize `true` (middleware handles auth); rules per data-model.md §5.2 — `company_name` required string max:255, `contact_email` required email max:255, `contact_phone` required string max:50, `business_description` required string max:1000, `business_address` required array with nested `street`/`city`/`postal_code`/`country` rules, `website` nullable url, `tax_id` nullable string max:50 (FR-002, FR-008)
- [X] T035 [P] [US3] Create `backend/app/Domains/Partner/Actions/ResubmitPartnerApplicationAction.php`: `execute(User $actor, Partner $partner, array $data): Partner`; (1) `abort_unless($partner->onboarding_status === 'rejected', 422, 'Only rejected partners can resubmit.')`; (2) DB transaction: lock partner, update `PartnerProfile` with submitted data, clear `rejection_reason`, update `partner.onboarding_status = 'pending'`, `is_active = false`; (3) log `partner.resubmit` via `GovernanceAuditService` with before/after state; (4) return refreshed partner. Follow the existing `ApprovePartnerAction` pattern (transaction, lockForUpdate, audit, no mail) (FR-006, FR-008, SC-005)
- [X] T036 [US3] Create `backend/app/Domains/Partner/Controllers/PartnerOnboardingStatusController.php` with two methods: (a) `show(Request $request)` — injects `PartnerOnboardingService`, calls `getStatus($request->user()->partner)`, returns `{data: ...}` per contracts/api.md §2.1; (b) `resubmit(PartnerResubmitRequest $request)` — injects `ResubmitPartnerApplicationAction`, calls `execute($request->user(), $request->user()->partner, $request->validated())`, returns `{data: {onboarding_status: 'pending', can_create_tours: false, rejection_reason: null, message: 'Your application has been resubmitted for review.'}}` per contracts/api.md §2.2. Thin controller — no business logic (Constitution: Thin Controllers, FR-008, FR-010)
- [X] T037 [US3] Register routes in `backend/routes/api/partner.php` under the existing `auth:sanctum, partner` middleware group: `Route::prefix('onboarding')->group(function () { Route::get('status', [PartnerOnboardingStatusController::class, 'show']); Route::post('resubmit', [PartnerOnboardingStatusController::class, 'resubmit']); })` (contracts/api.md §2)
- [X] T038 [P] [US3] Add `resubmitApplication` function to `frontend/src/lib/api/partner.ts`: `POST /api/partner/onboarding/resubmit` with `authHeaders()`, `requireCsrf: true`, body = `ResubmitPayload`, returns `PartnerOnboardingStatus`. Add `ResubmitPayload` type to `frontend/src/types/partner.ts` per contracts/api.md §2.2 (FR-008)
- [X] T039 [P] [US3] Add i18n strings for the resubmission flow to `frontend/messages/{en,es,it}.json` under `partnerOnboarding.resubmit`: form title, field labels, submit button, success message, "rejection feedback" label, and the resubmission instructions (parity across all three locales)
- [X] T040 [P] [US3] Create `frontend/src/components/partner/ResubmissionForm.tsx` — client component rendered inside `OnboardingStatusBanner` when `onboarding_status === 'rejected'`; pre-fills form with current profile data (from `GET /api/partner/profile`); fields per `PartnerResubmitRequest` rules; client-side validation; on success, refreshes the onboarding status (status flips to `pending`); renders the rejection reason above the form in a callout (FR-008, US3 acceptance 2)

**Checkpoint**: US3 fully functional — rejected partners can view feedback, update, and resubmit; approved partners can edit their profile.

---

## Phase 6: User Story 4 — Admin Partner Invitation Flow (Priority: P2)

**Goal**: An admin can invite a high-quality operator from the Filament panel; the operator receives an email with a secure link, completes registration, and is auto-approved.

**Independent Test**: An admin creates an invitation from Filament; the operator receives an email, opens the secure registration link, sets a password and submits details, and is successfully activated in `approved` status with `invited_by_admin = true`.

> Note: This is the only genuinely new backend surface in the spec — everything else is edits to existing classes. It is additive (FR-009).

### Tests for User Story 4

- [X] T041 [P] [US4] Add `backend/tests/Feature/Partner/PartnerInvitationTest.php`: (1) admin `POST` invite with valid email + company_name → 201, `PartnerInvitation` record exists with `status = 'pending'`, `token` is 64 chars, `expires_at` = now + 7 days, `PartnerInvitationMail` queued; (2) admin invite with already-registered email → 422; (3) admin invite with duplicate pending invitation for same email → 422; (4) non-admin → 403; (5) `GET /api/public/auth/partners/invite/{validToken}` → 200 with pre-filled email/company_name/contact_person/expires_at; (6) `GET` with expired token → 410 Gone; (7) `GET` with consumed token → 409 Conflict; (8) `GET` with unknown token → 404; (9) `POST /api/public/auth/partners/invite/{validToken}/complete` with valid data → 201, partner created with `onboarding_status = 'approved'`, `is_active = true`, `invited_by_admin = true`, invitation `status = 'consumed'`, `consumed_at` set, `partner_id` linked, Sanctum token returned, `PartnerInvitationMail` NOT sent (it was sent at creation), `PartnerApprovedMail` NOT sent (auto-approved, no admin action); (10) `POST` with expired token → 410; (11) `POST` with consumed token → 409; (12) `POST` with password mismatch → 422; (13) audit entry `action = 'partner.invite'` written at creation with admin as actor (FR-009, FR-006, FR-011, SC-002, US4 acceptance 1–3)
- [X] T042 [P] [US4] Add `backend/tests/Unit/Partner/PartnerInvitationModelTest.php`: `isExpired()` true when `expires_at < now()`, false otherwise; `isConsumed()` true when `status = 'consumed'`; `isValid()` true when `status = 'pending' && !isExpired()`; `scopePending` filters to `status = 'pending'`; `scopeValid` filters to `status = 'pending' && expires_at > now()`; token generation produces 64-char alphanumeric strings (data-model.md §2.1)

### Implementation for User Story 4

- [X] T043 [P] [US4] Create `backend/app/Domains/Partner/Requests/CompleteInvitationRequest.php` Form Request: rules per data-model.md §5.3 — `name` required string max:255, `password` required string min:8 confirmed, `contact_phone` sometimes string max:50, `business_description` sometimes string max:1000, `business_address` sometimes array with nested rules, `payout_country` sometimes string size:2; `company_name` and `contact_email` come from the invitation (pre-filled), not the request body (FR-009, US4 acceptance 2)
- [X] T044 [P] [US4] Create `backend/app/Domains/Admin/Actions/InvitePartnerAction.php`: `execute(User $actor, array $data): PartnerInvitation`; (1) validate email not already a user (`User::where('email', $data['email'])->exists()` → abort 422); (2) validate no existing pending invitation for this email (`PartnerInvitation::where('email', $data['email'])->where('status', 'pending')->exists()` → abort 422); (3) DB transaction: create `PartnerInvitation` with `Str::random(64)` token, `expires_at = now()->addDays(7)`, `invited_by_admin_id = $actor->id`, `status = 'pending'`, `company_name` + `contact_person` from data; (4) log `partner.invite` via `GovernanceAuditService` with actor = admin, target = invitation; (5) after commit, queue `PartnerInvitationMail` to the invitation email; (6) return invitation. Follow the existing Action pattern (FR-009, FR-006, FR-011, US4 acceptance 1)
- [X] T045 [P] [US4] Create `backend/app/Domains/Partner/Actions/CompletePartnerInvitationAction.php`: `execute(PartnerInvitation $invitation, array $data): array`; (1) abort if `!$invitation->isValid()` (410 if expired, 409 if consumed); (2) DB transaction: create `User` with `email = $invitation->email`, `name = $data['name']`, `password = $data['password']`, `role = 'partner'`; create `Partner` with `onboarding_status = 'approved'`, `is_active = true`, `invited_by_admin = true`; create `PartnerProfile` with `company_name = $invitation->company_name`, `contact_email = $invitation->email`, `contact_phone` + `business_description` + `business_address` + `payout_country` from data; update `PartnerInvitation` `status = 'consumed'`, `consumed_at = now()`, `partner_id = $partner->id`; create Sanctum token; (3) return `['user' => $user, 'partner' => $partner, 'token' => $plainTextToken]`. NO audit log (auto-approval is not an admin governance action) — but the invitation creation was audited in T044 (FR-009, US4 acceptance 2)
- [X] T046 [P] [US4] Create `backend/app/Domains/Partner/Controllers/Public/PartnerInvitationController.php` with two methods: (a) `show(string $token)` — find `PartnerInvitation::where('token', $token)->firstOrFail()` (404 if not found); if `isConsumed()` → 409; if `isExpired()` → 410; return `{data: {email, company_name, contact_person, expires_at}}`; (b) `complete(CompleteInvitationRequest $request, string $token)` — find invitation (404), validate via request, inject `CompletePartnerInvitationAction`, call `execute($invitation, $request->validated())`, return `{data: {user, partner, token}, message: 'Welcome to Bookly! Your partner account is now active.'}` 201. Thin controller (Constitution: Thin Controllers, FR-009, US4 acceptance 2)
- [X] T047 [US4] Register invitation routes in `backend/routes/api/public.php` under the existing `auth` prefix: `Route::prefix('partners')->group(function () { Route::get('invite/{token}', [PartnerInvitationController::class, 'show']); Route::post('invite/{token}/complete', [PartnerInvitationController::class, 'complete']); })` with `throttle:traveler` on GET and `throttle:auth` on POST (contracts/api.md §1.2–1.3, §7)
- [X] T048 [P] [US4] Create `backend/app/Mail/PartnerInvitationMail.php` Mailable: queued, renders `resources/views/emails/partner/invitation/{locale}.blade.php` with EN fallback, subject localized ("You're Invited to Join Bookly as a Partner"), content includes company name (pre-filled), admin contact person, registration link `{app_url}/auth/partner-invite/{token}`, expiration notice (7 days). Follow `PartnerApprovedMail` pattern (FR-009, FR-011, contracts/api.md §5.6)
- [X] T049 [P] [US4] Create locale views for `PartnerInvitationMail`: `backend/resources/views/emails/partner/invitation/{en,es,it}.blade.php` — EN is source, ES/IT translate; body includes the invitation link with `{{ $token }}`, company name, expiration date, and "This invitation expires in 7 days" (FR-011, contracts/api.md §5.6)
- [X] T050 [US4] Add the "Invite Partner" action to the `PartnerResource` Filament table header (not row-level): opens a modal form with `email` (required, email, not-already-registered validation), `company_name` (required, string, max:255), `contact_person` (optional, string, max:255); on submit calls `InvitePartnerAction::execute(auth()->user(), $data)`; success notification with "Invitation sent to {email}". The action is visible to admins with `manage_partners` permission (FR-009, US4 acceptance 1)
- [X] T051 [P] [US4] Add i18n strings for the invitation acceptance page to `frontend/messages/{en,es,it}.json` under `partnerOnboarding.invitation`: page title, "You've been invited" heading, pre-filled company name display, password field, confirm password field, additional fields labels, submit button, expired message, consumed message, not-found message (parity across all three locales)
- [X] T052 [P] [US4] Add `getInvitationDetails` and `completeInvitation` functions to `frontend/src/lib/api/partner.ts`: `GET /api/public/auth/partners/invite/{token}` (no auth) and `POST /api/public/auth/partners/invite/{token}/complete` (no auth, CSRF). Add `PartnerInvitation` type to `frontend/src/types/partner.ts` per contracts/api.md §1.2 (FR-009)
- [X] T053 [P] [US4] Create `frontend/src/app/[locale]/(auth)/partner-invite/[token]/page.tsx` — a Next.js Server Component that fetches `GET /api/public/auth/partners/invite/{token}` server-side; on 200 renders the `InvitationAcceptanceForm` with pre-filled data; on 410 renders an "expired" message + link to standard registration; on 409 renders a "already used" message + link to standard registration; on 404 renders a "not found" message + link to standard registration. Sets `<meta name="robots" content="noindex,nofollow">` (FR-009, US4 acceptance 2–3)
- [X] T054 [P] [US4] Create `frontend/src/components/partner/InvitationAcceptanceForm.tsx` — client component pre-filled with `email` (read-only), `company_name` (read-only from invitation), editable `name`, `password`, `password_confirmation`, `contact_phone`, `business_description`, `business_address`, `payout_country`; client-side validation; on success stores token + redirects to `/[locale]/partner/onboarding` (which will show "approved" status); on 422 renders localized errors (FR-009, US4 acceptance 2)
- [X] T055 [P] [US4] Add `ExpirePartnerInvitationsTest` to `backend/tests/Feature/Partner/PartnerInvitationTest.php` or a new `backend/tests/Feature/Partner/ExpirePartnerInvitationsCommandTest.php`: create a pending invitation with `expires_at` in the past, run `php artisan partner-invitations:expire`, assert its `status` becomes `expired`; a valid pending invitation stays `pending`; a consumed invitation stays `consumed` (T005, data-model.md §4.2)

**Checkpoint**: US4 fully functional — admins can invite, operators can accept, auto-approval works.

---

## Phase 7: User Story 5 — Partner Suspension & Account State Governance (Priority: P3)

**Goal**: An admin can suspend a partner, hiding all their tours from public search and blocking write access. The admin can reinstate, restoring visibility (tours must be resubmitted).

**Independent Test**: An admin suspends an approved partner via Filament; all associated tours disappear from search results (status → draft) and the partner's write actions are blocked (403); reinstating restores `is_active` and partner access, but tours remain draft until resubmitted.

> Note: `SuspendPartnerAction` + `ReinstatePartnerAction` + `Partner::removeToursFromDiscovery()` + `Partner::restoreToursToDiscovery()` all exist from Spec 013 and already implement the tour delisting + Scout reindex. The gaps are: (a) `SuspendPartnerAction` does not accept a reason (T026 fixes this), (b) `PartnerSuspendedMail` does not exist (T029 creates it), (c) `PartnerReinstatedMail` does not exist (T030 creates it), (d) test coverage for the full suspension → delisting → reinstatement flow.

### Tests for User Story 5

- [X] T056 [P] [US5] Add `backend/tests/Feature/Partner/PartnerSuspensionTest.php`: (1) admin suspends approved partner with reason → status `suspended`, `is_active = false`, all published tours transition to `draft`, `PartnerSuspendedMail` queued with reason, audit row `action = 'partner.suspend'` with reason in metadata; (2) admin suspends without reason → 422; (3) admin suspends already-suspended partner → 422 (lifecycle guard); (4) admin suspends pending partner → 422; (5) after suspension, `Tour::shouldBeSearchable()` returns false for the partner's tours (they are `draft`); (6) after suspension, `GET /api/public/search/tours` does not return the partner's tours (SC-004, FR-007); (7) non-admin → 403; (8) **Existing-token rejection after suspension** (spec Edge Case: Deleted/Inactive Users): authenticate an approved partner and obtain a valid Sanctum Bearer token; assert `GET /api/partner/onboarding-status` with that token returns 200 (token works pre-suspension); suspend the partner via admin; reuse the SAME previously-issued Bearer token to call `POST /api/partner/tours` and assert the request is rejected (403 `ONBOARDING_STATUS_BLOCKED` or 404 per the existing `PartnerRoleMiddleware` contract — the middleware checks `is_active` on every request, so the suspended partner's token is blocked on the next call without physical token deletion); also assert `GET /api/partner/onboarding-status` with the same token returns 200 (read-only onboarding endpoints remain accessible to suspended partners per T007 design) — confirming the authorization boundary is enforced via current user/partner status checks, not token revocation (FR-004, FR-007, spec Edge Case: Deleted/Inactive Users, SC-002, SC-003, SC-004, US5 acceptance 1)
- [X] T057 [P] [US5] Add `backend/tests/Feature/Partner/PartnerReinstatementTest.php`: (1) admin reinstates suspended partner → status `approved`, `is_active = true`, `PartnerReinstatedMail` queued, audit row `action = 'partner.reinstate'`; (2) reinstated partner's tours remain `draft` (NOT auto-republished — must resubmit per governed publishing flow FR-005); (3) admin reinstates non-suspended partner → 422; (4) after reinstatement, partner can create new tours (middleware passes); (5) after reinstatement, `GET /api/public/search/tours` still does not return the partner's old tours (they are still `draft`) until the partner resubmits and admin approves (FR-005, FR-007, FR-011, US5 acceptance 2)
- [X] T058 [P] [US5] Add `backend/tests/Feature/Partner/PartnerSuspendedAccessTest.php`: (1) suspended partner `POST /api/partner/tours` → 403 `ONBOARDING_STATUS_BLOCKED`; (2) suspended partner `GET /api/partner/profile` → 200 (allowed); (3) suspended partner `GET /api/partner/onboarding-status` → 200 with `onboarding_status: 'suspended'` and `suspension_reason`; (4) suspended partner `GET /api/partner/notifications` → 200 (allowed); (5) suspended partner `GET /api/partner/tours` → 200 (read access to own tours allowed, write blocked) (FR-004, FR-007, US5 acceptance 1)

**Checkpoint**: US5 fully functional and independently testable — suspension delists tours, blocks write access, and reinstatement restores access without auto-republishing.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Whole-feature validation, i18n parity, backward-compatibility, and constitution compliance checks.

- [X] T059 [P] Run the backend test suites serially via `docker exec bookly-backend vendor/bin/pest tests/Feature/Partner` and `docker exec bookly-backend vendor/bin/pest tests/Unit/Partner` (serial, pgsql, never concurrent — per project memory) and confirm no new failures beyond documented pre-existing ones
- [X] T060 [P] Run `cd frontend && npm run typecheck && npm run lint` and confirm the edited/new frontend files compile cleanly
- [X] T061 [P] Run an i18n parity check: confirm every new key added under `partnerOnboarding` exists in all three of `frontend/messages/en.json`, `frontend/messages/es.json`, `frontend/messages/it.json`
- [X] T062 [P] Backward-compatibility regression: run `docker exec bookly-backend vendor/bin/pest tests/Feature/Admin tests/Feature/Booking tests/Feature/Search` serially and confirm the new invitation system, onboarding middleware enhancement, and PartnerResource introduced no new failures in admin moderation, booking, payment, or search flows
- [X] T063 [P] Run the end-to-end quickstart in `specs/015-partner-onboarding/quickstart.md` against the running Docker stack and confirm every curl/browser check matches the expected result
- [X] T064 [P] Static guard: grep the diff to confirm no automated partner payout notification artifacts (no `PartnerPayout*Mail` / `*Payout*` mailable, job, or queued listener) were introduced (Constitution: Out-of-Scope §1 — Automated partner payouts), no multi-user partner role artifacts (no `PartnerUser`, `PartnerStaff`, `PartnerRole` model/migration — FR-012 single-account model), and no server-rendered partner-facing HTML (all partner surfaces via API + Next.js — Constitution: API-First)
- [X] T065 [P] Constitution compliance audit: verify (a) all new controllers are thin (no business logic, no direct DB access — Constitution: Thin Controllers); (b) all new write endpoints use Form Requests (Constitution: Mandatory Input Validation); (c) all new admin governance actions write `GovernanceAuditLog` entries (Constitution: Mandatory Audit Logs); (d) all new emails are queued (Constitution: Queueing & Async Work); (e) `PartnerResource` Filament actions delegate to Action classes, not direct model mutation (Constitution: Internal Admin Exception §"Filament resources and actions MUST delegate governance logic to Actions/Services"); (f) grep all new email Blade views for `{!! $reason !!}` or `{!! $rejection_reason !!}` and confirm NONE exist — all reason output uses escaped `{{ $reason }}` syntax (spec Edge Case: Rejection Reason Sanitization, T031/T031a)

**Checkpoint**: Feature complete, validated, backward-compatible, and constitution-compliant.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately. T001 before T006 (model needs table). T002 before T009 (model fillable needs column). T003 before T045 (action sets `invited_by_admin`). T004 before T044 (morph map for audit target). T005 is standalone.
- **Foundational (Phase 2)**: Depends on Phase 1 (T001, T002, T003). T006 before T044/T045 (model used by actions). T007 before T023 (middleware test). T008 before T036 (service used by controller). T009 before T035 (action writes `rejection_reason`). **BLOCKS US1–US5** (all touch the partner lifecycle, authorization, or invitation infrastructure).
- **User Stories (Phases 3–7)**: All depend on Foundational phase completion.
- **Polish (Phase 8)**: Depends on all user stories being complete.

### User Story Dependencies

- **US1 (P1)**: Depends on Phase 2 (T007 middleware, T008 service). No dependency on other stories.
- **US2 (P1)**: Depends on Phase 2 (T007 middleware for gate tests). T026 (SuspendPartnerAction reason) is shared with US5 — implement once. No dependency on other stories.
- **US3 (P2)**: Depends on Phase 2 (T007 middleware allows rejected partners to access profile, T008 service for status). No dependency on other stories.
- **US4 (P2)**: Depends on Phase 2 (T006 model, T007 middleware for auto-approved access). No dependency on other stories.
- **US5 (P3)**: Depends on Phase 2 (T007 middleware). Shares T026/T029/T030 with US2 — sequence after US2 or coordinate.

### Within Each User Story

- Tests first (write, watch fail, then implement) where a test/impl pair exists.
- Model before Action before Controller before Route (US4: T006 → T043/T044/T045 → T046 → T047).
- Backend endpoint before frontend page that consumes it (US1: T014 before T016; US3: T037 before T040; US4: T047 before T053).
- Mailable before locale views before test that asserts mail content (US2: T029 before T031 before T022).

### Parallel Opportunities

- **Phase 1**: T002, T003, T004, T005 are [P] (different files) — run in parallel after T001.
- **Phase 2**: T006, T008, T009 are [P] (different files) — run in parallel; T007 is sequential (middleware enhancement touches the existing file carefully).
- **Phase 3 (US1)**: T010, T011, T012, T013, T015, T016, T017, T018, T019, T021 are [P] (different files); T014 depends on T012/T013; T020 depends on T019/T021.
- **Phase 4 (US2)**: T022, T023, T027, T028, T029, T030, T031, T031a are [P]; T024 before T025; T026 before T022 (test asserts reason); T031a after T031 (test asserts the views use escaping).
- **Phase 5 (US3)**: T032, T033, T034, T035, T038, T039, T040 are [P]; T036 depends on T034/T035; T037 depends on T036.
- **Phase 6 (US4)**: T041, T042, T043, T044, T045, T048, T049, T051, T052, T054, T055 are [P]; T046 depends on T043/T045; T047 depends on T046; T050 depends on T044; T053 depends on T047/T052.
- **Phase 7 (US5)**: T056, T057, T058 are [P] (same test domain but different test files/assertions).
- **Cross-story**: US1, US3, US4 can all run in parallel (different files) after Phase 2. US2 and US5 share T026/T029/T030 — coordinate.

---

## Parallel Example: User Story 4 (after Phase 2)

```text
# Launch the parallel-safe US4 tasks together:
Task: "T041 PartnerInvitationTest (backend) in backend/tests/Feature/Partner/PartnerInvitationTest.php"
Task: "T042 PartnerInvitationModelTest (backend unit) in backend/tests/Unit/Partner/PartnerInvitationModelTest.php"
Task: "T043 CompleteInvitationRequest in backend/app/Domains/Partner/Requests/CompleteInvitationRequest.php"
Task: "T044 InvitePartnerAction in backend/app/Domains/Admin/Actions/InvitePartnerAction.php"
Task: "T045 CompletePartnerInvitationAction in backend/app/Domains/Partner/Actions/CompletePartnerInvitationAction.php"
Task: "T048 PartnerInvitationMail in backend/app/Mail/PartnerInvitationMail.php"
Task: "T049 invitation locale views in backend/resources/views/emails/partner/invitation/{en,es,it}.blade.php"
Task: "T051 invitation i18n keys in frontend/messages/{en,es,it}.json"
Task: "T052 invitation API functions in frontend/src/lib/api/partner.ts"

# Then sequence:
Task: "T046 PartnerInvitationController"          # after T043, T045
Task: "T047 Register invitation routes"            # after T046
Task: "T050 Filament InvitePartner action"         # after T044
Task: "T053 Next.js invitation page"               # after T047, T052
Task: "T054 InvitationAcceptanceForm"              # after T053
Task: "T055 ExpirePartnerInvitations test"         # after T005, T041
```

---

## Implementation Strategy

### MVP First (User Story 1 + User Story 2) — recommended

1. Complete Phase 1 (Setup) — T001–T005.
2. Complete Phase 2 (Foundational) — T006–T009. **CRITICAL — blocks all user stories.**
3. Complete Phase 3 (US1) — T010–T021. **STOP and VALIDATE**: an operator can register, see their pending status, and admins receive a notification.
4. Complete Phase 4 (US2) — T022–T031. **STOP and VALIDATE**: an admin can approve/reject via Filament, the partner is notified, and the onboarding gate blocks non-approved partners from tour creation.
5. Deploy/demo the MVP (the marketplace's core supply acquisition + governance gate).

### Incremental Delivery

1. Setup + Foundational → foundation ready (invitation model, middleware gate, onboarding service).
2. + US1 → self-registration works (MVP core).
3. + US2 → admin review + governance gate works (MVP complete).
4. + US3 → profile management + re-application works.
5. + US4 → admin invitation flow works.
6. + US5 → suspension + reinstatement works.
7. Polish (Phase 8) → whole-feature validation + backward-compatibility regression.

### Parallel Team Strategy

With multiple developers after Phase 2:

- Developer A: US1 (registration + onboarding status page — frontend-heavy).
- Developer B: US2 + US5 (Filament resource + lifecycle actions + suspension — backend-heavy, shares T026/T029/T030).
- Developer C: US3 (resubmission flow — backend action + frontend form).
- Developer D: US4 (invitation system — the only genuinely new surface, mostly new files).

---

## Notes

- **[P]** tasks touch different files and have no dependency on incomplete tasks.
- **[Story]** labels map each task to a user story for traceability.
- Each user story is independently completable and testable (run its test file in isolation).
- Verify tests fail before implementing (TDD where a test/impl pair exists).
- Commit after each task or logical group; run the affected Pest suite serially after backend changes (per project memory: never run Pest concurrently in the container).
- Stop at any checkpoint to validate a story independently.
- The spec reuses existing infrastructure from Specs 012/013 — most tasks are edits, not greenfield. The only genuinely new surface is US4 (invitation system) plus the Filament PartnerResource (US2) and the onboarding status page (US1).
- The `rejection_reason` column on `partner_profiles` (T002) is a missing schema piece — `RejectPartnerAction` already writes to it but the migration was never created. This is a bug fix bundled with the spec.
- **Output escaping (C1)**: Rejection/suspension reasons are stored verbatim for audit integrity but MUST be rendered with escaped Blade `{{ $reason }}` syntax in all email views — never `{!! $reason !!}`. T031 enforces this in the view creation; T031a adds a test verifying HTML is entity-encoded in rendered output; T065 adds a static grep guard.
- **Existing-token rejection (C2)**: The `PartnerRoleMiddleware` checks `is_active`/`onboarding_status` on every request, so already-issued Sanctum tokens are rejected on the next request after suspension without physical token deletion. T056 scenario (8) explicitly tests this.
- **Admin in-app notification (F2)**: FR-013 codifies that admin notifications on new partner applications are in-app `Notification` rows (not emails), distinct from FR-011 partner-facing transactional emails. T014 implements this; T010 assertion (7)–(8) verifies it.
- **Reinstatement email (F1)**: FR-011 now explicitly lists "Account Reinstated" in the lifecycle email set, aligning with US5 acceptance 2 and T030.