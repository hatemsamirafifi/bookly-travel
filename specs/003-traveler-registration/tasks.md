# Tasks: Traveler Registration

**Input**: Design documents from `/specs/003-traveler-registration/`
**Prerequisites**: plan.md (loaded), spec.md (loaded), research.md (loaded), data-model.md (loaded), contracts/register.md (loaded)

**Tests**: Included — the parent spec (001-traveler-auth) mandates "Authentication and authorization gates" testing coverage per the constitution.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Backend**: `backend/` (Laravel 11)
- **Frontend**: `frontend/` (Next.js 16)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: This feature builds on Phase 2 (002-foundational-implementation), which is already complete. No project setup needed. Skip to Phase 2.

> Phase 1 inherited from `002-foundational-implementation` — all database tables, models, events, listeners, API scaffolding, frontend auth utilities, and factories are already in place.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Backend infrastructure components that ALL user stories in this feature depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T001 [P] Create `RegisterRequest` Form Request with server-side validation rules in `backend/app/Http/Requests/Auth/RegisterRequest.php`. Rules: `name: required|string|max:255`, `email: required|email|unique:users,email`, `password: required|string|min:8` + regex for at least one uppercase, one lowercase, one digit, `locale: sometimes|filled|in:en,es,it` (use `filled` so an empty string `""` is rejected; absent key uses default `"en"` in the action). Override `prepareForValidation()` to trim whitespace and lowercase the email. Return localized error messages mapped to translation keys. Must NOT include `password_confirmation` field.

- [x] T002 [P] Create `RegisterController` thin controller in `backend/app/Http/Controllers/Public/Auth/RegisterController.php`. The `__invoke` method: (1) accepts `RegisterRequest`, (2) delegates to `RegisterTravelerAction`, (3) returns a 201 JSON response. **IMPORTANT**: Do NOT return `new UserResource($user)` directly — `UserResource` adds its own `data` wrapper, producing double-nesting. Construct the envelope manually: `return response()->json(['data' => ['user' => new UserResource($user), 'token' => $result['token']]], 201);`. No business logic in the controller.

- [x] T003 Update `backend/routes/api/public.php`: (1) Register `Route::post('register', RegisterController::class)` inside the existing auth group. (2) Also register a named GET route `Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('auth.verify')->middleware(['signed'])` — this named route is required by `URL::temporarySignedRoute('auth.verify', ...)` in T006 to generate the verification link. Without this named route, signed URL generation will throw a `RouteNotFoundException` at runtime.

**Checkpoint**: API scaffolding complete — registration endpoint accepts requests but the action doesn't exist yet.

---

## Phase 3: User Story 1 — New Visitor Registration (Priority: P1) 🎯 MVP

**Goal**: A visitor submits name, email, and password. The system creates a traveler account, signs them in with a Sanctum token, queues a verification email, dispatches the `TravelerRegistered` event for audit logging, and returns the user data + token.

**Independent Test**: `POST /api/public/auth/register` with valid data returns 201 with user + token. Invalid data returns 422 with per-field errors. Duplicate email returns 422 with "email taken". Auth audit log has a `registration` entry.

### Implementation for User Story 1

- [x] T004 [P] [US1] Create `LinkGuestBookingsAction` in `backend/app/Domains/Auth/Actions/LinkGuestBookingsAction.php`. Accepts a `User` instance. Queries `guest_identities` table for records matching the user's email. If the `bookings` table exists (check via `Schema::hasTable('bookings')`), updates `bookings.user_id` to the new user's ID for all matching guest identity records. If the bookings table does not exist, no-op gracefully. Updates `guest_identities.converted_user_id` to the new user's ID for matching records.

- [x] T005 [P] [US1] Create `SendVerificationEmailAction` in `backend/app/Domains/Auth/Actions/SendVerificationEmailAction.php`. Accepts a `User` instance. Dispatches the `SendVerificationEmail` job to the Redis queue.

- [x] T006 [P] [US1] Create `SendVerificationEmail` queued job in `backend/app/Jobs/SendVerificationEmail.php`. Implements `ShouldQueue`. Accepts a `User` instance in the constructor. In `handle()`: (1) Generate a signed verification URL using `URL::temporarySignedRoute('auth.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)])` — the named route `auth.verify` is registered in T003. (2) Instantiate and send `new VerificationMail($user, $verificationUrl)`. Set `$tries = 3` and `$backoff = [10, 60, 300]` for retry safety. This job is idempotent — resending a verification email is always safe.

- [x] T007 [P] [US1] Create `VerificationMail` mailable in `backend/app/Mail/VerificationMail.php`. Accepts a `User` and a `string $verificationUrl`. Uses a Blade email template at `backend/resources/views/emails/auth/verify.blade.php` with the traveler's name, a verification button/link, and the platform name. Support the user's locale for the email subject line.

- [x] T008 [US1] Create `RegisterTravelerAction` in `backend/app/Domains/Auth/Actions/RegisterTravelerAction.php`. Accepts validated data array (name, email, password, locale). Orchestrates the full flow in this exact order: (1) Create User with `User::create()` — role defaults to 'traveler', (2) Call `LinkGuestBookingsAction` for the new user, (3) Dispatch `TravelerRegistered` event (which triggers `LogAuthEvent` listener for audit logging), (4) Call `SendVerificationEmailAction`, (5) Create and return a Sanctum token via `$user->createToken('auth-token')`. Returns `['user' => $user, 'token' => $plainTextToken]`.

### Tests for User Story 1

- [x] T009 [US1] Create `RegistrationTest` feature tests in `backend/tests/Feature/Auth/RegistrationTest.php` using Pest. Must cover:
  - **Happy path**: POST `/api/public/auth/register` with valid name/email/password returns 201 with `data.user` and `data.token`. User exists in database. `auth_audit_logs` has a `registration` event_type entry for the new user.
  - **Duplicate email**: POST with taken email returns 422 with `error.details.email` containing "taken" message.
  - **Weak password**: POST with password missing uppercase returns 422 with password validation error.
  - **Missing fields**: POST with empty body returns 422 with errors for name, email, and password.
  - **Verification email queued**: Assert `SendVerificationEmail` job was dispatched to queue after successful registration (use `Queue::fake()`).
  - **Guest booking linkage**: Create a `GuestIdentity` with email "test@example.com", register with same email, assert `guest_identities.converted_user_id` is set to new user ID.
  - **Locale handling**: POST with `locale: "es"` creates user with locale "es". POST without locale creates user with default "en".
  - **Email normalization**: POST with email " Test@Example.COM " creates user with email "test@example.com".
  - **Rate limiting**: Assert 11th request within a minute returns 429.

**Checkpoint**: Backend registration is fully functional and tested. `php artisan test --filter=RegistrationTest` passes.

---

## Phase 4: User Story 2 — Guest Booking Linkage on Registration (Priority: P1)

**Goal**: When a traveler registers with an email previously used for guest checkout, all guest bookings are automatically linked to the new account.

**Independent Test**: Already covered by T004 (`LinkGuestBookingsAction`) and T009 (guest booking linkage test). This story's implementation is integrated into the registration flow.

> **Note**: This user story's implementation is entirely handled by `LinkGuestBookingsAction` (T004), which is called from `RegisterTravelerAction` (T008). No additional tasks are needed — the logic is already part of US1's registration flow. The test in T009 validates this story independently.

**Checkpoint**: Guest booking linkage verified via test in T009.

---

## Phase 5: User Story 3 — Multi-Language Registration (Priority: P2)

**Goal**: The registration page renders in all three languages (EN/ES/IT). All labels, placeholders, validation errors, and success messages are localized. The traveler's preferred language is stored with their account.

**Independent Test**: Navigate to `/es/auth/register`, verify all text is in Spanish. Submit invalid data, verify error messages are in Spanish. Register successfully, verify the account's locale is "es".

### Implementation for User Story 3

- [x] T010 [P] [US3] Add registration form translation keys to `frontend/messages/en.json` under `auth.register` namespace. Keys: `title`, `subtitle`, `nameLabel`, `namePlaceholder`, `emailLabel`, `emailPlaceholder`, `passwordLabel`, `passwordPlaceholder`, `submitButton`, `signinPrompt`, `signinLink`, `successMessage`. Also add `auth.errors.nameRequired` and `auth.errors.invalidLocale` to the errors section.

- [x] T011 [P] [US3] Add registration form translation keys to `frontend/messages/es.json` — same key structure as T010, all values translated to Spanish.

- [x] T012 [P] [US3] Add registration form translation keys to `frontend/messages/it.json` — same key structure as T010, all values translated to Italian.

- [x] T013 [US3] First, verify and update the `registerSchema` in `frontend/src/lib/validators/auth.ts` — confirm it includes `name: z.string().min(1, 'auth.errors.nameRequired').max(255)` as a required field alongside `email` and `password`. The existing Phase 2 schema may be missing the `name` field. Then create `RegisterForm` client component in `frontend/src/components/auth/RegisterForm.tsx`. Uses `useTranslations('auth')` from next-intl for all displayed text. Form fields: name (text input), email (email input), password (password input with show/hide toggle). Client-side validation using the updated `registerSchema`. On submit: call `authApi.register()`, on success call `auth.setAuth(user, token)` from `useAuth()` hook, then redirect to `returnUrl` or locale homepage. Display per-field validation errors (both client-side Zod and server-side API `error.details` errors mapped to fields). Show loading spinner during submission. Style with a premium design using Tailwind utility classes and the project's design system tokens (not custom globals.css variables) for the dark-friendly card layout, transitions and spacing.

- [x] T014 [US3] Create registration page in `frontend/src/app/[locale]/auth/register/page.tsx`. Server component that renders the `RegisterForm` inside an `AuthGuard` with `requireAuth={false}` (guest-only page — redirect authenticated users away). Read `returnUrl` from URL search params and pass to `RegisterForm`. Include SEO metadata: title "Create Account | Bookly", meta description. Use proper heading hierarchy (`<h1>` for page title).

**Checkpoint**: Registration page is fully functional in EN, ES, IT. Client-side validation works. Successful registration redirects.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final validation, cleanup, and verification across all stories.

- [X] T015 Run the `quickstart.md` verification checklist end-to-end: navigate to `/en/auth/register`, `/es/auth/register`, and `/it/auth/register`; register a new user from each locale; confirm redirect to homepage (or returnUrl); verify verification email in Mailpit (http://localhost:8025); verify `auth_audit_logs` has `registration` event; verify Sanctum token in `personal_access_tokens`; verify rate limiter returns 429 after 10 rapid requests. Document pass/fail for each checklist item.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Inherited from 002-foundational-implementation — ✅ Complete
- **Foundational (Phase 2)**: T001, T002, T003 — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Phase 2 completion
- **User Story 2 (Phase 4)**: Integrated into US1 — no additional tasks
- **User Story 3 (Phase 5)**: Depends on Phase 2 (backend) — can start translations (T010–T012) in parallel with US1
- **Polish (Phase 6)**: T015 — Depends on all user stories being complete

### User Story Dependencies

- **US1 (P1)**: Can start after Phase 2. No dependency on other stories.
- **US2 (P1)**: Fully integrated into US1 registration flow (T004, T008). No separate dependency.
- **US3 (P2)**: Frontend work depends on Phase 2 (API exists). Translation tasks (T010–T012) can run in parallel with US1 backend tasks. RegisterForm (T013) depends on API being available. Page (T014) depends on T013.

### Within Each Phase

- Phase 2: T001 and T002 are parallel. T003 depends on T002 (needs controller to route to).
- Phase 3: T004, T005, T006, T007 are all parallel. T008 depends on T004 and T005. T009 depends on T008.
- Phase 5: T010, T011, T012 are all parallel. T013 depends on translations. T014 depends on T013.

### Parallel Opportunities

```text
Phase 2 parallel group:     T001 ║ T002 → then T003
Phase 3 parallel group:     T004 ║ T005 ║ T006 ║ T007 → then T008 → then T009
Phase 5 parallel group:     T010 ║ T011 ║ T012 → then T013 → then T014
Cross-phase parallel:       T010–T012 (translations) can run alongside T004–T009 (backend)
```

---

## Parallel Example: User Story 1

```bash
# Launch all action classes together (different files, no dependencies):
Task: "Create LinkGuestBookingsAction in backend/app/Domains/Auth/Actions/LinkGuestBookingsAction.php"
Task: "Create SendVerificationEmailAction in backend/app/Domains/Auth/Actions/SendVerificationEmailAction.php"
Task: "Create SendVerificationEmail job in backend/app/Jobs/SendVerificationEmail.php"
Task: "Create VerificationMail mailable in backend/app/Mail/VerificationMail.php"

# Then orchestrate (depends on all above):
Task: "Create RegisterTravelerAction in backend/app/Domains/Auth/Actions/RegisterTravelerAction.php"

# Then test (depends on action):
Task: "Create RegistrationTest in backend/tests/Feature/Auth/RegistrationTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 2: Foundational (T001–T003)
2. Complete Phase 3: User Story 1 (T004–T009)
3. **STOP and VALIDATE**: Run `php artisan test --filter=RegistrationTest`
4. Backend registration is fully functional — test via curl/Postman

> Total task count reduced to **15** (T001–T015) after merging T015+T016 into a single polish task.

### Incremental Delivery

1. Complete Phase 2 + Phase 3 → Backend MVP ready ✅
2. Add Phase 5 → Full frontend + i18n ✅
3. Complete Phase 6 → End-to-end verified ✅

### Parallel Team Strategy

With two developers:

1. Both complete Phase 2 together (3 tasks)
2. Once Phase 2 done:
   - **Developer A**: Phase 3 — US1 backend (T004–T009)
   - **Developer B**: Phase 5 — US3 translations + frontend (T010–T014)
3. Phase 6 — joint verification

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- US2 is architecturally integrated into US1 — no separate phase tasks needed
- Guest booking linkage must be resilient to missing `bookings` table (use `Schema::hasTable()`)
- Email normalization (trim + lowercase) should happen in `RegisterRequest::prepareForValidation()`
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
