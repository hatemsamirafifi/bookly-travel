# Quickstart: Brute-Force Protection

## What This Feature Does

After 5 consecutive failed sign-in attempts for the same account, the account is temporarily locked. Lockout durations escalate: 1 minute → 5 minutes → 30 minutes. A successful sign-in resets everything.

## Files You'll Touch

### Backend (Laravel)

| File | Action | What |
|------|--------|------|
| `app/Domains/Auth/Events/LoginFailed.php` | **MODIFY** | Add `bool $rejectedDueToLockout = false` property |
| `app/Domains/Auth/Actions/AuthenticateTravelerAction.php` | **MODIFY** | Pass `rejectedDueToLockout: true` on lockout reject path |
| `app/Domains/Auth/Listeners/LogAuthEvent.php` | **MODIFY** | Include `rejected_due_to_lockout` in metadata when set |
| `app/Domains/Auth/Listeners/SendAccountLockedOutEmail.php` | **CREATE** | New listener → queues email notification |
| `app/Domains/Auth/Notifications/AccountLockedOutNotification.php` | **CREATE** | Mail notification class |
| `app/Providers/EventServiceProvider.php` | **MODIFY** | Register `SendAccountLockedOutEmail` listener for `AccountLockedOut` |

### Frontend (Next.js)

No changes needed. `LoginForm.tsx` already handles `code === 'account_locked'` with the translated `auth.errors.accountLocked` message. All three locales have the translation key.

### Tests (Pest)

| File | Action |
|------|--------|
| `tests/Feature/Auth/LoginTest.php` | **REVIEW** — 15 existing tests cover core flow; may add email notification assertion |

## How to Verify Locally

```bash
# 1. Run existing backend tests
cd backend
php artisan test tests/Feature/Auth/LoginTest.php

# 2. Trigger a lockout manually (5 wrong passwords)
curl -X POST http://localhost:8000/api/public/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"wrong"}'
# ... repeat 5 times ...

# 3. Verify 423 on 6th attempt (even with correct password)
curl -X POST http://localhost:8000/api/public/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"CorrectPassword123!"}'
# → HTTP 423, {"code":"account_locked","message":"Too many failed attempts..."}

# 4. Verify lockout recorded in audit log
php artisan tinker
> App\Models\AuthAuditLog::where('user_id', $userId)->latest()->first();
```

## Key Implementation Details

- **Lockout is DB-backed**: `SELECT ... FOR UPDATE` prevents race conditions. Cache flushes cannot bypass lockout.
- **Tier from audit logs**: Count `account_lockout` events since last `login_success`. No separate tier column.
- **Counter persists**: Only resets on successful sign-in, not on time decay or session end.
- **Generic errors**: Non-existent email and wrong password both return "Invalid email or password." (422). Locked accounts return "Too many failed attempts." (423) regardless of credential correctness.
