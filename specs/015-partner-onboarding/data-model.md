# Data Model: Partner Onboarding

**Feature**: 015-partner-onboarding
**Date**: 2026-08-18

## 1. Existing Entities (No Schema Changes Required)

### 1.1 Partner (existing)

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| id | bigint | PK, auto-increment | |
| user_id | bigint | FK → users.id, UNIQUE | One-to-one with User |
| role | varchar(20) | default 'partner' | Always 'partner' for Phase 1 |
| onboarding_status | varchar(20) | default 'pending' | Enum: pending, approved, rejected, suspended. Legacy 'incomplete' normalized to pending at runtime |
| is_active | boolean | default true | Mirrors lifecycle: true when approved, false otherwise |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Lifecycle transitions** (via `Partner::canTransitionTo()`):
```
pending  → approved | rejected
approved → suspended
suspended → approved
rejected → pending
```

**New behavior**: Add `invited_by_admin` boolean column (default false). When true, completing registration via invitation link sets this flag.

**Lifecycle timestamps**: Partner lifecycle timestamps (approval, rejection, suspension, reinstatement) are NOT stored as dedicated columns on the `partners` table. They are derived from the immutable `GovernanceAuditLog` by `PartnerOnboardingService::getLifecycleTimestamp()` (T008), which queries the latest `partner.{action}` entry targeting this partner. This is intentional — the audit log is the authoritative lifecycle history, and duplicating timestamps would risk drift between the column and the audit trail.

### 1.2 PartnerProfile (existing)

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| id | bigint | PK, auto-increment | |
| partner_id | bigint | FK → partners.id, UNIQUE, cascade delete | |
| company_name | varchar(255) | NOT NULL | |
| business_description | text | nullable | |
| logo_url | varchar(255) | nullable | |
| contact_email | varchar(255) | NOT NULL | |
| contact_phone | varchar(50) | nullable | |
| website | varchar(255) | nullable | |
| business_address | jsonb | nullable | {street, city, state, postal_code, country} |
| tax_id | varchar(50) | nullable | |
| payout_holder_name | varchar(255) | nullable | Deferred (Phase 1 scope) |
| payout_bank_name | varchar(255) | nullable | Deferred |
| payout_account_number | varchar(255) | nullable | Encrypted at rest via Attribute accessor |
| payout_iban | varchar(255) | nullable | Encrypted at rest |
| payout_swift_bic | varchar(255) | nullable | Encrypted at rest |
| payout_country | varchar(2) | nullable | |
| rejection_reason | text | nullable | **NEW COLUMN** — admin rejection feedback shown to rejected partners |

**Change**: Add `rejection_reason` column to `partner_profiles` table. Currently `RejectPartnerAction` writes rejection reason to `profile.rejection_reason` but the column doesn't exist in the migration yet — it needs to be added.

**Out of scope**: Social media links (Facebook, Instagram, X, etc.) are NOT part of Spec 015 / Phase 1. The `website` column captures a single website URL only. No `social_links` column is introduced.

### 1.3 PartnerSettings (existing)

No changes needed. Notification preferences already exist for booking, cancellation, daily summary, review, and tour status changes. Partner onboarding lifecycle notifications will use the existing Notification model.

### 1.4 GovernanceAuditLog (existing)

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| id | bigint | PK, auto-increment | |
| actor_type | varchar(20) | indexed | Morph map alias (e.g., 'admin') |
| actor_id | bigint | nullable, indexed | FK via morph |
| action | varchar(40) | indexed | e.g., 'partner.approve', 'partner.reject' |
| target_type | varchar(60) | nullable | Morph map alias (e.g., 'partner') |
| target_id | bigint | nullable | FK via morph |
| before_state | jsonb | nullable | State snapshot before action |
| after_state | jsonb | nullable | State snapshot after action |
| metadata | jsonb | nullable | Extra context (rejection reason, etc.) |
| created_at | timestamp | nullable | No updated_at — append-only |

**New action types**: `partner.resubmit`, `partner.invite` (in addition to existing `partner.approve`, `partner.reject`, `partner.suspend`, `partner.reinstate`).

## 2. New Entities

### 2.1 PartnerInvitation (new)

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| id | bigint | PK, auto-increment | |
| email | varchar(255) | NOT NULL, indexed | Recipient email address |
| company_name | varchar(255) | NOT NULL | Pre-filled company name for invitation |
| contact_person | varchar(255) | nullable | Pre-filled contact person name |
| invited_by_admin_id | bigint | FK → users.id, NOT NULL | Admin who created the invitation |
| token | varchar(64) | UNIQUE, NOT NULL, indexed | Cryptographically secure invitation token |
| status | varchar(20) | default 'pending', indexed | Enum: pending, consumed, expired |
| expires_at | timestamp | NOT NULL | Invitation expiration (default 7 days from creation) |
| consumed_at | timestamp | nullable | When the invitation was used |
| partner_id | bigint | FK → partners.id, nullable | Set when invitation is consumed (links to created partner) |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**:
- `partner_invitations_token_unique` on `token`
- `partner_invitations_email_status_idx` on `(email, status)`
- `partner_invitations_expires_at_idx` on `expires_at`

**Invariants**:
- Token is generated with `Str::random(64)` (cryptographically secure)
- `expires_at` defaults to `now() + 7 days`
- `status` transitions: `pending → consumed` (on registration completion) or `pending → expired` (via scheduled cleanup)
- `email` must not already be a registered user (validated at invitation creation time)
- `partner_id` is set only when `status = 'consumed'`
- Duplicate invitations for the same email with `status = 'pending'` are rejected (one active invitation per email)

### 2.2 User Model Changes

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| (no schema changes) | | | The existing `users` table has `role` column; partner users get `role = 'partner'` |

**No new columns on users.** The `role` column already supports 'partner', and the `partner` relationship already exists on the User model.

### 2.3 Partners Table Changes

| Column | Type | Constraints | Notes |
|--------|------|------------|-------|
| invited_by_admin | boolean | default false | **NEW COLUMN** — true when partner account was created via admin invitation flow |

## 3. Entity Relationships

```
User 1 ──── 1 Partner
Partner 1 ──── 1 PartnerProfile
Partner 1 ──── 1 PartnerSettings
Partner 1 ──── * Tour
Partner 1 ──── * Notification
PartnerInvitation * ──── 1 User (invited_by_admin_id)
PartnerInvitation 0..1 ──── 1 Partner (partner_id, set on consumption)
```

## 4. State Machines

### 4.1 Partner Onboarding Status

```
                    ┌──────────┐
          ┌────────▶│ pending  │◀────────┐
          │         └────┬─────┘         │
          │              │               │
     resubmit       approve│reject       │
     (by partner)      │    │           │
          │            ▼    ▼           │
          │      ┌────────┐ ┌─────────┐ │
          │      │approved│ │rejected │─┘
          │      └───┬────┘ └─────────┘
          │          │
          │     suspend│
          │          │
          │          ▼
          │    ┌──────────┐
          └────│suspended │───reinstate──▶ approved
               └──────────┘
```

**Auto-approval path for invitations**: 
```
Admin creates invitation → Partner clicks link → Completes profile → Partner created with status='approved'
```

### 4.2 PartnerInvitation Status

```
pending ──▶ consumed  (partner completes registration via invitation link)
pending ──▶ expired   (token past expires_at, or manually revoked)
```

## 5. Validation Rules

### 5.1 Partner Registration (self-service)

| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| email | required, email, max:255, unique:users,email |
| password | required, string, min:8, confirmed |
| company_name | required, string, max:255 |
| contact_email | required, email, max:255 |
| contact_phone | required, string, max:50 |
| business_description | required, string, max:1000 |
| business_address.street | required, string, max:255 |
| business_address.city | required, string, max:255 |
| business_address.state | nullable, string, max:255 |
| business_address.postal_code | required, string, max:20 |
| business_address.country | required, string, size:2 (ISO 3166-1 alpha-2) |
| tax_id | nullable, string, max:50 |
| payout_country | required, string, size:2 |

### 5.2 Partner Resubmission

| Field | Rules |
|-------|-------|
| company_name | required, string, max:255 |
| contact_email | required, email, max:255 |
| contact_phone | required, string, max:50 |
| business_description | required, string, max:1000 |
| business_address | required, array |
| business_address.street | required, string, max:255 |
| business_address.city | required, string, max:255 |
| business_address.state | nullable, string, max:255 |
| business_address.postal_code | required, string, max:20 |
| business_address.country | required, string, size:2 |

**Only allowed when `onboarding_status = 'rejected'`** — enforced at middleware + action level.

### 5.3 Invitation Completion

| Field | Rules |
|-------|-------|
| token | required, string, exists:partner_invitations,token + not expired + not consumed |
| name | required, string, max:255 |
| password | required, string, min:8, confirmed |
| company_name | sometimes, string, max:255 (pre-filled, overridable) |
| contact_phone | sometimes, string, max:50 |
| business_description | sometimes, string, max:1000 |
| business_address | sometimes, array |
| business_address.country | sometimes, string, size:2 |

### 5.4 Admin Invitation Creation (Filament form)

| Field | Rules |
|-------|-------|
| email | required, email, not exists:users,email |
| company_name | required, string, max:255 |
| contact_person | nullable, string, max:255 |

## 6. Data Integrity Constraints

1. **Unique user email**: `users.email` unique constraint prevents duplicate registrations (FR-002, Edge Case: Concurrent Registration)
2. **Unique partner per user**: `partners.user_id` unique constraint enforces single-account model (FR-012)
3. **Unique invitation per email**: One active `pending` invitation per email address; duplicates rejected
4. **Token uniqueness**: `partner_invitations.token` unique constraint prevents token collision
5. **Lifecycle guards**: `Partner::canTransitionTo()` enforces valid state transitions at model level
6. **Audit immutability**: `GovernanceAuditLog` model blocks update/delete at Eloquent level (`updating` and `deleting` events return false)
7. **Ownership boundaries**: PartnerRoleMiddleware + ownership checks in controllers enforce FR-010
8. **Existing-token rejection after suspension**: PartnerRoleMiddleware checks `is_active` and `onboarding_status` on every request, so an already-issued Sanctum Bearer token is rejected on the next request after suspension — no physical token deletion required (spec Edge Case: Deleted/Inactive Users)
9. **Rejection/suspension reason output escaping**: Rejection and suspension reasons are stored verbatim in `partner_profiles.rejection_reason` and audit metadata for audit/history integrity; XSS prevention is enforced at render time via escaped Blade syntax `{{ $reason }}` in email views and frontend rendering — never raw `{!! $reason !!}` (spec Edge Case: Rejection Reason Sanitization)
10. **Admin in-app notification on registration**: A `Notification` row is created for each admin with `manage_partners` permission on partner self-registration; this is an in-app operational notification (FR-013), distinct from partner-facing transactional emails (FR-011), and no email is dispatched to admins