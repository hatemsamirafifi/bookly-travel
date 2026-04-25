# Quickstart: Traveler Sign-In and Sign-Out

**Feature**: 004-traveler-signin | **Date**: 2026-04-25

## Prerequisites

- Backend dependencies installed (`composer install` in `backend/`)
- Frontend dependencies installed (`npm install` in `frontend/`)
- Database migrated and seeded (`php artisan migrate --seed` in `backend/`)
- Redis running (for queue and cache)
- A registered traveler account exists (or create one via `POST /api/public/auth/register`)

## Running the Application

### 1. Start the Backend

```powershell
cd backend
php artisan serve
```

API will be available at `http://localhost:8000/api/public/auth/*`

### 2. Start the Frontend

```powershell
cd frontend
npm run dev
```

Frontend will be available at `http://localhost:3000`

### 3. Start the Queue Worker (for async jobs)

```powershell
cd backend
php artisan queue:work --queue=default
```

## Manual Testing Steps

### Sign In (Happy Path)

1. Navigate to `http://localhost:3000/en/auth/login`
2. Enter credentials for an existing account:
   - Email: `jane@example.com`
   - Password: `MyPassword1`
3. Click **Sign In**
4. **Verify**: You are redirected to the homepage (`/en/`) or to the `returnUrl` if one was provided
5. **Verify**: The navbar shows your name and a **Sign Out** button
6. **Verify**: `last_login_at` is updated in the database

### Sign In with Return URL

1. While logged out, visit a protected page (e.g., `/en/account/bookings`)
2. You are redirected to `/en/auth/login?returnUrl=/en/account/bookings`
3. Sign in with valid credentials
4. **Verify**: You are redirected to `/en/account/bookings`, not the homepage

### Brute-Force Lockout

1. Navigate to `http://localhost:3000/en/auth/login`
2. Enter a valid email but wrong password
3. Submit the form 5 consecutive times
4. On the 6th attempt (even with the correct password), **Verify**:
   - Error message: "Too many failed attempts. Please try again later."
   - HTTP status: 423
   - `failed_login_count` = 5 in the database
   - `locked_until` is set to approximately 1 minute in the future
5. Wait for 1 minute
6. Sign in with the correct password
7. **Verify**: Sign-in succeeds, `failed_login_count` resets to 0, `locked_until` is null

### Second Lockout Tier (5 minutes)

1. Repeat the brute-force test above
2. **Verify**: The 2nd lockout duration is 5 minutes (check `locked_until`)

### Third Lockout Tier (30 minutes)

1. Repeat the brute-force test a 3rd time
2. **Verify**: The 3rd (and all subsequent) lockout durations are 30 minutes

### Sign Out

1. While signed in, click the **Sign Out** button in the navbar
2. **Verify**: You are redirected to the homepage (`/en/`)
3. **Verify**: The navbar shows **Sign In** and **Register** links
4. **Verify**: The token is deleted from `personal_access_tokens`

### Multi-Session Independence

1. Sign in on Device A (e.g., Chrome desktop)
2. Sign in on Device B (e.g., Chrome mobile or incognito)
3. **Verify**: Both sessions are active
4. Sign out on Device A
5. **Verify**: Device A is signed out
6. **Verify**: Device B remains signed in (can still access authenticated pages)

### Email Enumeration Prevention

1. Try to sign in with an email that does not exist: `nonexistent@example.com`
2. **Verify**: Error message is "Invalid email or password."
3. Try to sign in with a valid email but wrong password
4. **Verify**: Error message is **identical**: "Invalid email or password."
5. Try to sign in while locked out
6. **Verify**: Error message is still generic (no indication that the account exists)

### Client-Side Validation

1. Navigate to `http://localhost:3000/en/auth/login`
2. Leave the email field empty and click **Sign In**
3. **Verify**: The form does not submit; the email field is highlighted with an error
4. Enter an invalid email (e.g., `not-an-email`) and click **Sign In**
5. **Verify**: Error "Invalid email or password" appears on the field
6. Leave the password field empty and click **Sign In**
7. **Verify**: The password field is highlighted with an error

### Already Authenticated Redirect

1. Sign in as a traveler
2. Manually navigate to `http://localhost:3000/en/auth/login`
3. **Verify**: You are redirected away (to `/en/account/profile` or `/en/`)

### Session Expiry (Backend Token Revoked)

1. Sign in on the frontend
2. In the database, manually delete the token from `personal_access_tokens`
3. Refresh the page or navigate to an authenticated page
4. **Verify**: The frontend detects the 401, clears the auth state, and redirects to `/en/auth/login` with a "session expired" message

### Localization

1. Navigate to `http://localhost:3000/es/auth/login`
2. **Verify**: All labels, placeholders, button text, and error messages are in Spanish
3. Repeat for `http://localhost:3000/it/auth/login` (Italian)

## Running Automated Tests

### Backend Feature Tests

```powershell
cd backend
php artisan test tests/Feature/Auth/LoginTest.php
php artisan test tests/Feature/Auth/LogoutTest.php
```

### Frontend Tests

```powershell
cd frontend
npm run test
```

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| "Invalid email or password" even with correct credentials | Account is locked | Check `locked_until` in DB; wait or manually clear |
| 429 Too Many Requests | IP rate limit triggered | Wait 1 minute or restart `php artisan serve` |
| 401 after sign-in | Token not stored correctly | Check `localStorage` or cookie storage in browser dev tools |
| Login form shows English only | Missing translations | Ensure `messages/es.json` and `messages/it.json` have `auth.signin.*` keys |
| Queue jobs not running | Worker not started | Run `php artisan queue:work` |
| Audit logs not written | Listener not registered | Check `EventServiceProvider` maps events to `LogAuthEvent` |
