# Bookly — Agent Guide

> This file provides context for any AI coding agent working on the Bookly
> codebase. Read this file and the constitution before writing any code.

## What is Bookly?

Bookly is a **tours-only marketplace** where:

- **Travelers** search, discover, and book tours with instant confirmation
- **Partners** create, price, and manage their own tour listings
- **Admins** moderate partners and tours, oversee bookings and finances

Phase 1 delivers the core booking MVP across 11 feature specifications.

## Critical Documents

| Document | Path | Read When |
|----------|------|-----------|
| Constitution | `.specify/memory/constitution.md` | Before any architectural or design decision |
| Spec Strategy | `docs/specification-strategy.md` | Before starting any feature spec |
| Feature Specs | `specs/NNN-feature-name/spec.md` | Before implementing a specific feature |

The **constitution is the highest authority**. If any spec, plan, or task
conflicts with it, the constitution wins.

## Technology Stack

### Backend

- **Framework**: Laravel (API-only mode)
- **Admin panel**: Laravel Filament (server-rendered)
- **Auth**: Laravel Sanctum (token-based)
- **Database**: PostgreSQL
- **Cache / Queue**: Redis
- **Search**: Laravel Scout
- **Storage**: Cloudflare R2 (S3-compatible)
- **Payments**: Stripe

### Frontend

- **Framework**: Next.js 14
- **Language**: TypeScript (strict)
- **Styling**: Tailwind CSS
- **Rendering**: SSR/SSG for all public pages (SEO requirement)
- **Languages**: English, Spanish, Italian with localized routes

### Surfaces

| Surface | Tech | Route Prefix |
|---------|------|-------------|
| Public website | Next.js (SSR/SSG) | `/en/`, `/es/`, `/it/` |
| Partner dashboard | Next.js | `/partner/` |
| Admin dashboard | Filament | `/admin/` |
| Backend API | Laravel | `/api/public/*`, `/api/partner/*`, `/api/admin/*` |

## Non-Negotiable Rules

These rules come directly from the constitution. Violating them is a blocking
review issue.

### Architecture

1. **API-first**: Frontend never bypasses the API. No direct database access
   from Next.js.
2. **Thin controllers**: Controllers do request handling and authorization only.
   Business logic belongs in services or action classes.
3. **No DB queries in controllers**: All data access goes through services or
   repositories.
4. **Modular domains**: Backend organized by business domain (Auth, Tours,
   Bookings, Payments, etc.). Cross-domain calls use service interfaces.
5. **Search separation**: Public search uses Laravel Scout, not direct SQL.

### Security

6. **Server-side validation**: All write operations validated via Form Requests.
   Client validation is UX, not security.
7. **Four-layer authorization**: Every protected resource checks authentication →
   role → permission → ownership.
8. **No hardcoded secrets**: Everything from environment variables.
9. **Idempotent financial flows**: Idempotency keys on all payment and booking
   operations.
10. **Secure file uploads**: Validate MIME type, size, integrity. Store in R2.

### Product

11. **Tours only**: No hotels, flights, transfers, or other verticals.
12. **Direct booking only**: Instant confirmation. No request-to-book or waitlists.
13. **Admin approval required**: Tours are not public until an admin approves them.
14. **Platform-controlled commerce**: All payments through the platform. Immutable
    financial ledger.
15. **Review integrity**: Reviews only for completed bookings. One per booking.

## Phase 1 Product Decisions

| Decision | Value |
|----------|-------|
| Guest checkout | Enabled (account optional for booking) |
| Partner accounts | One account per partner (no multi-staff) |
| Notifications | Email only (queued, no SMS/push) |
| Refunds | Manual via Stripe dashboard (no automation) |
| Booking completion | Auto-complete after tour date (scheduled job) |
| Cancellation | Traveler can cancel before tour date (no penalties) |
| Reviews | Submit only (rating + comment, no partner replies) |
| Languages | English, Spanish, Italian |
| Admin dashboard | Laravel Filament |

## Code Patterns

### Laravel Controller Example

```php
// CORRECT — thin controller, Form Request, service layer
class BookTourController extends Controller
{
    public function __invoke(BookTourRequest $request, BookingService $service)
    {
        $this->authorize('create', Booking::class);
        $booking = $service->create($request->validated());
        return new BookingResource($booking);
    }
}

// WRONG — business logic in controller, direct DB access
class BookTourController extends Controller
{
    public function __invoke(Request $request)
    {
        $tour = Tour::find($request->tour_id);           // direct DB
        $booking = Booking::create([...]);                // logic in controller
        Stripe::charge($request->amount);                 // no service layer
        return response()->json($booking);
    }
}
```

### Queued Job Pattern

```php
// Jobs MUST be idempotent (retry-safe)
class SendBookingConfirmation implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [10, 60, 300];

    public function handle(): void
    {
        // Check if already sent to prevent duplicates
        if ($this->booking->confirmation_sent_at) {
            return;
        }
        // ... send email, then mark as sent
    }
}
```

## File Organization

```
bookly-travel/
├── backend/                    → Laravel application
│   ├── app/
│   │   ├── Actions/            → Single-purpose business actions
│   │   ├── Http/
│   │   │   ├── Controllers/    → Thin controllers only
│   │   │   ├── Requests/       → Form Request validation
│   │   │   └── Resources/      → API response transformers
│   │   ├── Models/             → Eloquent models
│   │   ├── Services/           → Business logic services
│   │   ├── Jobs/               → Queued jobs (retry-safe)
│   │   └── Policies/           → Authorization policies
│   ├── database/migrations/    → Schema migrations
│   ├── routes/
│   │   ├── api/public.php      → Public API routes
│   │   ├── api/partner.php     → Partner API routes
│   │   └── api/admin.php       → Admin API routes
│   └── tests/
├── frontend/                   → Next.js application
│   ├── src/
│   │   ├── app/                → App Router pages
│   │   ├── components/         → Shared UI components
│   │   ├── lib/                → API clients, utilities
│   │   └── i18n/               → Translation files (en, es, it)
│   └── tests/
├── docs/                       → Project documentation
├── specs/                      → Feature specifications
└── .specify/                   → Spec Kit config and templates
```

## Commit Messages

Format: `type(scope): description`

- `feat(003):` new feature (spec number as scope)
- `fix(007):` bug fix
- `docs:` documentation changes
- `refactor(004):` restructuring
- `test(006):` test additions
- `chore:` tooling and config

## Audit Logging

These actions MUST produce audit log entries:

- Admin approves/rejects partner or tour
- Booking status changes (created → confirmed → completed → cancelled → refunded)
- Financial transactions (charge, refund)
- Partner management (suspend, unsuspend)

Each entry records: **actor, action, target, timestamp, before/after state**.
