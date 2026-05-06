# Research: Brute-Force Protection

## Decision 1: Lockout State Storage

**Decision**: Store lockout state in `users` table (`failed_login_count`, `locked_until`), not in cache (Redis).

**Rationale**: FR-011 explicitly requires this. Cache flushes, Redis restarts, or deployment clears must not bypass lockout. The `users` table is the authoritative source. `SELECT ... FOR UPDATE` provides concurrency safety.

**Alternatives considered**:
- **Redis-only**: Rejected — cache is ephemeral by design. A Redis restart would unlock all accounts.
- **Dual-write (DB + cache)**: Rejected — adds complexity without benefit. Cache-aside read could serve stale data.

## Decision 2: Lockout Tier Calculation

**Decision**: Calculate tier dynamically by counting `account_lockout` entries in `auth_audit_logs` since the most recent `login_success`.

**Rationale**: This avoids a dedicated `lockout_tier` column, keeping schema changes minimal. It also makes the tier auditable — the counting logic can be verified by inspecting log entries. The query is scoped to a single user's audit log, which has a compound index on `(user_id, event_type, created_at)`, making it fast even with large log tables.

**Alternatives considered**:
- **Stored `lockout_tier` column**: Rejected — requires schema migration and dual-state (column + logs) that could get out of sync.
- **Counter-only based on `failed_login_count / 5`**: Rejected — fails after a successful sign-in resets the counter but not the tier escalation history.

## Decision 3: Race Condition Handling

**Decision**: Use `SELECT ... FOR UPDATE` (row-level lock) within a database transaction.

**Rationale**: The edge cases doc specifies that simultaneous requests may double-increment but that this is acceptable. The row lock ensures that increments are serialized, minimizing race window. Lockout trigger uses `count % 5 == 0`, so a double-increment (4→6) still triggers lockout at count=10.

**Alternatives considered**:
- **Atomic `UPDATE ... SET failed_login_count = failed_login_count + 1`**: Rejected — can't combine with lockout logic that reads `failed_login_count` to decide tier.
- **Optimistic locking with version column**: Rejected — would require retry logic in the client, adding latency for the common case.

## Decision 4: Email Notification on Lockout

**Decision**: Create a `SendAccountLockedOutEmail` listener attached to the `AccountLockedOut` event, dispatching a queued `AccountLockedOutNotification` mail class.

**Rationale**: Follows existing project pattern — events → listeners → queued jobs. The `AccountLockedOut` event already fires on every lockout trigger. Adding a second listener is the minimal change. The mail is queued to avoid blocking the login response. While duplicate sends from retried jobs would not be harmful (user seeing two lockout emails is benign), a timestamp-based dedup check (`last_lockout_email_sent_at` vs current `locked_until`) was implemented to satisfy the constitution's retry-safety mandate (§274-276: "All queued jobs MUST be retry-safe"). This is a belt-and-suspenders approach — not strictly necessary for correctness, but required for constitution compliance.

**Alternatives considered**:
- **Inline mail in `AuthenticateTravelerAction`**: Rejected — violates thin action principle and blocks login response on SMTP latency.
- **Notification directly in `LogAuthEvent` listener**: Rejected — mixes concerns (audit logging vs user notification).

## Decision 5: `rejected_due_to_lockout` Flag

**Decision**: Add a `public bool $rejectedDueToLockout = false` property to the `LoginFailed` event class. Set it to `true` when dispatching from the lockout check path. The `LogAuthEvent` listener reads this property and includes `rejected_due_to_lockout: true` in the audit log metadata.

**Rationale**: FR-009 requires a distinct audit trail for lockout-rejected attempts versus credential-failure attempts. Adding it as an event property keeps the data explicit and typed, rather than relying on string matching in metadata. The camelCase name follows PHP 8.1+ promoted property convention for consistency.

**Alternatives considered**:
- **Separate event class (e.g., `LoginRejectedDueToLockout`)**: Rejected — over-engineering. The `LoginFailed` event captures the same semantic domain.
- **Metadata-only approach**: Rejected — less discoverable; consumers would need to inspect metadata arrays rather than typed properties.

## Decision 6: No Time-Based Counter Decay

**Decision**: `failed_login_count` resets ONLY on successful sign-in. No time-based decay, no session-boundary reset.

**Rationale**: Per spec clarification: "The counter resets to 0 exclusively after a successful login_success event." Time-based decay would allow slow-burn attacks (e.g., fail 4 times every hour for weeks). The counter persists indefinitely until the legitimate user signs in successfully.

**Alternatives considered**: Time-decay windows (e.g., reset after 24h). Rejected per explicit spec ruling.
