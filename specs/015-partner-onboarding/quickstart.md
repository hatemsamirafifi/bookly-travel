# Quickstart: Partner Onboarding

**Feature**: 015-partner-onboarding
**Date**: 2026-08-18

## Prerequisites

- Laravel backend running with database migrations applied
- Next.js frontend running with locale support (EN/ES/IT)
- Redis running for queue processing
- Mail driver configured (SMTP or log for local dev)
- Existing Partner domain infrastructure (Partner model, PartnerProfile, lifecycle Actions)

## Backend Setup

### 1. Run Migrations

```bash
cd backend
php artisan migrate
```

This creates:
- `partner_invitations` table
- `rejection_reason` column on `partner_profiles`
- `invited_by_admin` column on `partners`

### 2. Register the PartnerInvitation Model

Ensure `PartnerInvitation` is registered in the morph map (if not already in `AppServiceProvider`):

```php
// AppServiceProvider::boot()
Relation::enforceMorphMap([
    'admin' => User::class,
    'partner' => Partner::class,
    'tour' => Tour::class,
    'booking' => Booking::class,
    'review' => Review::class,
    'static_page' => StaticPage::class,
    'invitation' => PartnerInvitation::class,
]);
```

### 3. Register Routes

Add to `routes/api/public.php`:

```php
use App\Domains\Partner\Controllers\Public\PartnerInvitationController;

Route::prefix('partners')->group(function () {
    Route::post('register', PartnerRegistrationController::class);
    Route::get('invite/{token}', [PartnerInvitationController::class, 'show']);
    Route::post('invite/{token}/complete', [PartnerInvitationController::class, 'complete']);
});
```

Add to `routes/api/partner.php`:

```php
use App\Domains\Partner\Controllers\PartnerOnboardingStatusController;

Route::prefix('onboarding')->group(function () {
    Route::get('status', [PartnerOnboardingStatusController::class, 'show']);
    Route::post('resubmit', [PartnerOnboardingStatusController::class, 'resubmit']);
});
```

### 4. Enhance PartnerRoleMiddleware

The existing middleware must allow rejected partners to access profile and onboarding endpoints while blocking tour creation:

```php
// In PartnerRoleMiddleware::handle()
// Allow rejected partners to reach onboarding/profile routes
// Block tour creation for non-approved partners
```

### 5. Schedule Invitation Expiration

Add to `app/Console/Kernel.php` or `routes/console.php`:

```php
Schedule::command('partner-invitations:expire')->daily();
```

### 6. Create the Custom Artisan Command

```bash
php artisan make:command ExpirePartnerInvitations
```

Implement the command to set `status = 'expired'` for invitations past their `expires_at`.

## Frontend Setup

### 1. Add Onboarding Types to `types/partner.ts`

```typescript
export interface PartnerOnboardingStatus {
  onboarding_status: 'pending' | 'approved' | 'rejected' | 'suspended';
  can_create_tours: boolean;
  rejection_reason: string | null;
  submitted_at: string | null;
  approved_at?: string | null;
  rejected_at?: string | null;
  suspended_at?: string | null;
  suspension_reason?: string | null;
  message: string | null;
}

export interface PartnerInvitation {
  email: string;
  company_name: string;
  contact_person: string | null;
  expires_at: string;
}

export interface ResubmitPayload {
  company_name: string;
  contact_email: string;
  contact_phone: string;
  business_description: string;
  business_address: {
    street: string;
    city: string;
    state?: string;
    postal_code: string;
    country: string;
  };
}
```

### 2. Add API Functions to `lib/api/partner.ts`

```typescript
export function getOnboardingStatus() {
  return apiClient<PartnerOnboardingStatus>('/api/partner/onboarding-status', {
    headers: authHeaders(),
  });
}

export function resubmitApplication(payload: ResubmitPayload) {
  return apiClient<PartnerOnboardingStatus>('/api/partner/onboarding/resubmit', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
}

export function getInvitationDetails(token: string) {
  return apiClient<PartnerInvitation>(`/api/public/auth/partners/invite/${encodeURIComponent(token)}`, {
    headers: {},
  });
}

export function completeInvitation(token: string, payload: Record<string, unknown>) {
  return apiClient<{ data: { user: any; partner: any; token: string }; message: string }>(
    `/api/public/auth/partners/invite/${encodeURIComponent(token)}/complete`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }
  );
}
```

### 3. Add Locale Strings

Add to `messages/en.json`, `messages/es.json`, `messages/it.json` under a `partnerOnboarding` key.

## Testing

### Backend Tests

```bash
cd backend
# Run all partner onboarding tests
php artisan test --filter=Partner

# Specific test classes
php artisan test tests/Feature/Partner/PartnerRegistrationTest.php
php artisan test tests/Feature/Partner/PartnerLifecycleTest.php
php artisan test tests/Feature/Partner/PartnerInvitationTest.php
php artisan test tests/Feature/Partner/PartnerResubmissionTest.php
```

### Frontend Tests

```bash
cd frontend
npm test -- --testPathPattern=partner
```

## Key Files to Create

| File | Purpose |
|------|---------|
| `backend/app/Domains/Partner/Models/PartnerInvitation.php` | Invitation model |
| `backend/app/Domains/Partner/Controllers/Public/PartnerInvitationController.php` | Invitation endpoints |
| `backend/app/Domains/Partner/Controllers/PartnerOnboardingStatusController.php` | Onboarding status + resubmit |
| `backend/app/Domains/Partner/Actions/ResubmitPartnerApplicationAction.php` | Resubmission action |
| `backend/app/Domains/Partner/Actions/CompletePartnerInvitationAction.php` | Invitation completion action |
| `backend/app/Domains/Admin/Actions/InvitePartnerAction.php` | Admin invitation creation |
| `backend/app/Domains/Partner/Requests/PartnerResubmitRequest.php` | Resubmission validation |
| `backend/app/Domains/Partner/Requests/CompleteInvitationRequest.php` | Invitation completion validation |
| `backend/app/Domains/Admin/Filament/PartnerResource.php` | Filament admin resource |
| `backend/app/Mail/PartnerSuspendedMail.php` | Suspension email |
| `backend/app/Mail/PartnerReinstatedMail.php` | Reinstatement email |
| `backend/app/Mail/PartnerApplicationReceivedMail.php` | Application received email |
| `backend/app/Mail/PartnerInvitationMail.php` | Invitation email |
| `backend/app/Console/Commands/ExpirePartnerInvitations.php` | Scheduled command |
| `frontend/src/app/[locale]/(auth)/partner-register/page.tsx` | Registration page |
| `frontend/src/app/[locale]/(auth)/partner-invite/[token]/page.tsx` | Invitation acceptance page |
| `frontend/src/app/[locale]/(partner)/partner/onboarding/page.tsx` | Onboarding status page |
| `frontend/src/components/partner/OnboardingStatusBanner.tsx` | Status banner component |
| `frontend/src/components/partner/PartnerRegistrationForm.tsx` | Registration form component |
| `frontend/src/components/partner/InvitationAcceptanceForm.tsx` | Invitation form component |
| `frontend/src/components/partner/ResubmissionForm.tsx` | Resubmission form component |
| `backend/tests/Feature/Partner/EmailOutputEscapingTest.php` | Output escaping verification for rejection/suspension reasons (T031a) |
| `frontend/src/components/partner/ResubmissionForm.tsx` | Resubmission form component |