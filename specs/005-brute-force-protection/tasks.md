# Tasks: Brute-Force Protection for Traveler Sign-In

**Input**: Design documents from `/specs/005-brute-force-protection/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/

**Status Note**: The core lockout logic (account lockout triggering, escalating tiers, counter reset, race-condition handling) is already implemented in `AuthenticateTravelerAction`. Backend tests cover all success criteria. The tasks below address the 6 remaining gaps identified in the plan.

**Tests**: Existing tests in `backend/tests/Feature/Auth/LoginTest.php` (15 tests) cover SC-001 through SC-007. One new test task is included for audit metadata verification.

**Organization**: Tasks are grouped by user story for traceability. Most implementation tasks are in US1 since the lockout trigger path is where all gaps exist.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- All paths are relative to repository root

---

## Phase 1: Setup

**Purpose**: Confirm prerequisites are in place

- [x] T001 Verify `failed_login_count` and `locked_until` columns exist in `backend/database/migrations/0001_01_01_000000_create_users_table.php`
- [x] T002 [P] Verify `auth_audit_logs` table exists with `event_type`, `user_id`, `metadata` columns in `backend/database/migrations/0001_01_01_000003_create_auth_audit_logs_table.php`
- [x] T003 [P] Verify `AccountLockedOut` and `LoginFailed` events exist in `backend/app/Domains/Auth/Events/`
- [ ] T004 Run existing auth test suite: `cd backend && php artisan test tests/Feature/Auth/LoginTest.php` — confirm all 15 tests pass

**Checkpoint**: All prerequisites confirmed. Proceed to user story tasks.

---

## Phase 2: User Story 1 — Account Lockout After Consecutive Failures (Priority: P1)

**Goal**: Complete the `rejected_due_to_lockout` audit trail so every lockout-rejected sign-in attempt is distinguishable from a credential-failure in audit logs (FR-009).

**Independent Test**: Submit a sign-in attempt against a locked account, query `auth_audit_logs` for the resulting `login_failed` entry, and confirm `metadata->rejected_due_to_lockout` is `true`. Submit a wrong-password attempt (not locked) and confirm the flag is absent.

### Implementation for User Story 1

- [x] T005 [P] [US1] Add `public bool $rejectedDueToLockout = false` property to `LoginFailed` event in `backend/app/Domains/Auth/Events/LoginFailed.php`
- [x] T006 [US1] Pass `rejectedDueToLockout: true` when dispatching `LoginFailed` from the lockout-reject path (line ~42) in `backend/app/Domains/Auth/Actions/AuthenticateTravelerAction.php`
- [x] T007 [US1] Include `rejected_due_to_lockout` flag in audit log metadata when event property is true in `backend/app/Domains/Auth/Listeners/LogAuthEvent.php`
- [x] T008 [US1] Update `LoginTest.php` test "returns structured error codes" to assert `rejected_due_to_lockout` metadata on the `login_failed` audit entry in `backend/tests/Feature/Auth/LoginTest.php`

**Checkpoint**: Lockout-rejected attempts are now auditable with the `rejected_due_to_lockout` flag. Run `php artisan test tests/Feature/Auth/LoginTest.php` — all tests should pass.

---

## Phase 3: User Story 1 (continued) — Email Notification on Lockout (Priority: P1)

**Goal**: Send a queued email notification to the traveler when their account is locked out (FR-013).

**Independent Test**: Trigger account lockout, assert a queued mail job was dispatched for the locked-out user's email. The email subject matches the lockout notification template.

### Implementation for Email Notification

- [x] T009 [P] [US1] Create `AccountLockedOutMail` mailable class following `VerificationMail` pattern in `backend/app/Mail/AccountLockedOutMail.php`
- [x] T010 [P] [US1] Create `SendAccountLockedOutEmail` listener implementing `ShouldQueue` in `backend/app/Domains/Auth/Listeners/SendAccountLockedOutEmail.php`
- [x] T011 [US1] Register `SendAccountLockedOutEmail::class` as a listener for `AccountLockedOut::class` in `backend/app/Providers/EventServiceProvider.php`
- [x] T012 [US1] Add tests asserting email queued on lockout + idempotency (no duplicate email for same `locked_until` timestamp) in `backend/tests/Feature/Auth/LoginTest.php`

**Checkpoint**: After T012, triggering lockout dispatches a queued email notification. The existing lockout flow tests remain green.

---

## Phase 4: Polish & Verification

**Purpose**: Final validation of all success criteria

- [x] T013 [P] Verify translation completeness: confirm `auth.errors.accountLocked` exists in `frontend/messages/en.json`, `frontend/messages/es.json`, and `frontend/messages/it.json`
- [x] T014 [P] Verify `LoginForm.tsx` handles `code === 'account_locked'` in `frontend/src/components/auth/LoginForm.tsx` and `AuthApiError` code field in `frontend/src/lib/api/auth.ts`
- [x] T014.1 [P] Verify cache-survivability test exists in `backend/tests/Feature/Auth/LoginTest.php` — confirmed: "survives redis cache flush during lockout" at line 287 validates lockout enforcement after `Cache::flush()` (SC-005 traceability)
- [ ] T015 Run full backend auth test suite: `cd backend && php artisan test tests/Feature/Auth/` (user to execute locally)
- [ ] T016 Run quickstart validation per `specs/005-brute-force-protection/quickstart.md` (user to execute locally)

**Checkpoint**: All SC-001 through SC-007 verified. Feature is complete.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (US1 — Audit Flag)**: Depends on Phase 1 (confirmation that event files exist)
- **Phase 3 (US1 — Email Notification)**: Technically independent of Phase 2 (different files), but logically depends on lockout infrastructure being confirmed in Phase 1
- **Phase 4 (Polish)**: Depends on Phase 2 and Phase 3 completion

### User Story Dependencies

All three user stories (lockout, escalation, reset) share the same code path in `AuthenticateTravelerAction`. Their core implementation is complete. The remaining tasks are:
- US1: `rejected_due_to_lockout` flag + email notification (Phases 2-3)
- US2: No remaining tasks (escalation tiers already implemented and tested)
- US3: No remaining tasks (counter reset already implemented and tested)

### Within Each Phase

- T001-T003 can run in parallel (verification only)
- T004 runs after T001-T003 (requires all prerequisites)
- T005 and T009-T010 are independent (different new files)
- T006 depends on T005 (uses the new property)
- T007 depends on T005 (reads the new property)
- T008 depends on T006-T007 (tests the combined change)
- T011 depends on T010 (registers the listener)
- T012 depends on T009-T011 (tests the email pipeline)
- T013-T014 are independent verification tasks (parallel)

### Parallel Opportunities

```bash
# Phase 1: Launch all verification tasks in parallel
Task: "T001 Verify columns in users table migration"
Task: "T002 Verify auth_audit_logs table migration"
Task: "T003 Verify event classes exist"

# Phase 2: Launch independent new file creation (T005) in parallel with Phase 3 setup (T009-T010)
# These touch completely different files
Task: "T005 Add rejectedDueToLockout property to LoginFailed event"
Task: "T009 Create AccountLockedOutMail mail class"
Task: "T010 Create SendAccountLockedOutEmail listener"

# Phase 4: Launch verification tasks in parallel
Task: "T013 Verify translation completeness across 3 locale files"
Task: "T014 Verify frontend lockout handling in LoginForm.tsx"
```

---

## Implementation Strategy

### Execution Order (Single Developer)

1. **T001-T004** (Phase 1): Verify prerequisites exist (~5 minutes)
2. **T005-T008** (Phase 2): Add `rejected_due_to_lockout` audit flag (~15 minutes)
3. **T009-T012** (Phase 3): Wire email notification on lockout (~20 minutes)
4. **T013-T016** (Phase 4): Final verification (~10 minutes)

**Estimated total**: ~50 minutes (all changes are small, focused modifications)

### MVP Scope

The MVP (User Story 1 minimum) is already implemented and passing. The remaining tasks are completions of audit and notification requirements that were deferred during initial implementation.

### What's Already Done (No Tasks Needed)

- Core lockout logic: `AuthenticateTravelerAction::execute()` (lines 36-112)
- Escalating tiers: 1/5/30 minute dynamic calculation in lockout trigger path
- Counter reset: Only on successful sign-in (line 95-96)
- Race condition safety: `SELECT ... FOR UPDATE` (line 38)
- Frontend lockout message: `LoginForm.tsx` lines 64-65
- All three locale translations: `en.json`, `es.json`, `it.json`
- 15 Pest feature tests covering lockout, escalation, reset, race conditions, cache survivability, and rate limiting

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Total files touched across the entire feature (including pre-implemented work): 12 backend files (8 existing modified, 4 new created). Remaining tasks touch only 4-5 files (see plan.md § Structure Decision).
- Frontend requires zero changes — already complete
- Commit after each phase checkpoint
