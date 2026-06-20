# Quickstart: Partner Dashboard

## Prerequisites

- Node.js ≥ 20
- Backend running at `http://localhost:8000` (Laravel)
- Frontend running at `http://localhost:3000` (Next.js)
- Partner account created and authenticated

## Setup

```bash
# Navigate to frontend
cd frontend

# Install dependencies (if not already)
npm install

# Start dev server
npm run dev
```

## Key Routes

| Route | Description |
|---|---|
| `/{locale}/partner` | Dashboard overview with analytics |
| `/{locale}/partner/tours` | Tour list with status filters |
| `/{locale}/partner/tours/create` | Multi-step tour creation wizard |
| `/{locale}/partner/tours/{id}/edit` | Edit existing tour |
| `/{locale}/partner/bookings` | Booking management with filters |
| `/{locale}/partner/reviews` | Review management with responses |
| `/{locale}/partner/analytics` | Detailed analytics page |
| `/{locale}/partner/profile` | Profile, settings & payout info |

## Backend API Base

All partner API endpoints are prefixed with `/api/partner/` and require Bearer token authentication.

```bash
# Test partner auth
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/partner/profile

# Test tours list
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/partner/tours

# Test bookings
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/partner/bookings

# Test analytics
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/partner/analytics
```

## Running Tests

```bash
cd frontend

# Unit tests
npm run test

# E2E tests (Playwright)
npm run test:e2e

# Lint
npm run lint

# Type check
npx tsc --noEmit
```

## Existing Code

### Backend (fully implemented)
```
backend/app/Domains/Partner/
├── Controllers/   (7 controllers)
├── Services/      (6 services)
├── Models/        (10 models)
├── Events/        (7 events)
├── Middleware/     (PartnerRoleMiddleware)
└── Requests/      (5 form requests)
```

### Frontend (types + API client ready)
```
frontend/src/
├── types/partner.ts           # All TypeScript types
├── lib/api/partner.ts         # Complete API client
├── lib/hooks/usePartnerRealtime.ts  # WebSocket hook
├── components/auth/PartnerAuthGuard.tsx
└── components/partner/layout/
    ├── PartnerHeader.tsx
    └── PartnerSidebar.tsx
```

## Design System Tokens

| Token | Value |
|---|---|
| Primary (Navy) | `#0A2540` |
| Accent (Gold) | `#FFB800` |
| Typography | Inter |
| Grid | 8px |
| Border Radius | 12px |
| Mobile Breakpoint | 390px |
| Tablet Breakpoint | 780px |
| Desktop Breakpoint | 1280px |
