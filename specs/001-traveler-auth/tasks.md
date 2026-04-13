# Tasks: Traveler Authentication

**Input**: Design documents from `/specs/001-traveler-auth/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are included as constitutionally mandated for auth gate testing.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `backend/` (Laravel API)
- **Frontend**: `frontend/` (Next.js)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project scaffolding — Laravel backend + Next.js frontend initialization

- [x] T001 Initialize Laravel project in `backend/` with PHP 8.2+, configure Sanctum, PostgreSQL, and Redis connections in `backend/.env`
- [x] T002 Initialize Next.js 14 project in `frontend/` with TypeScript strict mode, App Router, and Tailwind CSS
- [x] T003 [P] Configure Docker Compose for local development with PostgreSQL 15, Redis 7, and mailpit (email testing) in `docker-compose.yml`
- [x] T004 [P] Configure backend linting (Pint) and frontend linting (ESLint + Prettier) in `backend/pint.json` and `frontend/.eslintrc.json`
- [x] T005 [P] Setup `next-intl` with locale-prefixed routing (`/en/`, `/es/`, `/it/`) and middleware in `frontend/src/middleware.ts`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database schema, shared models, event infrastructure, and API scaffolding that ALL user stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T006 Create `users` table migration with all columns (role enum, locale enum, email_verified_at, failed_login_count, locked_until, last_login_at) per data-model.md in `backend/database/migrations/xxxx_create_users_table.php`
- [ ] T007 [P] Publish and run Sanctum `personal_access_tokens` migration in `backend/database/migrations/xxxx_create_personal_access_tokens_table.php`
- [ ] T008 [P] Create `password_reset_tokens` migration (Laravel default) in `backend/database/migrations/xxxx_create_password_reset_tokens_table.php`
- [ ] T009 [P] Create `guest_identities` table migration with email index, converted_user_id FK, anonymized_at per data-model.md in `backend/database/migrations/xxxx_create_guest_identities_table.php`
- [ ] T010 [P] Create `auth_audit_logs` table migration with user_id FK, event_type, ip_address, user_agent, metadata (jsonb) per data-model.md in `backend/database/migrations/xxxx_create_auth_audit_logs_table.php`
- [ ] T011 Create User model with role enum, locale enum, HasApiTokens trait, MustVerifyEmail contract, fillable/casts/relationships in `backend/app/Models/User.php`
- [ ] T012 [P] Create GuestIdentity model with relationships, scopes (active, anonymizable), and anonymization method in `backend/app/Models/GuestIdentity.php`
- [ ] T013 [P] Create AuthAuditLog model (no updating/deleting, append-only) with scopes for event type filtering in `backend/app/Models/AuthAuditLog.php`
- [ ] T014 Create UserResource API transformer (id, name, email, locale, email_verified, created_at, last_login_at) in `backend/app/Http/Resources/UserResource.php`
- [ ] T015 [P] Create all auth domain events (TravelerRegistered, TravelerLoggedIn, LoginFailed, AccountLockedOut, PasswordReset, PasswordChanged, EmailVerified, GuestConvertedToAccount) in `backend/app/Domains/Auth/Events/`
- [ ] T016 [P] Create LogAuthEvent listener that writes to auth_audit_logs table, capturing user_id, event_type, IP, user agent, and metadata in `backend/app/Domains/Auth/Listeners/LogAuthEvent.php`
- [ ] T017 Register event-listener mappings in `backend/app/Providers/EventServiceProvider.php`
- [ ] T018 Configure Sanctum token expiration (7 days), configure rate limiting for auth endpoints (10 req/min) in `backend/config/sanctum.php` and `backend/app/Providers/RouteServiceProvider.php`
- [ ] T019 Create auth API route group with rate limiting middleware in `backend/routes/api/public.php`
- [ ] T020 [P] Create Zod validation schemas for all auth forms (register, login, forgot-password, reset-password, change-password) in `frontend/src/lib/validators/auth.ts`
- [ ] T021 [P] Create auth API client module with typed methods for all endpoints (register, login, logout, etc.) in `frontend/src/lib/api/auth.ts`
- [ ] T022 [P] Create useAuth hook with auth context provider (token management, user state, sign-in/out methods) in `frontend/src/lib/hooks/useAuth.ts`
- [ ] T023 [P] Create auth translation files for all three languages in `frontend/src/i18n/en/auth.json`, `frontend/src/i18n/es/auth.json`, `frontend/src/i18n/it/auth.json`
- [ ] T024 [P] Create AuthGuard component for protecting authenticated routes with redirect-to-login in `frontend/src/components/auth/AuthGuard.tsx`
- [ ] T025 Create UserFactory and GuestIdentityFactory for test seeding in `backend/database/factories/UserFactory.php` and `backend/database/factories/GuestIdentityFactory.php`

**Checkpoint**: Foundation ready — database migrated, models created, events wired, API client and auth context ready. User story implementation can now begin.

---

## Phase 3: User Story 1 — Traveler Registration (Priority: P1) 🎯 MVP

**Goal**: Visitors can create a traveler account by providing name, email, and password. A verification email is sent (non-blocking). Guest bookings with the same email are linked.

**Independent Test**: Visit registration page, submit valid credentials, confirm user is signed in and redirected.

### Tests for User Story 1

- [ ] T026 [P] [US1] Write registration feature test: valid registration, duplicate email, weak password, missing fields, verification email dispatch, guest booking linkage in `backend/tests/Feature/Auth/RegistrationTest.php`

### Implementation for User Story 1

- [ ] T027 [P] [US1] Create RegisterRequest form request with validation rules (name required max 255, email required unique, password min 8 with uppercase/lowercase/number, locale optional) in `backend/app/Http/Requests/Auth/RegisterRequest.php`
- [ ] T028 [US1] Create RegisterTravelerAction with logic: create user, link guest bookings by email, dispatch TravelerRegistered event, queue verification email in `backend/app/Domains/Auth/Actions/RegisterTravelerAction.php`
- [ ] T029 [US1] Create LinkGuestBookingsAction to query guest_identities by email and update associated bookings' user_id in `backend/app/Domains/Auth/Actions/LinkGuestBookingsAction.php`
- [ ] T030 [US1] Create SendVerificationEmailAction using Laravel's signed URL verification with 60-min expiry in `backend/app/Domains/Auth/Actions/SendVerificationEmailAction.php`
- [ ] T031 [P] [US1] Create VerificationMail mailable with multi-language support (EN/ES/IT) in `backend/app/Mail/VerificationMail.php`
- [ ] T032 [P] [US1] Create SendVerificationEmail queued job (retry-safe, idempotent) in `backend/app/Jobs/SendVerificationEmail.php`
- [ ] T033 [US1] Create RegisterController (thin: validate via RegisterRequest, delegate to RegisterTravelerAction, return UserResource + token) in `backend/app/Http/Controllers/Public/Auth/RegisterController.php`
- [ ] T034 [US1] Register POST `/api/public/auth/register` route in `backend/routes/api/public.php`
- [ ] T035 [P] [US1] Create RegisterForm component with name/email/password fields, client-side Zod validation, error display, loading state in `frontend/src/components/auth/RegisterForm.tsx`
- [ ] T036 [US1] Create register page with RegisterForm, return-to-URL handling, redirect on success in `frontend/src/app/[locale]/auth/register/page.tsx`

**Checkpoint**: Traveler registration fully functional — account creation, verification email, guest linkage, and frontend form all working.

---

## Phase 4: User Story 2 — Traveler Sign-In and Sign-Out (Priority: P1)

**Goal**: Returning travelers can sign in with email/password and sign out. Includes brute-force protection (5 attempts → escalating lockout) and session token management.

**Independent Test**: Sign in with valid credentials, verify access to auth pages, sign out, confirm auth pages inaccessible.

### Tests for User Story 2

- [ ] T037 [P] [US2] Write login feature test: valid login, invalid credentials (generic error), token issuance, last_login_at update in `backend/tests/Feature/Auth/LoginTest.php`
- [ ] T038 [P] [US2] Write logout feature test: token revocation, other sessions preserved in `backend/tests/Feature/Auth/LogoutTest.php`
- [ ] T039 [P] [US2] Write brute-force protection test: counter increment, lockout after 5 failures (1min/5min/30min escalation), reset on success in `backend/tests/Feature/Auth/BruteForceProtectionTest.php`

### Implementation for User Story 2

- [ ] T040 [P] [US2] Create LoginRequest form request (email required, password required) in `backend/app/Http/Requests/Auth/LoginRequest.php`
- [ ] T041 [US2] Create LoginAction with logic: check lockout, verify credentials, reset/increment failure counter, issue Sanctum token, dispatch events in `backend/app/Domains/Auth/Actions/LoginAction.php`
- [ ] T042 [US2] Create LogoutAction to revoke current token in `backend/app/Domains/Auth/Actions/LogoutAction.php`
- [ ] T043 [US2] Implement brute-force protection in AuthService: track failed_login_count, compute lockout duration (1min→5min→30min), check locked_until, dispatch AccountLockedOut event in `backend/app/Domains/Auth/Services/AuthService.php`
- [ ] T044 [US2] Create LoginController (thin: validate, delegate to LoginAction, return UserResource + token or error) in `backend/app/Http/Controllers/Public/Auth/LoginController.php`
- [ ] T045 [P] [US2] Create LogoutController (auth required, delegate to LogoutAction) in `backend/app/Http/Controllers/Public/Auth/LogoutController.php`
- [ ] T046 [US2] Register POST `/api/public/auth/login` and POST `/api/public/auth/logout` routes in `backend/routes/api/public.php`
- [ ] T047 [P] [US2] Create LoginForm component with email/password fields, Zod validation, generic error display, lockout countdown timer in `frontend/src/components/auth/LoginForm.tsx`
- [ ] T048 [US2] Create login page with LoginForm, "Forgot password?" link, return-to-URL handling in `frontend/src/app/[locale]/auth/login/page.tsx`
- [ ] T049 [US2] Integrate login/logout with useAuth hook: store token in httpOnly cookie via Next.js API route, update auth context on sign-in/out in `frontend/src/lib/hooks/useAuth.ts`

**Checkpoint**: Sign-in/sign-out fully functional with brute-force protection, session management, and frontend forms.

---

## Phase 5: User Story 3 — Guest Checkout Identity (Priority: P1)

**Goal**: Guests can provide name, email, and phone during checkout without creating an account. The system captures this as a guest identity linked to their booking.

**Independent Test**: Complete checkout as guest, verify booking created and associated with provided email, confirm no account/password was required.

**Note**: This story focuses on the identity/auth system's role in guest checkout. The full checkout flow is in spec 007 (Booking & Checkout). This phase implements the guest identity capture and existing-account detection components.

### Tests for User Story 3

- [ ] T050 [P] [US3] Write guest identity test: create guest identity, detect existing account by email, multiple bookings same email in `backend/tests/Feature/Auth/GuestIdentityTest.php`

### Implementation for User Story 3

- [ ] T051 [US3] Create AuthService method `findOrCreateGuestIdentity(email, name, phone)` — checks for existing user, creates guest_identity record if no account found in `backend/app/Domains/Auth/Services/AuthService.php`
- [ ] T052 [US3] Create AuthService method `checkExistingAccount(email)` — returns whether a registered account exists for a given email (used by checkout UI to prompt sign-in) in `backend/app/Domains/Auth/Services/AuthService.php`

**Checkpoint**: Guest identity system ready — checkout flows (spec 007) can now call AuthService to capture guest identity.

---

## Phase 6: User Story 4 — Automatic Account Creation After Guest Booking (Priority: P2)

**Goal**: After guest checkout, the traveler is offered to create a full account by setting a password. All previous guest bookings with the same email are linked to the new account.

**Independent Test**: Complete guest booking, accept account creation offer, set password, verify bookings linked.

### Tests for User Story 4

- [ ] T053 [P] [US4] Write guest conversion test: successful conversion, bookings linked, duplicate email handling, verification email in `backend/tests/Feature/Auth/GuestConversionTest.php`

### Implementation for User Story 4

- [ ] T054 [P] [US4] Create ConvertGuestRequest form request (email, name, password, password_confirmation, booking_reference) in `backend/app/Http/Requests/Auth/ConvertGuestRequest.php`
- [ ] T055 [US4] Create ConvertGuestToAccountAction with logic: create user from guest data, set password, link all guest bookings by email, mark guest_identity as converted, dispatch GuestConvertedToAccount event, queue verification email in `backend/app/Domains/Auth/Actions/ConvertGuestToAccountAction.php`
- [ ] T056 [US4] Create guest conversion controller (validate via ConvertGuestRequest, delegate to ConvertGuestToAccountAction, return UserResource + token + linked_bookings_count) in `backend/app/Http/Controllers/Public/Auth/GuestConversionController.php`
- [ ] T057 [US4] Register POST `/api/public/auth/guest/convert` route in `backend/routes/api/public.php`
- [ ] T058 [P] [US4] Create GuestConversionPrompt component: pre-filled name/email, password field, "Create account" CTA, "Skip" option, existing-account detection in `frontend/src/components/auth/GuestConversionPrompt.tsx`

**Checkpoint**: Guest-to-account conversion fully functional — guests can create accounts post-booking with automatic booking linkage.

---

## Phase 7: User Story 5 — Password Reset (Priority: P2)

**Goal**: Travelers who forgot their password can request a reset link via email (60-min validity), set a new password, and sign in with the new credentials. Reset is restricted to verified emails.

**Independent Test**: Request reset, receive email, click link, set new password, verify sign-in works.

### Tests for User Story 5

- [ ] T059 [P] [US5] Write password reset test: request for verified email, request for unverified email (sends verification instead), token expiry, token invalidation on use, generic response for nonexistent email in `backend/tests/Feature/Auth/PasswordResetTest.php`

### Implementation for User Story 5

- [ ] T060 [P] [US5] Create ForgotPasswordRequest (email required) and ResetPasswordRequest (email, token, password, password_confirmation) in `backend/app/Http/Requests/Auth/ForgotPasswordRequest.php` and `backend/app/Http/Requests/Auth/ResetPasswordRequest.php`
- [ ] T061 [P] [US5] Create PasswordResetMail mailable with multi-language support in `backend/app/Mail/PasswordResetMail.php`
- [ ] T062 [P] [US5] Create SendPasswordResetEmail queued job (retry-safe) in `backend/app/Jobs/SendPasswordResetEmail.php`
- [ ] T063 [US5] Create ResetPasswordAction: validate token, verify not expired (60 min), update password, invalidate all reset tokens for this email, dispatch PasswordReset event in `backend/app/Domains/Auth/Actions/ResetPasswordAction.php`
- [ ] T064 [US5] Create ForgotPasswordController: always return same response (anti-enumeration), check verification status — if verified queue reset email, if unverified queue verification email instead in `backend/app/Http/Controllers/Public/Auth/ForgotPasswordController.php`
- [ ] T065 [US5] Create ResetPasswordController (validate, delegate to ResetPasswordAction) in `backend/app/Http/Controllers/Public/Auth/ResetPasswordController.php`
- [ ] T066 [US5] Register POST `/api/public/auth/forgot-password` and POST `/api/public/auth/reset-password` routes in `backend/routes/api/public.php`
- [ ] T067 [P] [US5] Create ForgotPasswordForm component with email field, success message (same regardless of email existence) in `frontend/src/components/auth/ForgotPasswordForm.tsx`
- [ ] T068 [P] [US5] Create ResetPasswordForm component with password/confirmation fields, token validation, expired-link handling in `frontend/src/components/auth/ResetPasswordForm.tsx`
- [ ] T069 [US5] Create forgot-password and reset-password pages in `frontend/src/app/[locale]/auth/forgot-password/page.tsx` and `frontend/src/app/[locale]/auth/reset-password/page.tsx`

**Checkpoint**: Password reset fully functional — request, email delivery, token validation, password update, anti-enumeration protection.

---

## Phase 8: User Story 6 — Session Management & Account Settings (Priority: P3)

**Goal**: Sessions persist for 7 days of inactivity with auto-extension. Travelers can change their password from account settings and view active sessions.

**Independent Test**: Sign in, remain idle past timeout, verify re-auth required. Change password from settings, verify new password works.

### Tests for User Story 6

- [ ] T070 [P] [US6] Write session management test: token expiry after inactivity, token extension on use, multiple concurrent sessions in `backend/tests/Feature/Auth/SessionManagementTest.php`
- [ ] T071 [P] [US6] Write change password test: valid change with current password, incorrect current password, weak new password in `backend/tests/Feature/Auth/ChangePasswordTest.php`

### Implementation for User Story 6

- [ ] T072 [US6] Implement Sanctum token expiry middleware: check `expires_at`, reject expired tokens, extend `expires_at` on valid requests in `backend/app/Http/Middleware/RefreshTokenExpiry.php`
- [ ] T073 [P] [US6] Create ChangePasswordRequest form request (current_password required, password min 8 with strength rules, password_confirmation) in `backend/app/Http/Requests/Auth/ChangePasswordRequest.php`
- [ ] T074 [US6] Create ChangePasswordAction: verify current password, update password hash, invalidate reset tokens, dispatch PasswordChanged event in `backend/app/Domains/Auth/Actions/ChangePasswordAction.php`
- [ ] T075 [US6] Create ChangePasswordController (auth required, validate, delegate to ChangePasswordAction) in `backend/app/Http/Controllers/Public/Account/ChangePasswordController.php`
- [ ] T076 [P] [US6] Create ProfileController: GET returns UserResource, PUT updates name/phone/locale in `backend/app/Http/Controllers/Public/Account/ProfileController.php`
- [ ] T077 [US6] Create SessionController: GET lists active sessions (token id, name, last_used_at, is_current flag — never exposes token values) in `backend/app/Http/Controllers/Public/Account/SessionController.php`
- [ ] T078 [US6] Register account routes (GET/PUT `/api/public/account/profile`, PUT `/api/public/account/change-password`, GET `/api/public/account/sessions`) in `backend/routes/api/public.php`
- [ ] T079 [P] [US6] Create ChangePasswordForm component with current password, new password, confirmation fields in `frontend/src/components/auth/ChangePasswordForm.tsx`
- [ ] T080 [US6] Create account profile page with profile editing and password change sections in `frontend/src/app/[locale]/account/profile/page.tsx`

**Checkpoint**: Session management and account settings fully functional — token expiry, password change, profile updates, session listing.

---

## Phase 9: Email Verification Flow (Cross-Story)

**Purpose**: Email verification endpoints and frontend pages that support multiple user stories (registration, guest conversion, password reset gating)

- [ ] T081 Create VerifyEmailAction: validate signed URL, update email_verified_at, dispatch EmailVerified event in `backend/app/Domains/Auth/Actions/VerifyEmailAction.php`
- [ ] T082 Create VerifyEmailController (validate signed URL, delegate to VerifyEmailAction) in `backend/app/Http/Controllers/Public/Auth/VerifyEmailController.php`
- [ ] T083 [P] Create ResendVerificationController (auth required, check already verified, rate limit 3/hour, queue verification email) in `backend/app/Http/Controllers/Public/Auth/ResendVerificationController.php`
- [ ] T084 Write email verification test: valid verification, expired link, already verified, resend rate limiting in `backend/tests/Feature/Auth/EmailVerificationTest.php`
- [ ] T085 Register GET `/api/public/auth/verify-email/{id}/{hash}` and POST `/api/public/auth/resend-verification` routes in `backend/routes/api/public.php`
- [ ] T086 Create verify-email page with success/error/expired states in `frontend/src/app/[locale]/auth/verify-email/page.tsx`

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Guest data anonymization, security hardening, and final validation

- [ ] T087 Create AnonymizeStaleGuestIdentities scheduled job: find guest_identities where last booking > 24 months and no future bookings, clear PII in `backend/app/Jobs/AnonymizeStaleGuestIdentities.php`
- [ ] T088 Register AnonymizeStaleGuestIdentities in Laravel scheduler (daily) in `backend/app/Console/Kernel.php`
- [ ] T089 [P] Create UserPolicy for ownership authorization (users can only access their profile) and extend with role/permission capabilities (`hasRole`, `hasPermission`) in `backend/app/Policies/UserPolicy.php`
- [ ] T090 [P] Create RoleMiddleware and PermissionMiddleware (or equivalent Gate definitions) and apply them to all "auth required" routes to enforce role boundaries.
- [ ] T091 [P] Write unit tests for AuthService (lockout logic, failure counter, guest identity resolution) in `backend/tests/Unit/Auth/AuthServiceTest.php`
- [ ] T092 [P] Add feature tests in `tests/Feature` to completely assert the full authorization chain (authentication → role → permission → ownership) for protected endpoints.
- [ ] T093 [P] Write unit tests for LinkGuestBookingsAction (email matching, multiple bookings, no bookings) in `backend/tests/Unit/Auth/LinkGuestBookingsActionTest.php`
- [ ] T094 [P] Write frontend component tests for LoginForm and RegisterForm in `frontend/tests/components/auth/LoginForm.test.tsx` and `frontend/tests/components/auth/RegisterForm.test.tsx`
- [ ] T095 Run all backend tests (`php artisan test --filter=Auth`) and verify all pass
- [ ] T096 Validate translation key completeness: verify every error code from API contracts has a corresponding key in all 3 locale files (`frontend/src/i18n/{en,es,it}/auth.json`)
- [ ] T097 Run quickstart.md verification checklist end-to-end
- [ ] T098 Code cleanup: ensure no hardcoded secrets, all error messages use translation keys, all controllers are thin

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **US1 Registration (Phase 3)**: Depends on Foundational
- **US2 Sign-In/Out (Phase 4)**: Depends on Foundational (can parallel with US1)
- **US3 Guest Identity (Phase 5)**: Depends on Foundational (can parallel with US1, US2)
- **US4 Guest Conversion (Phase 6)**: Depends on US3 (guest identity must exist)
- **US5 Password Reset (Phase 7)**: Depends on Foundational (can parallel with US1–US4)
- **US6 Sessions/Settings (Phase 8)**: Depends on US2 (login must exist for auth-required routes)
- **Email Verification (Phase 9)**: Depends on US1 (registration sends verification)
- **Polish (Phase 10)**: Depends on all user stories complete

### User Story Dependencies

```
                    ┌───────────────────────────────┐
                    │      Phase 2: Foundational     │
                    └───────┬───┬───┬───┬───────────┘
                            │   │   │   │
              ┌─────────────┘   │   │   └─────────────┐
              ↓                 ↓   ↓                 ↓
         ┌────────┐      ┌────────┐ ┌────────┐  ┌────────┐
         │  US1   │      │  US2   │ │  US3   │  │  US5   │
         │Register│      │Sign-In │ │ Guest  │  │Pwd Reset│
         └───┬────┘      └───┬────┘ └───┬────┘  └────────┘
             │               │          │
             │               ↓          ↓
             │          ┌────────┐ ┌────────┐
             │          │  US6   │ │  US4   │
             │          │Sessions│ │Convert │
             │          └────────┘ └────────┘
             ↓
        ┌──────────┐
        │ Phase 9  │
        │Email Vfy │
        └──────────┘
```

### Parallel Opportunities

Within each user story phase, tasks marked `[P]` can run in parallel:

```bash
# Phase 2 parallel group (after T006):
T007, T008, T009, T010  # Migrations (parallel, different tables)
T012, T013              # Models (parallel, different files)
T015, T016              # Events + Listener (parallel, different files)
T020, T021, T022, T023, T024  # Frontend (parallel, different files)

# Phase 3 (US1) parallel group:
T027, T031, T032, T035  # Request, Mailable, Job, Component (different files)

# Phase 4 (US2) parallel group:
T037, T038, T039  # All test files (parallel)
T040, T045, T047  # Request, Controller, Component (parallel)

# Phase 6 (US4) parallel group:
T054, T058  # Request + Component (different files)

# Phase 7 (US5) parallel group:
T060, T061, T062, T067, T068  # Requests, Mailable, Job, Components

# Cross-story parallelism:
US1 (Phase 3), US2 (Phase 4), US3 (Phase 5), US5 (Phase 7) — all can start after Phase 2
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (Registration)
4. **STOP and VALIDATE**: Register a user, verify token returned, verification email queued
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US1 (Registration) → Test → **MVP!**
3. Add US2 (Sign-In/Out) → Test → Core auth complete
4. Add US3 + US4 (Guest Identity + Conversion) → Test → Guest flow complete
5. Add US5 (Password Reset) → Test → Account recovery complete
6. Add US6 (Sessions/Settings) → Test → Full auth suite
7. Add Email Verification + Polish → Production-ready

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: US1 (Registration) + Phase 9 (Email Verification)
   - Developer B: US2 (Sign-In/Out) + US6 (Sessions/Settings)
   - Developer C: US3 (Guest Identity) + US4 (Guest Conversion)
   - Developer D: US5 (Password Reset)
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Constitution mandates: thin controllers, server-side validation (Form Requests), queued email jobs, audit logging
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- The `bookings` table is NOT created in this feature (spec 007) — LinkGuestBookingsAction should handle the case where the bookings table doesn't exist yet
