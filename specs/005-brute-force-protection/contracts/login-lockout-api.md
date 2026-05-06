# Login API Contract

> Parent contract: `specs/004-traveler-signin/contracts/login-api.md` (normal flow).
> This document covers the brute-force protection behavior only.

## Endpoint

```text
POST /api/public/auth/login
```

## Lockout Behavior (Feature 005)

### Response: 423 Locked

Returned when the account is currently locked (`locked_until` is in the future).

**Trigger**: Any sign-in attempt for a locked account — regardless of credential validity.

```json
{
  "code": "account_locked",
  "message": "Too many failed attempts. Please try again later."
}
```

**Rules**:
- Same response for correct AND incorrect passwords (prevents credential enumeration through lockout side channel).
- `failed_login_count` is NOT incremented during lockout (rejected pre-credential-check).
- Every rejected attempt still logs `login_failed` in audit log with `rejected_due_to_lockout: true`.

### Lockout Triggers

Lockout activates when `failed_login_count` reaches a multiple of 5 (5, 10, 15, ...):

| Attempt | failed_login_count | Behavior |
|---------|-------------------|----------|
| 1-4 incorrect | 1-4 | 422 `invalid_credentials` |
| 5th incorrect | 5 | 422 + lockout sets `locked_until`, `AccountLockedOut` event |
| 6th (locked) | 5 (unchanged) | 423 `account_locked` |

### Lockout Expiry

When `locked_until` passes:
- Next attempt proceeds to credential verification (no auto-unlock needed).
- If correct password → `failed_login_count = 0`, `locked_until = null`, 200 with token.
- If wrong password → `failed_login_count = 6`, and on next multiple of 5 (at 10), another lockout triggers at the escalated tier.

### Email Notification

On `AccountLockedOut` event:
- A queued job sends an email to the traveler.
- Email includes: account email, timestamp, instructions to wait or use password reset.
- Subject/body in traveler's preferred locale (EN/ES/IT).

## Error Code Table (Brute-Force Specific)

| HTTP Status | `code` | Message | When |
|-------------|--------|---------|------|
| 423 | `account_locked` | "Too many failed attempts. Please try again later." | Account locked |
| 422 | `invalid_credentials` | "Invalid email or password." | Wrong credentials (before/after lockout expiry) |
