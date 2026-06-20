# Research: Partner Dashboard

## Phase 0 — Research Output

**Feature**: Partner Dashboard — Tour Creation, Booking Management & Partner Profile
**Date**: 2026-05-28

---

## 1. Existing Infrastructure

### Backend (Implemented)

The Laravel backend Partner domain is fully implemented at `backend/app/Domains/Partner/`:

- **7 Controllers**: Tour, Booking, Review, Notification, Profile, Upload, Analytics
- **6 Services**: TourService, BookingService, ReviewService, NotificationService, ProfileService, AnalyticsService
- **10 Models**: Partner, PartnerProfile, PartnerSettings, TourDraft, TourMedia, PricingTier, AvailabilityRule, AvailabilityException, Notification, ReviewResponse
- **7 Events**: NewBooking, BookingCancelled, ReviewReceived, TourApproved, TourRejected, PaymentStatusChanged, DailySummaryReady
- **1 Job**: SendDailySummaryJob
- **1 Middleware**: PartnerRoleMiddleware
- **1 Policy**: TourPolicy
- **5 Form Requests**: StoreTourRequest, UpdateTourRequest, UpdateProfileRequest, UpdateBookingStatusRequest, StoreReviewResponseRequest
- **1 Validation Rule**: ValidIban

### Frontend (Partially Implemented)

- **Types**: `frontend/src/types/partner.ts` — complete type definitions for all entities
- **API Client**: `frontend/src/lib/api/partner.ts` — complete API client with all endpoints
- **Auth Guard**: `frontend/src/components/auth/PartnerAuthGuard.tsx` — exists
- **Layout Components**: `PartnerHeader.tsx`, `PartnerSidebar.tsx` — exist
- **Realtime Hook**: `frontend/src/lib/hooks/usePartnerRealtime.ts` — exists
- **Route Directories**: Partner route directories exist but pages are empty

---

## 2. Technology Decisions

| Decision | Choice | Rationale |
|---|---|---|
| **Framework** | Next.js 16 (App Router) | Consistent with Phase 1 (spec 010) |
| **Styling** | Tailwind CSS 4.x | Consistent with existing frontend |
| **State** | TanStack Query | Server-state caching for API data |
| **Charts** | Recharts or Chart.js | Lightweight charting for analytics |
| **File Upload** | Signed URL direct upload | Avoid server bottleneck; already in API client |
| **Drag & Drop** | @dnd-kit/core | Accessible drag-and-drop for media reordering |
| **Form Validation** | Zod + react-hook-form | Type-safe validation matching backend rules |
| **Real-time** | WebSocket/SSE | Already configured via usePartnerRealtime hook |

---

## 3. API Endpoints (Existing)

| Domain | Endpoints | Methods |
|---|---|---|
| **Tours** | `/api/partner/tours`, `/api/partner/tours/{id}`, `/api/partner/tours/drafts`, `/api/partner/tours/drafts/{id}` | GET, POST, PUT, DELETE |
| **Bookings** | `/api/partner/bookings`, `/api/partner/bookings/{ref}`, `/api/partner/bookings/{ref}/status`, `/api/partner/bookings/{ref}/request-cancellation` | GET, PATCH, POST |
| **Reviews** | `/api/partner/reviews`, `/api/partner/reviews/{id}/respond` | GET, POST, PUT |
| **Notifications** | `/api/partner/notifications`, `/api/partner/notifications/{id}/read`, `/api/partner/notifications/read-all` | GET, POST |
| **Analytics** | `/api/partner/analytics` | GET |
| **Profile** | `/api/partner/profile`, `/api/partner/profile/settings` | GET, PUT |
| **Uploads** | `/api/partner/uploads/signed-url` | GET |

---

## 4. Risk Assessment

| Risk | Mitigation |
|---|---|
| Tour creation wizard complexity | Multi-step with draft save; each step is independently saveable |
| File upload failures | Retry logic with exponential backoff; clear error messages |
| Large booking lists performance | Server-side pagination (20 per page); TanStack Query caching |
| Real-time notification reliability | Fallback to polling if WebSocket disconnects |
| IBAN validation accuracy | Use ValidIban backend rule + client-side format check |
