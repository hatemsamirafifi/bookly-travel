# Data Model: Brute-Force Protection

## Entities

### User (Traveler Account)

**Table**: `users`
**Purpose**: Stores lockout state directly on the user record.

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `failed_login_count` | `integer` | `NOT NULL`, `DEFAULT 0` | Consecutive failed sign-in count since last success |
| `locked_until` | `timestamp` | `NULLABLE` | Lockout expiration; when past, lockout is inactive |
| `last_lockout_email_sent_at` | `timestamp` | `NULLABLE` | Tracks the `locked_until` value for which a notification was last sent; used for retry-safe idempotency |

**State transitions**:

```text
failed_login_count = 0 ──(wrong password)──> failed_login_count += 1
failed_login_count = N ──(wrong password)──> failed_login_count = N + 1
failed_login_count = N ──(N % 5 == 0)──────> locked_until = now() + tier_duration + AccountLockedOut event
failed_login_count = * ──(correct password)─> failed_login_count = 0, locked_until = null, TravelerLoggedIn event
locked_until is future ──(any attempt)──────> 423 Locked (no count change, LoginFailed event with rejected_due_to_lockout)
```

**Locked check pseudo-logic**:
```text
if locked_until && locked_until->isFuture():
    reject with 423 (before credential verification)
elif Hash::check(password, user.password):
    reset counters, grant token, 200
else:
    increment failed_login_count
    if failed_login_count % 5 == 0:
        determine tier, set locked_until, fire AccountLockedOut
    fire LoginFailed, 422
```

**Escalating lockout tiers** (determined by counting `account_lockout` audit log entries since last `login_success`):

| Prior lockouts since last success | Lockout duration |
|-----------------------------------|------------------|
| 0 (1st lockout) | 1 minute |
| 1 (2nd lockout) | 5 minutes |
| 2+ (3rd+ lockout) | 30 minutes |

---

### AuthAuditLog

**Table**: `auth_audit_logs`
**Purpose**: Append-only event log for all auth actions.

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | `bigint` | PK, auto-increment | |
| `user_id` | `bigint` | FK → `users.id`, `NULLABLE`, `ON DELETE SET NULL` | Target user (null if non-existent email) |
| `event_type` | `varchar(50)` | `NOT NULL` | Event category |
| `ip_address` | `varchar(45)` | `NULLABLE` | Request IP |
| `user_agent` | `varchar(500)` | `NULLABLE` | Browser user agent |
| `metadata` | `jsonb` | `NULLABLE` | Event-specific data |
| `created_at` | `timestamp` | `NOT NULL` | When the event occurred (no `updated_at`) |

**Event types used by this feature**:

| event_type | Triggered by event class | When |
|------------|--------------------------|------|
| `login_success` | `TravelerLoggedIn` | Successful sign-in (tier-reset anchor) |
| `login_failed` | `LoginFailed` | Failed credential check OR lockout-rejected attempt |
| `account_lockout` | `AccountLockedOut` | Lockout triggered (counted for tier calculation) |

**Metadata for `login_failed` events**:

> `rejected_due_to_lockout` is ONLY present for lockout-rejected attempts.

```json
{
  "email": "<hmac-sha256 hash>",
  "rejected_due_to_lockout": true
}
```

**Tier calculation query** (in `AuthenticateTravelerAction`):
```sql
SELECT COUNT(*) FROM auth_audit_logs
WHERE user_id = ?
  AND event_type = 'account_lockout'
  [AND created_at > (<most_recent_login_success.created_at>)]
```

If no `login_success` record exists, count ALL `account_lockout` entries for that user.
If no `account_lockout` entries exist, default tier → 1 minute.

---

## Events

### LoginFailed

**Namespace**: `App\Domains\Auth\Events`

| Property | Type | Purpose |
|----------|------|---------|
| `email` | `string` | The email attempted (always populated) |
| `user` | `?User` | The user if found, null for non-existent emails |
| `rejectedDueToLockout` | `bool` | `true` when attempt was rejected due to active lockout |

### AccountLockedOut

**Namespace**: `App\Domains\Auth\Events`

| Property | Type | Purpose |
|----------|------|---------|
| `user` | `User` | The locked-out user account |

---

## Relationships

```text
User ──(hasMany)──> AuthAuditLog  (via user_id FK)
```

No new tables or relationships needed. This feature extends existing schema.
