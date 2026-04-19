# Quickstart: Traveler Registration

**Feature**: 003-traveler-registration
**Date**: 2026-04-18
**Prerequisites**: Phase 2 infrastructure complete (002-foundational-implementation)

## Prerequisites

- Backend running (`cd backend && php artisan serve`)
- Frontend running (`cd frontend && npm run dev`)
- Database migrated (`php artisan migrate`)
- Queue worker running (`php artisan queue:work`)
- Docker services up (`docker compose up -d` for PostgreSQL, Redis, Mailpit)

## Files to Implement (in order)

### 1. Backend — Server-Side Validation

```
backend/app/Http/Requests/Auth/RegisterRequest.php    # Form Request with validation rules
```

### 2. Backend — Domain Actions

```
backend/app/Domains/Auth/Actions/RegisterTravelerAction.php     # Orchestrates registration flow
backend/app/Domains/Auth/Actions/LinkGuestBookingsAction.php     # Links guest bookings by email
backend/app/Domains/Auth/Actions/SendVerificationEmailAction.php # Queues verification email
```

### 3. Backend — Email

```
backend/app/Mail/VerificationMail.php          # Multi-language mailable
backend/app/Jobs/SendVerificationEmail.php     # Queued, retry-safe job
```

### 4. Backend — HTTP Layer

```
backend/app/Http/Controllers/Public/Auth/RegisterController.php  # Thin controller
backend/routes/api/public.php                                    # Add POST /register route
```

### 5. Backend — Tests

```
backend/tests/Feature/Auth/RegistrationTest.php  # Feature tests
```

### 6. Frontend — Registration Page

```
frontend/src/components/auth/RegisterForm.tsx                    # Form component
frontend/src/app/[locale]/auth/register/page.tsx                 # Page with form + return-to-URL
frontend/messages/en.json                                        # Add register form keys
frontend/messages/es.json                                        # Add register form keys
frontend/messages/it.json                                        # Add register form keys
```

## Testing

```bash
# Backend — registration tests only
cd backend
php artisan test --filter=RegistrationTest

# Manual frontend verification
# 1. Navigate to http://localhost:3000/en/auth/register
# 2. Submit with empty fields → client-side validation errors
# 3. Submit with weak password → password strength error
# 4. Submit with valid data → account created, redirected
# 5. Submit with same email → "email taken" error
# 6. Check Mailpit (http://localhost:8025) → verification email received
# 7. Repeat steps 1-4 in /es/ and /it/ → all text translated
```

## Verification Checklist

- [ ] Can register with valid name, email, and password
- [ ] Receive 201 response with user data and token
- [ ] Invalid fields return specific per-field validation errors
- [ ] Duplicate email returns "email taken" error
- [ ] Weak password returns specific feedback
- [ ] Verification email appears in Mailpit
- [ ] Registration event appears in auth_audit_logs table
- [ ] Guest bookings linked automatically (if bookings table exists)
- [ ] Registration page renders correctly in EN, ES, IT
- [ ] Return-to-URL redirect works after registration
- [ ] Rate limiting blocks after 10 requests/minute
