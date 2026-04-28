# Tasks: Traveler Sign-In and Sign-Out

**Input**: Design documents from `/specs/004-traveler-signin/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Backend feature tests (Pest) are included per spec success criteria and constitution Minimum Testing Coverage mandate.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Verify Existing Infrastructure)

**Purpose**: Confirm all foundational auth infrastructure from specs 002 and 003 is in place. No new infrastructure is needed for this feature.

- [x] T001 Verify existing auth events and listeners are registered in `backend/app/Providers/EventServiceProvider.php`. Confirm: `TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut` events are mapped to `App\Domains\Auth\Listeners\LogAuthEvent::class`. Also: delete the stale `specs/004-traveler-signin/contracts/auth-api.md` file — it is superseded by `login-api.md` and `logout-api.md` and must be removed to avoid confusing the implementer.
- [x] T002 Verify `backend/app/Models/User.php` has casts for: `locked_until` (datetime), `last_login_at` (datetime), `password` (hashed). Check `$fillable`: if `failed_login_count`, `locked_until`, or `last_login_at` are MISSING from `$fillable`, add them immediately. Then confirm they are present. These fields MUST be mass-assignable for the action class to update them via direct property assignment (`$user->failed_login_count = $count; $user->save();`).
- [x] T003 Verify and fix `frontend/src/lib/api/auth.ts`. Check: (1) `authApi.login()` calls `POST /public/auth/login` and `authApi.logout()` calls `POST /public/auth/logout` with Bearer token. (2) The `AuthApiError` class MUST have a `code?: string` field added alongside the existing `errors` field. (3) The `fetchApi` function MUST pass `data?.code` to the `AuthApiError` constructor: `throw new AuthApiError(data?.message || 'Authentication failed', data?.errors, data?.code)`. (4) Update the constructor signature: `constructor(message: string, errors?: Record<string, string[]>, code?: string)`. (5) Verify `frontend/src/lib/hooks/useAuth.tsx` has `login()` and `logout()` methods.
- [x] T004 Verify `frontend/src/lib/validators/auth.ts` has `loginSchema` with `email` (string, email) and `password` (string, min 1).

**Checkpoint**: All prerequisite files exist and are functional. Backend events, User model, frontend auth API, and validators are confirmed.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Confirm no new migrations or infrastructure are needed. This phase is verification-only since 002-foundational-implementation already created everything.

**NOTE**: No blocking prerequisites exist. All foundational work (database schema, events, listeners, middleware, authApi, useAuth, loginSchema) is already complete from previous specs.

**Checkpoint**: Foundation ready — user story implementation can begin immediately.

---

## Phase 3: User Story 1 — Traveler Sign-In (Priority: P1) 🎯 MVP

**Goal**: Implement the backend login endpoint and frontend login page so travelers can sign in with email and password. This phase also implements the brute-force protection logic (User Story 3) because it lives inside the same `AuthenticateTravelerAction`.

**Independent Test**: Navigate to `/en/auth/login`, submit valid email and password, confirm the traveler is signed in and redirected. Verify `last_login_at` is updated and an audit log entry exists.

### Backend Implementation

- [x] T005 [P] [US1] Create `backend/app/Http/Requests/Auth/LoginRequest.php`. Copy the exact pattern from `backend/app/Http/Requests/Auth/RegisterRequest.php`. Requirements: (1) Class extends `FormRequest`, (2) `authorize()` returns `true`, (3) `rules()` returns `email` (required, string, email) and `password` (required, string, min:1), (4) `prepareForValidation()` normalizes email with `trim()` + `strtolower()`, (5) For the `messages()` method, return standard English strings (e.g., `'email.required' => 'Email is required.'`). Do NOT use frontend translation key names here — Laravel's Form Request `messages()` uses `resources/lang/` PHP localization, not frontend JSON. The frontend maps the response `code` field to localized strings independently (handled in T012 step 9 and T027), (6) No custom messages that reveal whether email exists.

- [x] T006 [US1] Create `backend/app/Domains/Auth/Actions/AuthenticateTravelerAction.php`. This is the CORE business logic file. Copy the pattern from `backend/app/Domains/Auth/Actions/RegisterTravelerAction.php`. The `execute(array $data)` method must:
  1. Normalize email: `strtolower(trim($data['email']))`.
  2. Find user by normalized email: `User::where('email', $normalizedEmail)->first()`.
  3. **Lockout check**: If user exists AND `user->locked_until` is not null AND `user->locked_until->isFuture()`, return `['success' => false, 'locked' => true, 'message' => 'Too many failed attempts. Please try again later.']`.
  4. **Credential verification**: If no user found OR `!Hash::check($data['password'], $user->password)`, handle failure:
     - If user exists: increment `failed_login_count` by 1. Save using direct property assignment (`$user->failed_login_count = $newCount; $user->save();`) — do NOT use `$user->update([...])` because these fields may not be in `$fillable`.
     - Dispatch `LoginFailed` event: `event(new LoginFailed($normalizedEmail, $user))`. If user does not exist, pass `null` as the second argument: `event(new LoginFailed($normalizedEmail, null))`.
     - If `failed_login_count == 5`: determine lockout tier by counting `account_lockout` events in `auth_audit_logs` for this user that occurred AFTER the user's most recent `login_success` event (i.e., since the last successful sign-in). If the user has never signed in successfully, count all `account_lockout` events. 1st lockout = 1 minute, 2nd = 5 minutes, 3rd+ = 30 minutes. If no prior lockouts are found (e.g., audit log purged), default to 1st tier (1 minute). Set `locked_until = now + tier_duration`. Save using direct property assignment (`$user->locked_until = $lockedUntil; $user->save();`). Dispatch `AccountLockedOut($user)` event.
     - Return `['success' => false, 'message' => 'Invalid email or password.']`.
  5. **Success path**: If credentials match:
     - Reset `failed_login_count = 0`, `locked_until = null`, update `last_login_at = now()`. Save using direct property assignment (`$user->failed_login_count = 0; $user->locked_until = null; $user->last_login_at = now(); $user->save();`) — do NOT use `$user->update([...])`.
     - Create Sanctum token: `$user->createToken('auth-token')`.
     - Dispatch `TravelerLoggedIn($user)` event.
     - Return `['success' => true, 'user' => $user, 'token' => $plainTextToken]`.
  6. **Security**: The generic message `'Invalid email or password.'` must be returned for ALL failure cases (wrong email, wrong password, missing user, no password) to prevent enumeration.

- [x] T007 [US1] Create `backend/app/Http/Controllers/Public/Auth/LoginController.php`. Copy the exact pattern from `backend/app/Http/Controllers/Public/Auth/RegisterController.php`. Requirements: (1) Class is invokable (`__invoke`), (2) Accept `LoginRequest $request`, (3) Call `AuthenticateTravelerAction::execute($request->validated())`, (4) If `success` is false and `locked` is true, return `response()->json(['code' => 'account_locked', 'message' => $result['message']], 423)`, (5) If `success` is false, return `response()->json(['code' => 'invalid_credentials', 'message' => $result['message']], 422)`, (6) If success, return `response()->json(['data' => ['user' => new UserResource($result['user']), 'token' => $result['token']]], 200)`.

- [x] T008 [US1] Add login route to `backend/routes/api/public.php`. Inside the existing `Route::prefix('auth')->middleware('throttle:auth')->group(function () { ... })`, add: `Route::post('login', LoginController::class);`. Place it after the existing `Route::post('register', ...)` line. Do NOT remove or modify existing routes.

### Frontend Implementation

- [x] T009 [P] [US1] Add login page translations to `frontend/messages/en.json`. The existing `"auth"` object already contains `"login": "Sign In"` as a string (used for nav buttons). Do NOT overwrite this string. Instead, add a NEW `"signin"` object with these exact keys (matching the `"register"` object structure): `title` ("Welcome back"), `subtitle` ("Sign in to access your bookings and reviews"), `emailLabel` ("Email Address"), `emailPlaceholder` ("you@example.com"), `passwordLabel` ("Password"), `submitButton` ("Sign In"), `registerPrompt` ("Don't have an account?"), `registerLink` ("Create account"), `forgotPasswordLink` ("Forgot password?"), `metaTitle` ("Sign In | Bookly"), `metaDescription` ("Sign in to your Bookly traveler account to manage bookings, download vouchers, and leave reviews."). Also add to the existing `"errors"` object: `"sessionExpired"` ("Your session has expired. Please sign in again."). Do NOT modify existing keys.

- [x] T010 [P] [US1] Add login page translations to `frontend/messages/es.json`. Do NOT overwrite the existing `"login": "Iniciar sesión"` string. Add a NEW `"signin"` object with the same structure as T009 but in Spanish: `title` ("Bienvenido de nuevo"), `subtitle` ("Inicia sesión para acceder a tus reservas y reseñas"), `emailLabel` ("Correo electrónico"), `emailPlaceholder` ("tu@ejemplo.com"), `passwordLabel` ("Contraseña"), `submitButton` ("Iniciar sesión"), `registerPrompt` ("¿No tienes una cuenta?"), `registerLink` ("Crear cuenta"), `forgotPasswordLink` ("¿Olvidaste tu contraseña?"), `metaTitle` ("Iniciar sesión | Bookly"), `metaDescription` ("Inicia sesión en tu cuenta de viajero de Bookly para gestionar reservas, descargar vouchers y dejar reseñas."). Also add to `"errors"`: `"sessionExpired"` ("Tu sesión ha expirado. Inicia sesión de nuevo."). Do NOT modify existing keys.

- [x] T011 [P] [US1] Add login page translations to `frontend/messages/it.json`. Do NOT overwrite the existing `"login": "Accedi"` string. Add a NEW `"signin"` object con the same structure as T009 but in Italian: `title` ("Bentornato"), `subtitle` ("Accedi per gestire le tue prenotazioni e recensioni"), `emailLabel` ("Indirizzo email"), `emailPlaceholder` ("tu@esempio.com"), `passwordLabel` ("Password"), `submitButton` ("Accedi"), `registerPrompt` ("Non hai un account?"), `registerLink` ("Crea account"), `forgotPasswordLink` ("Hai dimenticato la password?"), `metaTitle` ("Accedi | Bookly"), `metaDescription` ("Accedi al tuo account viaggiatore Bookly per gestire prenotazioni, scaricare voucher e lasciare recensioni."). Also add to `"errors"`: `"sessionExpired"` ("La tua sessione è scaduta. Accedi di nuovo."). Do NOT modify existing keys.

- [x] T012 [US1] Create `frontend/src/components/auth/LoginForm.tsx`. Copy the exact component structure from `frontend/src/components/auth/RegisterForm.tsx` and adapt it for login. Requirements:
  1. `'use client'` directive at top.
  2. Import `useForm` from `react-hook-form`, `zodResolver` from `@hookform/resolvers/zod`, `z` from `zod`, `useTranslations` and `useLocale` from `next-intl`, `useRouter` from `next/navigation`, `Link` from `next/link`.
  3. Import `loginSchema` from `@/lib/validators/auth`, `useAuth` from `@/lib/hooks/useAuth`, `AuthApiError` from `@/lib/api/auth`.
  4. Define `type LoginFormData = z.infer<typeof loginSchema>`.
  5. Define `interface LoginFormProps { returnUrl?: string; sessionExpired?: boolean; }`. If `sessionExpired` is true, display `t('errors.sessionExpired')` as an info banner (styled with `bg-warning/10 border-warning/30 text-warning`) above the form on initial mount. Clear the banner when the user submits the form.
  6. Use a single `useTranslations('auth')` call. Access nested keys as `t('signin.title')`, `t('signin.emailLabel')`, `t('errors.invalidCredentials')`, etc. Do NOT call `useTranslations` more than once in the same component.
  7. Call `const { login } = useAuth();` at the top of the component. In the `onSubmit` handler, call `await login(data)` passing the form data `{ email, password }` directly — the `loginSchema` validation is already handled by `react-hook-form` + `zodResolver`.
  8. Validate `returnUrl` before use: only redirect if `returnUrl` starts with `/${locale}/` (same-origin relative path). If `returnUrl` is missing, empty, external (starts with `http`), or does not start with `/${locale}/`, redirect to `/${locale}/` instead.
  9. On success, redirect using `router.push(validatedReturnUrl)` after `await login(data)` returns.
  10. On error, handle THREE distinct error cases by checking the caught error:
      - **Zod validation errors**: Display field-level errors inline below each input (handled automatically by `react-hook-form`).
      - **422 with `code: 'invalid_credentials'`**: Check `err instanceof AuthApiError && err.code === 'invalid_credentials'`. Display `t('errors.invalidCredentials')` in the server error banner.
      - **423 with `code: 'account_locked'`**: Check `err instanceof AuthApiError && err.code === 'account_locked'`. Display `t('errors.accountLocked')` in the server error banner. The form should remain submittable but will continue to be rejected server-side until the lockout expires.
      - **Fallback**: For any other error, display `err.message` directly.
  11. Form fields: email input (type="email", autocomplete="email") and password input (type="password", autocomplete="current-password"). Include show/hide password toggle button (copy from RegisterForm).
  12. Include a "Forgot password?" link pointing to `/${locale}/auth/forgot-password` (non-functional placeholder for spec 005).
  13. Include a "Don't have an account? Create account" link pointing to `/${locale}/auth/register`.
  14. Use the EXACT same Tailwind CSS classes as RegisterForm for consistent styling (input borders, focus rings, error states, submit button, etc.).
  15. Set `noValidate` on the form and `aria-label={t('signin.title')}`.

- [x] T013 [US1] Create `frontend/src/app/[locale]/auth/login/page.tsx`. Copy the exact structure from `frontend/src/app/[locale]/auth/register/page.tsx` and adapt for login. Requirements:
  1. Import `Metadata` from `next`, `getTranslations` from `next-intl/server`, `AuthGuard` from `@/components/auth/AuthGuard`, `LoginForm` from `@/components/auth/LoginForm`.
  2. `generateMetadata` function using `auth.signin.metaTitle` and `auth.signin.metaDescription` with canonical URL `/${locale}/auth/login`.
  3. `LoginPage` is an async server component accepting `{ searchParams: Promise<{ returnUrl?: string; sessionExpired?: string }> }`.
  4. Extract `returnUrl` and `sessionExpired` from `searchParams`: `const { returnUrl, sessionExpired } = await searchParams;`.
  5. Use the EXACT same page layout HTML structure as register/page.tsx (gradient background, centered card, Bookly header).
  6. Wrap `LoginForm` in `<AuthGuard requireAuth={false}>`.
  7. Pass `returnUrl` and `sessionExpired={sessionExpired === '1'}` as props to `LoginForm`.

### Tests for User Story 1

- [x] T014 [US1] Create `backend/tests/Feature/Auth/LoginTest.php`. Copy the exact pattern from `backend/tests/Feature/Auth/RegistrationTest.php`. Write Pest-style tests:
  1. `it('signs in a traveler with valid credentials')`: POST to `/api/public/auth/login` with valid email/password. Assert 200 status, JSON structure contains `data.user.id`, `data.user.email`, `data.token`. Assert `last_login_at` is updated in database. Assert `auth_audit_logs` has `login_success` entry.
  2. `it('fails with generic error for non-existent email')`: POST with non-existent email. Assert 422 status, message is exactly `"Invalid email or password."`.
  3. `it('fails with generic error for wrong password')`: POST with valid email but wrong password. Assert 422 status, message is exactly `"Invalid email or password."`.
  4. `it('fails validation with empty fields')`: POST with empty body. Assert 422 status with validation errors on `email` and `password`.
  5. `it('normalizes email with whitespace and uppercase')`: POST with `"  UPPER@Example.COM  "` and correct password. Assert 200 status (email is normalized before lookup).
  6. `it('fails for account with no password')`: Create user with `password = null` (or empty), attempt login. Assert 422 with generic message.
  7. `it('returns structured error codes')`: POST with wrong password. Assert 422 status and JSON contains `code: 'invalid_credentials'`. POST to a locked account. Assert 423 status and JSON contains `code: 'account_locked'`.

**Checkpoint**: At this point, User Story 1 (basic sign-in) is fully functional and independently testable. The login page renders, accepts credentials, issues tokens, updates `last_login_at`, and writes audit logs.

---

## Phase 4: User Story 2 — Traveler Sign-Out (Priority: P1)

**Goal**: Implement the backend logout endpoint and verify frontend integration so travelers can securely end their session from the current device only.

**Independent Test**: Sign in, verify access to authenticated content, send logout request with Bearer token, verify token is revoked and authenticated content is no longer accessible. Verify other sessions remain active.

### Backend Implementation

- [x] T015 [P] [US2] Create `backend/app/Domains/Auth/Actions/LogoutTravelerAction.php`. Copy the pattern from existing actions. The `execute(User $user): void` method must:
  1. Get the current token via `$user->currentAccessToken()`.
  2. If the token is a `PersonalAccessToken` instance, delete ONLY that token (`$token->delete()`).
  3. Do NOT delete other tokens for the same user.
  4. Return void.

- [x] T016 [US2] Create `backend/app/Http/Controllers/Public/Auth/LogoutController.php`. Copy the invokable controller pattern. Requirements: (1) Apply `auth:sanctum` middleware, (2) In `__invoke(Request $request)`, get authenticated user via `$request->user()`, (3) Delegate token revocation to `LogoutTravelerAction`: `app(LogoutTravelerAction::class)->execute($request->user())`. Do NOT access `$request->user()->currentAccessToken()->delete()` directly in the controller — this violates the Thin Controllers and No Direct DB Access constitution rules. (4) Return `response()->noContent()` (HTTP 204). **NOTE**: The current implementation uses return type `void` instead of returning `response()->noContent()`. This should be corrected during Phase 7 polish (T028) to explicitly return 204 — the tests in T019 assert `assertStatus(204)` and rely on this.

- [x] T017 [US2] Add logout route to `backend/routes/api/public.php`. Inside the existing auth route group, add: `Route::post('logout', LogoutController::class)->middleware('auth:sanctum');`. Place it after the login route. Do NOT remove existing routes.

### Frontend Integration

- [x] T018 [US2] Implement or verify `frontend/src/lib/hooks/useAuth.tsx` logout integration. Read the existing `logout()` method and check ALL of the following. If any check fails, edit the file to fix it:
  1. Calls `authApi.logout(token)` with the current token BEFORE clearing state.
  2. On success OR failure (use a `finally` block), sets `user = null`, `token = null`.
  3. After clearing state, redirects to `/${locale}/` using `router.push(`/${locale}/`)`. The current `logout()` method does NOT redirect and has no access to `locale` or `router`. To fix: add `import { useLocale } from 'next-intl'` and `import { useRouter } from 'next/navigation'` to the file. Inside `AuthProvider`, call `const locale = useLocale(); const router = useRouter();`. Then add `router.push(`/${locale}/`)` as the LAST line inside the `finally` block of `logout()`, after `setIsLoading(false)` and before the closing brace.
  4. **IMPORTANT**: Before adding `useLocale()`, verify in `frontend/src/app/[locale]/layout.tsx` that `AuthProvider` is rendered INSIDE `NextIntlClientProvider`, not outside it. If `AuthProvider` wraps `NextIntlClientProvider`, the `useLocale()` hook will throw a runtime error because it requires the intl context. If the nesting order is wrong, swap the providers so `NextIntlClientProvider` is the outer wrapper.
  5. The `AuthApiError` class in `frontend/src/lib/api/auth.ts` is imported and available.
  6. If the logout API call throws, the error is caught and the state is still cleared (no stuck authenticated state).

### Tests for User Story 2

- [x] T019 [US2] Create `backend/tests/Feature/Auth/LogoutTest.php`. Write Pest-style tests:
  1. `it('revokes the current token on logout')`: Create user, create token, send POST to `/api/public/auth/logout` with Bearer token. Assert 204 status. Assert token is deleted from `personal_access_tokens`.
  2. `it('returns 401 for revoked token')`: Logout, then send authenticated request with same token. Assert 401 status.
  3. `it('keeps other sessions active after logout')`: Create user, create two tokens (simulating two devices), logout with first token. Assert first token is deleted, second token still exists in `personal_access_tokens` and can still authenticate.
  4. `it('returns 401 without token')`: Send POST to logout without Authorization header. Assert 401 status.

**Checkpoint**: At this point, User Story 2 is fully functional. Travelers can sign out, only their current session ends, and other device sessions remain active.

---

## Phase 5: User Story 3 — Brute-Force Protection (Priority: P1)

**Goal**: Verify and test the brute-force protection implemented in T006. This phase is testing and edge-case verification since the implementation is inside `AuthenticateTravelerAction`.

**Independent Test**: Attempt to sign in with incorrect passwords 5 times for the same account. Verify the 6th attempt is blocked with a lockout message. Wait for lockout to expire, sign in successfully, verify counter resets. Verify tier escalation (1min → 5min → 30min).

### Tests and Verification

- [x] T020 [US3] Add brute-force protection tests to `backend/tests/Feature/Auth/LoginTest.php`. Append these Pest tests to the file:
  1. `it('locks account after 5 failed attempts with 1 minute tier')`: Submit 5 wrong passwords. Assert 422 each time. On 6th attempt (even with correct password), assert 423 status with message `"Too many failed attempts. Please try again later."`. Assert `failed_login_count == 5` and `locked_until` is approximately 1 minute in the future. Assert `auth_audit_logs` has `account_lockout` entry.
  2. `it('rejects login while account is locked even with correct password')`: Lock account, then submit correct credentials. Assert 423 status.
  3. `it('resets counter and tier on successful login after lockout expires')`: Wait for lockout to expire (use Laravel's `travel()` time manipulation in tests), sign in successfully. Assert `failed_login_count == 0`, `locked_until` is null. Then trigger 5 more failures. Assert the NEW lockout is 1 minute (tier resets after successful login per spec FR-009).
  4. `it('escalates to 30 minute tier on third lockout')`: Trigger lockout 3 times, verifying durations: 1st = 1min, 2nd = 5min, 3rd = 30min.
  5. `it('resets failed count on successful login before reaching 5')`: Fail 3 times, then succeed on 4th. Assert `failed_login_count == 0` after success.

- [x] T021 [US3] Add brute-force edge case tests to `backend/tests/Feature/Auth/LoginTest.php`:
  1. `it('handles concurrent failed login requests safely')`: Simulate two simultaneous failed login requests for same account. Assert both increment the counter. Worst case is double-increment, which is acceptable per spec.
  2. `it('survives redis cache flush during lockout')`: Lock account, manually verify `locked_until` is in DB (not Redis). This is a documentation/verification test — the lockout is DB-backed by design.

- [x] T022 [US3] Verify frontend displays lockout message correctly. Confirm `frontend/src/components/auth/LoginForm.tsx` handles 423 responses by displaying the `auth.errors.accountLocked` translation key ("Too many failed attempts. Please try again later.") in the server error display area.

**Checkpoint**: Brute-force protection is verified. Account locks after exactly 5 failures, tiers escalate correctly, reset on success works, and frontend shows appropriate messages.

---

## Phase 6: User Story 4 — Multi-Language Sign-In (Priority: P2)

**Goal**: Ensure the sign-in page renders correctly in all three supported languages (English, Spanish, Italian) with all text properly translated.

**Independent Test**: Switch to each of the three supported languages and attempt the sign-in flow (including triggering validation errors), verifying all text is correctly translated.

### Verification

- [x] T023 [P] [US4] Verify `frontend/src/components/auth/LoginForm.tsx` uses `useTranslations('auth')` for ALL user-facing text and accesses keys under `auth.signin.*` and `auth.errors.*`. Confirm no hardcoded English strings exist in the component. Check: labels, placeholders, button text, error messages, links ("Forgot password?", "Create account").

- [x] T024 [P] [US4] Verify `frontend/src/app/[locale]/auth/login/page.tsx` uses `getTranslations('auth.signin')` for metadata (title, description, OpenGraph). Confirm `generateMetadata` returns localized strings.

- [x] T025 [US4] Verify Spanish translations are complete in `frontend/messages/es.json`. Check that `auth.signin.*` keys exist and all values are in Spanish. Verify `auth.errors.invalidCredentials` and `auth.errors.accountLocked` are in Spanish. Confirm the existing `auth.login` string ("Iniciar sesión") was NOT overwritten by the object.

- [x] T026 [US4] Verify Italian translations are complete in `frontend/messages/it.json`. Check that `auth.signin.*` keys exist and all values are in Italian. Verify `auth.errors.invalidCredentials` and `auth.errors.accountLocked` are in Italian. Confirm the existing `auth.login` string ("Accedi") was NOT overwritten by the object.

- [x] T027 [US4] Verify error messages from backend are translated on the frontend. The backend now returns structured errors with a `code` field (`invalid_credentials` or `account_locked`). In `LoginForm.tsx`, map by `err.code` (from `AuthApiError`): if `code === 'invalid_credentials'`, display `t('errors.invalidCredentials')`; if `code === 'account_locked'`, display `t('errors.accountLocked')`. Fallback: if no code is present, display `err.message` directly. Do NOT match on the English message string.

**Checkpoint**: All sign-in text is properly localized in EN, ES, and IT. Error messages, labels, and lockout messages display in the selected language.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final validation, testing, and quality checks across all user stories.

- [x] T028 [P] Run all backend feature tests: `cd backend && php artisan test tests/Feature/Auth/LoginTest.php tests/Feature/Auth/LogoutTest.php`. All tests must pass.
- [x] T029 [P] Run frontend TypeScript compilation: `cd frontend && npx tsc --noEmit`. Must complete with zero errors.
- [x] T030 [P] Run frontend lint: `cd frontend && npm run lint`. Must pass with no errors.
- [ ] T031 [P] Verify `backend/routes/api/public.php` has correct route ordering: `register`, `login`, `logout` inside the auth group with `throttle:auth` middleware. Confirm `logout` has additional `auth:sanctum` middleware.
- [ ] T032 Update `frontend/src/components/auth/AuthGuard.tsx` to redirect authenticated users away from the login page to the locale-prefixed homepage. In the `useEffect`, change the `requireAuth={false}` branch from `router.push(`/${locale}/account/profile`)` to `router.push(`/${locale}/`)`. The `/account/profile` page does not exist yet and will create a broken link. Do NOT change the `requireAuth={true}` redirect behavior.
- [x] T033 Perform manual quickstart validation per `specs/004-traveler-signin/quickstart.md`. Execute ALL of the following scenarios and mark each complete:
  1. [x] Happy path sign-in (valid credentials, redirect to homepage).
  2. [x] Sign-in with `returnUrl` query parameter (e.g., `?returnUrl=/en/tours`) — verify redirect to that path.
  3. [x] Sign-in with invalid `returnUrl` (external URL like `?returnUrl=https://evil.com`) — verify redirect to homepage, not external site.
  4. [x] Brute-force lockout: 5 wrong passwords, 6th blocked with 423, wait for expiry, sign in successfully.
  5. [x] Sign-out: token revoked, authenticated page returns 401.
  6. [x] Multi-session: sign in on two incognito windows, logout from one, verify the other still works.
  7. [x] Already-authenticated redirect: sign in, then visit `/en/auth/login` — verify redirect away.
  8. [x] Session expiry: sign in, delete token in DB, refresh page — verify redirect to login with "session expired" message.
  9. [x] Localization: test sign-in flow in `/es/auth/login` and `/it/auth/login` including triggering validation errors.
- [x] T034 [P] Add performance assertion to `backend/tests/Feature/Auth/LoginTest.php`. Append test: `it('responds within 3 seconds')`: POST valid login and assert response time is under 3000ms using Pest's `assertLessThan` or Laravel's `assertOk` with a timeout check. If Pest does not support timing assertions natively, wrap the request in `microtime(true)` before/after and assert the delta.
- [x] T035 [P] Implement 401 session expiry recovery in `frontend/src/lib/hooks/useAuth.tsx`. In the `restoreSession` effect, if the `/api/auth/session` call returns 401, OR if any `authApi` call returns 401, catch it and: (1) set `user = null`, `token = null`, (2) redirect to `/${locale}/auth/login` with query param `?sessionExpired=1`, (3) display "session expired" message on the login page. If `useAuth.tsx` already handles this, verify it works correctly.

**Checkpoint**: All automated tests pass, lint passes, manual validation complete. Feature is ready for merge.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — verification only.
- **Foundational (Phase 2)**: No dependencies — everything already exists.
- **User Story 1 (Phase 3)**: Depends on Setup/Foundational verification. Can start immediately.
- **User Story 2 (Phase 4)**: Depends on Setup/Foundational. Can start in parallel with US1 after Setup. Logout does NOT depend on login implementation.
- **User Story 3 (Phase 5)**: Depends on T006 (`AuthenticateTravelerAction`) from US1. Cannot start until US1 backend implementation is complete.
- **User Story 4 (Phase 6)**: Depends on T012 (`LoginForm.tsx`) and T013 (`login/page.tsx`) from US1. Cannot start until US1 frontend is complete.
- **Polish (Phase 7)**: Depends on all user stories being complete.

### Within Each User Story

- Backend: Form Request → Action → Controller → Route (sequential)
- Frontend: Translations [P] → LoginForm → LoginPage (sequential after translations)
- Tests: Written after implementation for this feature (not TDD since tests verify existing patterns)

### Parallel Opportunities

- **Backend vs Frontend**: All backend tasks (T005-T008) can run in parallel with frontend translation tasks (T009-T011).
- **US1 vs US2**: T015-T017 (logout backend) can run in parallel with T005-T008 (login backend).
- **Translations**: T009, T010, T011 (en/es/it translations) can run in parallel.
- **Tests**: T014 (LoginTest) and T019 (LogoutTest) can be written in parallel after their respective implementations.
- **Polish**: T028, T029, T030, T031, T032 can all run in parallel.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup verification.
2. Complete Phase 3: User Story 1 (sign-in with brute-force).
3. **STOP and VALIDATE**: Run LoginTest, manually test sign-in flow.
4. At this point, travelers can sign in. Deploy/demo if ready.

### Incremental Delivery

1. Setup + US1 (sign-in with brute-force) → Test → Deploy.
2. Add US2 (sign-out) → Test → Deploy.
3. Add US3 (brute-force verification/tests) → Test → Deploy.
4. Add US4 (multi-language polish) → Test → Deploy.
5. Each increment adds value without breaking previous stories.

### Parallel Team Strategy

With multiple developers:

1. Developer A: Backend login (T005-T008) + backend logout (T015-T017).
2. Developer B: Frontend login (T009-T013) + frontend translations (T009-T011).
3. Developer C: Tests (T014, T019, T020-T021) + Polish (T028-T033).

---

## Notes

- [P] tasks = different files, no dependencies.
- [Story] label maps task to specific user story for traceability.
- Each user story is independently completable and testable.
- Verify tests fail appropriately before fixing (for new test files).
- Commit after each task or logical group.
- Stop at any checkpoint to validate story independently.
- All new backend files MUST follow the exact patterns from spec 003 (registration): thin controllers, domain actions, Form Requests, Pest tests.
- All new frontend files MUST follow the exact patterns from spec 003: react-hook-form + zodResolver, next-intl translations, Tailwind CSS, AuthGuard.
- No new database migrations, events, or listeners are needed — reuse existing infrastructure from 002/003.
- **Terminology**: Use "sign in" / "sign out" for user-facing copy (buttons, labels, page titles). Use "login" / "logout" for technical identifiers (API endpoints, filenames, schema names, Zod schemas, function names). Example: the page title is "Sign In" but the endpoint is `/login` and the file is `LoginController.php`.
