# Quickstart: Traveler Authentication

**Feature**: 001-traveler-auth
**Date**: 2026-04-13

## Prerequisites

- PHP 8.2+ with Composer
- Node.js 18+ with npm
- PostgreSQL 15+
- Redis 7+
- Docker (recommended for local development)

## Backend Setup

```bash
# From project root
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Configure database (edit .env)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=bookly
# DB_USERNAME=bookly
# DB_PASSWORD=secret

# Configure Redis (edit .env)
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
# QUEUE_CONNECTION=redis

# Configure mail (edit .env for local dev)
# MAIL_MAILER=log   (logs emails instead of sending)

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Start the development server
php artisan serve

# In a separate terminal, start the queue worker
php artisan queue:work
```

## Frontend Setup

```bash
# From project root
cd frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Configure API URL (edit .env.local)
# NEXT_PUBLIC_API_URL=http://localhost:8000/api

# Start the development server
npm run dev
```

## Key Files to Implement (in order)

### 1. Database Migrations

```
backend/database/migrations/
├── xxxx_create_users_table.php
├── xxxx_create_personal_access_tokens_table.php  (Sanctum default)
├── xxxx_create_password_reset_tokens_table.php   (Laravel default)
├── xxxx_create_guest_identities_table.php
└── xxxx_create_auth_audit_logs_table.php
```

### 2. Models & Auth Configuration

```
backend/app/Models/User.php                    → Role enum, Sanctum HasApiTokens
backend/app/Models/GuestIdentity.php           → Guest checkout records
backend/app/Models/AuthAuditLog.php            → Audit log entries
backend/config/sanctum.php                     → Token expiration config
```

### 3. Domain Logic (Services & Actions)

```
backend/app/Domains/Auth/Services/AuthService.php
backend/app/Domains/Auth/Actions/RegisterTravelerAction.php
backend/app/Domains/Auth/Actions/LoginAction.php
backend/app/Domains/Auth/Actions/ConvertGuestToAccountAction.php
backend/app/Domains/Auth/Actions/LinkGuestBookingsAction.php
backend/app/Domains/Auth/Actions/SendVerificationEmailAction.php
```

### 4. HTTP Layer

```
backend/app/Http/Controllers/Public/Auth/*Controller.php
backend/app/Http/Requests/Auth/*Request.php
backend/app/Http/Resources/UserResource.php
backend/routes/api/public.php
```

### 5. Events & Audit Logging

```
backend/app/Domains/Auth/Events/*.php
backend/app/Domains/Auth/Listeners/LogAuthEvent.php
```

### 6. Frontend Auth Pages

```
frontend/src/app/[locale]/auth/login/page.tsx
frontend/src/app/[locale]/auth/register/page.tsx
frontend/src/app/[locale]/auth/forgot-password/page.tsx
frontend/src/app/[locale]/auth/reset-password/page.tsx
frontend/src/components/auth/*.tsx
frontend/src/lib/api/auth.ts
frontend/src/lib/hooks/useAuth.ts
frontend/src/i18n/{en,es,it}/auth.json
```

## Testing

```bash
# Backend tests
cd backend
php artisan test --filter=Auth

# Frontend tests
cd frontend
npm run test -- --filter=auth
```

## Verification Checklist

- [ ] Can register a new traveler account
- [ ] Can sign in with valid credentials
- [ ] Can sign out (token revoked)
- [ ] Incorrect credentials show generic error
- [ ] Account locks after 5 failed attempts (1 min lockout)
- [ ] Can request password reset email
- [ ] Can reset password via email link
- [ ] Can change password while signed in
- [ ] Verification email sent on registration
- [ ] Password reset blocked for unverified emails
- [ ] Guest checkout captures identity without account
- [ ] Guest can convert to account post-booking
- [ ] Guest bookings linked on account creation
- [ ] Auth events appear in audit log
- [ ] All auth pages work in EN, ES, IT
