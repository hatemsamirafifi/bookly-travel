# API Contracts: Partner Onboarding

**Feature**: 015-partner-onboarding
**Date**: 2026-08-18
**Constitution**: API-First (partner + public surfaces), Internal Admin Exception (Filament for admin)

---

## 1. Public Endpoints (Unauthenticated)

### 1.1 Partner Self-Registration

**`POST /api/public/auth/partners/register`**

Existing endpoint (PartnerRegistrationController). No contract changes required — the existing implementation creates a User + Partner + PartnerProfile with `onboarding_status = 'pending'` and returns auth token.

**Request Body:**
```json
{
  "name": "Maria Garcia",
  "email": "maria@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "company_name": "Garcia Tours SL",
  "contact_email": "info@garciatours.com",
  "contact_phone": "+34-612-345-678",
  "business_description": "Guided walking tours of Barcelona's Gothic Quarter...",
  "business_address": {
    "street": "Carrer de Ferran, 42",
    "city": "Barcelona",
    "state": "Catalonia",
    "postal_code": "08002",
    "country": "ES"
  },
  "tax_id": "B-12345678",
  "payout_country": "ES"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "user": {
      "id": 42,
      "name": "Maria Garcia",
      "email": "maria@example.com",
      "role": "partner"
    },
    "partner": {
      "id": 17,
      "onboarding_status": "pending",
      "is_active": false
    },
    "token": "1|abc123..."
  },
  "message": "Partner registration submitted successfully. Your account is pending admin approval."
}
```

**Error Responses:**
- `422 Unprocessable Entity`: Validation errors (duplicate email, missing required fields, invalid phone format)
- `409 Conflict`: Email already registered (FR-002 edge case)

### 1.2 Partner Invitation Validation

**`GET /api/public/auth/partners/invite/{token}`**

Validates an invitation token and returns pre-filled details for the registration form.

**Path Parameters:**
- `token` (string, required): The invitation token

**Response (200 OK):**
```json
{
  "data": {
    "email": "invited@operator.com",
    "company_name": "Premier Adventures",
    "contact_person": "John Smith",
    "expires_at": "2026-08-25T12:00:00Z"
  }
}
```

**Error Responses:**
- `404 Not Found`: Token does not exist
- `410 Gone`: Token has expired
- `409 Conflict`: Token has already been consumed

### 1.3 Partner Invitation Completion

**`POST /api/public/auth/partners/invite/{token}/complete`**

Completes registration via admin invitation link. Creates User + Partner with `onboarding_status = 'approved'` (auto-approved per spec clarification).

**Path Parameters:**
- `token` (string, required): The invitation token

**Request Body:**
```json
{
  "name": "John Smith",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "contact_phone": "+1-555-123-4567",
  "business_description": "Adventure tours in the Swiss Alps...",
  "business_address": {
    "street": "Bahnhofstrasse 15",
    "city": "Zürich",
    "state": "ZH",
    "postal_code": "8001",
    "country": "CH"
  },
  "payout_country": "CH"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "user": {
      "id": 43,
      "name": "John Smith",
      "email": "invited@operator.com",
      "role": "partner"
    },
    "partner": {
      "id": 18,
      "onboarding_status": "approved",
      "is_active": true
    },
    "token": "2|def456..."
  },
  "message": "Welcome to Bookly! Your partner account is now active."
}
```

**Error Responses:**
- `422 Unprocessable Entity`: Validation errors
- `404 Not Found`: Token does not exist
- `410 Gone`: Token has expired
- `409 Conflict`: Token already consumed or email already registered

---

## 2. Partner Endpoints (Authenticated)

### 2.1 Get Onboarding Status

**`GET /api/partner/onboarding-status`**

Returns the partner's current onboarding status, rejection reason (if applicable), and invitation details (if applicable).

**Headers:** `Authorization: Bearer {token}`

**Response (200 OK) — Pending:**
```json
{
  "data": {
    "onboarding_status": "pending",
    "can_create_tours": false,
    "rejection_reason": null,
    "submitted_at": "2026-08-18T10:30:00Z",
    "message": "Your application is under review. We'll notify you once a decision is made."
  }
}
```

**Response (200 OK) — Approved:**
```json
{
  "data": {
    "onboarding_status": "approved",
    "can_create_tours": true,
    "rejection_reason": null,
    "submitted_at": "2026-08-18T10:30:00Z",
    "approved_at": "2026-08-19T14:00:00Z",
    "message": null
  }
}
```

**Response (200 OK) — Rejected:**
```json
{
  "data": {
    "onboarding_status": "rejected",
    "can_create_tours": false,
    "rejection_reason": "The business description does not provide sufficient detail about your tour operations. Please update your company profile with more specific information about the tours you offer.",
    "submitted_at": "2026-08-18T10:30:00Z",
    "rejected_at": "2026-08-20T09:15:00Z",
    "message": "Your application was not approved. You can update your profile and resubmit."
  }
}
```

**Response (200 OK) — Suspended:**
```json
{
  "data": {
    "onboarding_status": "suspended",
    "can_create_tours": false,
    "rejection_reason": null,
    "suspension_reason": "Violation of marketplace quality guidelines.",
    "submitted_at": "2026-08-18T10:30:00Z",
    "suspended_at": "2026-09-01T08:00:00Z",
    "message": "Your account has been suspended. Please contact support for more information."
  }
}
```

### 2.2 Resubmit Application

**`POST /api/partner/onboarding/resubmit`**

Updates the partner's profile and transitions `onboarding_status` from `rejected` back to `pending`. Only allowed for rejected partners.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "company_name": "Garcia Tours SL (Updated)",
  "contact_email": "info@garciatours.com",
  "contact_phone": "+34-612-345-678",
  "business_description": "Updated: Expert-led walking tours through Barcelona's Gothic Quarter, covering medieval history, local cuisine, and hidden architectural gems...",
  "business_address": {
    "street": "Carrer de Ferran, 42",
    "city": "Barcelona",
    "state": "Catalonia",
    "postal_code": "08002",
    "country": "ES"
  }
}
```

**Response (200 OK):**
```json
{
  "data": {
    "onboarding_status": "pending",
    "can_create_tours": false,
    "rejection_reason": null,
    "message": "Your application has been resubmitted for review."
  }
}
```

**Error Responses:**
- `403 Forbidden`: Partner is not in `rejected` status (status-gated)
- `422 Unprocessable Entity`: Validation errors

### 2.3 Partner Profile Update (Existing)

**`PUT /api/partner/profile`** — Existing endpoint from Spec 012. No changes needed for this spec, but rejected partners should be able to access it to update their details before resubmission. The PartnerRoleMiddleware must allow rejected partners to view/update their profile while blocking tour creation.

---

## 3. Admin Endpoints (Filament)

Admin partner management is handled via Filament admin panel at `/admin`. The following Filament resource and actions are defined:

### 3.1 PartnerResource (Filament)

**Resource**: `App\Domains\Admin\Filament\PartnerResource`

**Table Columns:**
- id, company_name, contact_email, onboarding_status, is_active, created_at

**Form Fields:**
- Read-only: id, onboarding_status, created_at, rejection_reason
- Editable (when appropriate): company_name, contact_email, contact_phone, business_description, website, business_address, tax_id

**Actions:**

| Action | Button Label | Visibility | Confirmation | Form Fields | Action Class |
|--------|-------------|------------|---------------|-------------|--------------|
| Approve | "Approve" | `onboarding_status = pending` | Yes (modal) | None | ApprovePartnerAction |
| Reject | "Reject" | `onboarding_status = pending` | Yes (modal) | `rejection_reason` (required textarea) | RejectPartnerAction |
| Suspend | "Suspend" | `onboarding_status = approved` | Yes (modal) | `reason` (required textarea) | SuspendPartnerAction |
| Reinstate | "Reinstate" | `onboarding_status = suspended` | Yes (modal) | None | ReinstatePartnerAction |
| Invite | "Invite Partner" | Global (table header) | Yes (modal) | `email` (required, email), `company_name` (required), `contact_person` (optional) | InvitePartnerAction |

### 3.2 Admin Invitation Creation (Filament Action)

When an admin clicks "Invite Partner", the `InvitePartnerAction`:

1. Validates that the email is not already registered as a user
2. Generates a cryptographically secure 64-character token
3. Creates a `PartnerInvitation` record with `status = 'pending'`, `expires_at = now() + 7 days`
4. Queues `PartnerInvitationMail` to the recipient email
5. Logs a `partner.invite` governance audit entry
6. Displays a success notification with a link to copy

The invitation email contains a link to `/auth/partner-invite/{token}` which routes to the Next.js invitation acceptance page.

---

## 4. Scheduled Tasks

### 4.1 Invitation Expiration Cleanup

**Command**: `php artisan partner-invitations:expire`

**Schedule**: Daily at midnight (`schedule:command('partner-invitations:expire')->daily()`)

**Behavior**: Transitions all `partner_invitations` with `status = 'pending'` and `expires_at < now()` to `status = 'expired'`.

---

## 5. Email Contracts

### 5.1 PartnerApplicationReceivedMail

- **Trigger**: After successful self-registration (PartnerRegistrationController)
- **Recipient**: Partner's `contact_email`
- **Subject**: "Your Bookly Partner Application Has Been Received"
- **Content**: Confirmation of application receipt, estimated review time, link to onboarding status page

### 5.2 PartnerApprovedMail (existing)

- **Trigger**: Admin approves partner (ApprovePartnerAction)
- **Recipient**: Partner's `user.email`
- **Content**: Congratulatory onboarding email, link to partner dashboard
- **No changes needed** — already implemented

### 5.3 PartnerRejectedMail (existing)

- **Trigger**: Admin rejects partner (RejectPartnerAction)
- **Recipient**: Partner's `user.email`
- **Content**: Rejection notification with reason, link to update profile and resubmit
- **Enhancement needed**: Add resubmission instructions and link

### 5.4 PartnerSuspendedMail

- **Trigger**: Admin suspends partner (SuspendPartnerAction)
- **Recipient**: Partner's `user.email`
- **Subject**: "Your Bookly Partner Account Has Been Suspended"
- **Content**: Suspension notification with reason, support contact information

### 5.5 PartnerReinstatedMail

- **Trigger**: Admin reinstates partner (ReinstatePartnerAction)
- **Recipient**: Partner's `user.email`
- **Subject**: "Your Bookly Partner Account Has Been Reinstated"
- **Content**: Reinstatement notification, note that tours must be resubmitted, link to partner dashboard

### 5.6 PartnerInvitationMail

- **Trigger**: Admin creates invitation (InvitePartnerAction)
- **Recipient**: Invitation email
- **Subject**: "You're Invited to Join Bookly as a Partner"
- **Content**: Invitation from admin, company name pre-filled, link to registration page with token, expiration notice (7 days)

### 5.7 Output Escaping for Rejection/Suspension Reasons

All email Blade views that render admin-provided rejection or suspension reasons (including `PartnerRejectedMail`, `PartnerSuspendedMail`, and any frontend surface displaying these reasons) MUST use escaped Blade syntax `{{ $reason }}` — never raw `{!! $reason !!}`. Reasons are stored verbatim in `partner_profiles.rejection_reason` and audit metadata for audit/history integrity; XSS prevention is enforced at render time, not at storage time (spec Edge Case: Rejection Reason Sanitization).

---

## 6. Admin In-App Operational Notifications

Distinct from the partner-facing transactional emails above (§5.1–5.7), admin in-app operational notifications use the existing `Notification` model mechanism and do NOT dispatch emails to admins.

### 6.1 Partner Application Received (Admin Notification)

- **Trigger**: After successful partner self-registration (PartnerRegistrationController)
- **Recipient**: All admin users with `manage_partners` permission (in-app `Notification` row, not email)
- **Notification fields**: `type = 'partner_application'`, `title` localized ("New Partner Application"), `body` containing the applicant's company name + contact email
- **FR reference**: FR-013 (distinct from FR-011 partner-facing emails)
- **No email is sent to admins** — this is an in-app operational notification only

---

## 6. Error Response Format

All error responses follow the standard Laravel validation error format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "company_name": ["The company name field is required."]
  }
}
```

Authorization errors return:
```json
{
  "message": "You are not authorized to perform this action."
}
```

Lifecycle transition errors return:
```json
{
  "message": "Partner cannot be rejected from its current state."
}
```

Onboarding status gate errors return:
```json
{
  "message": "Your account is pending approval. Tour creation is only available for approved partners.",
  "error_code": "ONBOARDING_STATUS_BLOCKED",
  "onboarding_status": "pending"
}
```

---

## 7. Rate Limiting

| Endpoint | Rate Limit | Key |
|----------|-----------|-----|
| `POST /api/public/auth/partners/register` | 5/min per IP | throttle:auth |
| `GET /api/public/auth/partners/invite/{token}` | 30/min per IP | throttle:traveler |
| `POST /api/public/auth/partners/invite/{token}/complete` | 5/min per IP | throttle:auth |
| `GET /api/partner/onboarding-status` | 60/min per user | throttle:booking.get |
| `POST /api/partner/onboarding/resubmit` | 3/min per user | custom:partner-resubmit |