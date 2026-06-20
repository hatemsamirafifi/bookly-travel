# Data Model: Partner Dashboard — Partner Entities

## Overview

This document defines the frontend TypeScript types and UI-facing entities for the Partner Dashboard. These types are implemented in `frontend/src/types/partner.ts` and `frontend/src/types/tour.ts`, and mirror the API responses from the Laravel backend Partner domain.

---

## 1. Partner

```typescript
interface Partner {
  id: string | number;
  company_name: string;
  slug: string;
  email: string;
  phone?: string | null;
  website?: string | null;
  logo_url?: string | null;
  verified: boolean;
  created_at: string;
  updated_at: string;
}
```

### Notes
- Partner is linked to a User via `user_id` in the backend.
- `verified` status is admin-controlled.
- `slug` is auto-generated from `company_name`.

---

## 2. Tour

```typescript
type TourStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';

interface Tour {
  id: string | number;
  partner_id: string | number;
  title: string;
  slug: string;
  description: string;
  category: string;
  destination: string;
  location: string;
  duration: { minutes: number; label: string };
  difficulty?: string;
  meeting_point: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  cancellation_policy: string;
  languages: string[];
  status: TourStatus;
  media: TourMedia[];
  pricing_tiers: PricingTier[];
  availability_rules: AvailabilityRule[];
  availability_exceptions: AvailabilityException[];
  min_participants: number;
  max_participants: number;
  rating?: { average: number; count: number };
  seo?: { meta_title: string; meta_description: string };
  created_at: string;
  updated_at: string;
  published_at?: string | null;
}
```

### Status Lifecycle
- `draft` → `pending_review` (partner submits)
- `pending_review` → `published` (admin approves) / `rejected` (admin rejects)
- `rejected` → `pending_review` (partner resubmits after edits)
- `published` → `archived` (partner archives)
- Published tours have separate `tour_drafts` for revision editing (payload-based)

---

## 3. Tour Draft

**Implementation Note**: TourDraft uses a `payload` JSONB column rather than individual form fields. The payload contains a partial Tour object. This design allows flexible, schema-free draft saves without column migrations.

```typescript
interface TourDraft {
  id: string | number;
  tour_id?: string | number | null;     // Links to existing tour for revision editing
  partner_id: string | number;
  payload: Record<string, unknown>;     // JSONB — partial Tour data snapshot
  status: 'draft' | 'pending_review';    // Draft status
  rejection_reason?: string | null;     // Admin rejection reason
  auto_saved_at: string;                // Last auto-save timestamp
  created_at: string;
  updated_at: string;
}
```

### Backend Model Fields (`TourDraft.php`)
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Auto-increment |
| `tour_id` | bigint, nullable | FK to tours table |
| `partner_id` | bigint | FK to partners table |
| `payload` | jsonb | Cast to array — partial Tour data |
| `status` | string | Draft status |
| `rejection_reason` | string, nullable | Admin feedback |
| `auto_saved_at` | timestamp, nullable | Auto-save timestamp |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### API Endpoints
- `POST /api/partner/tours/{id}/drafts/save` — Save draft (payload required)
- `GET /api/partner/tours/{id}/drafts/latest` — Retrieve latest draft

---

## 4. Tour Media

```typescript
interface TourMedia {
  id: string | number;
  url: string;
  thumbnail_url?: string;
  is_cover: boolean;
  alt_text?: string;
  sort_order: number;
}
```

---

## 5. Pricing Tier

```typescript
interface PricingTier {
  id: string | number;
  name: string;
  price: number;
  currency: string;
  min_participants: number;
  max_participants: number;
}
```

---

## 6. Availability Rule & Exception

```typescript
interface AvailabilityRule {
  id: string | number;
  frequency: 'daily' | 'weekly' | 'monthly' | 'once';
  days_of_week?: number[]; // 0 = Sunday, 6 = Saturday
  start_time: string;      // HH:MM format
  end_time?: string;
  capacity: number;
  start_date?: string;     // ISO8601
  end_date?: string;
}

interface AvailabilityException {
  id: string | number;
  date: string;            // ISO8601
  type: 'blackout' | 'specific';
  start_time?: string;
  capacity?: number;
  price_override?: number;
  reason?: string;
}
```

### Scheduling Precedence
- Blackout exceptions take precedence over all other rules.
- Specific date exceptions override recurring rules for that date.
- Recurring rules provide the baseline availability.

---

## 7. Partner Booking

```typescript
type BookingStatus = 'confirmed' | 'completed' | 'cancelled' | 'pending_payment' | 'expired' | 'no_show' | 'cancellation_requested';

interface BookingParticipant {
  tier_id: string | number;
  tier_name: string;
  count: number;
  price_per_person: number;
}

interface PartnerBooking {
  id: string | number;
  reference: string;
  status: BookingStatus;
  tour: {
    id: string | number;
    title: string;
    slug: string;
    cover_image_url?: string;
  };
  traveler: {
    id: string | number;
    name: string;
    email: string;
    phone?: string | null;
  };
  booking_date: string;
  tour_date: string;
  tour_time?: string;
  participants: BookingParticipant[];
  total_participants: number;
  total_amount: number;
  currency: string;
  special_requests?: string | null;
  payment_status: string;
  cancellation_reason?: string | null;
  cancellation_evidence_url?: string | null;
  created_at: string;
  updated_at: string;
}
```

### Notes
- Partner sees traveler info (name, email, phone) for operational purposes.
- Participants are broken down by pricing tier.
- Cancellation requires reason; evidence is optional URL array.
- Status transitions: `confirmed` → `completed` or `cancellation_requested` (partner-initiated).

---

## 8. Partner Review

```typescript
interface PartnerReview {
  id: string | number;
  tour: { id: string | number; title: string; slug: string };
  traveler_name: string;
  rating: number;
  text: string;
  submitted_at: string;
  booking_reference: string;
  response?: ReviewResponse | null;
}

interface ReviewResponse {
  id: string | number;
  text: string;
  created_at: string;
  updated_at: string;
}
```

---

## 9. Notification

```typescript
type NotificationType = 'booking' | 'review' | 'tour_status' | 'system' | 'payout' | 'cancellation';
type NotificationPriority = 'high' | 'normal' | 'low';

interface Notification {
  id: string | number;
  type: NotificationType;
  priority: NotificationPriority;
  title: string;
  message: string;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at: string;
}
```

### WebSocket Events (broadcast on `private-partner.{partner_id}`)
| Event | Trigger |
|-------|---------|
| `NewBooking` | New booking created for partner's tour |
| `TourApproved` | Admin approves partner's tour |
| `TourRejected` | Admin rejects partner's tour |
| `BookingCancelled` | Booking cancellation processed |
| `ReviewReceived` | New review submitted for partner's tour |
| `PaymentStatusChanged` | Payment status update |
| `DailySummaryReady` | Daily summary job completed |

---

## 10. Partner Profile & Settings

**Implementation Note**: Payout fields are encrypted at rest using Laravel Crypt and masked on read. The `PartnerSettings` model uses flat boolean columns (not a nested JSON object) for notification preferences.

```typescript
interface PartnerProfile {
  id: string | number;
  partner_id: string | number;
  company_name: string;
  business_description?: string | null;
  logo_url?: string | null;
  contact_email: string;
  contact_phone?: string | null;
  website?: string | null;
  business_address?: {
    street?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
  } | null;                              // Cast as 'array' in backend
  tax_id?: string | null;
  payout_holder_name?: string | null;
  payout_bank_name?: string | null;
  payout_account_number?: string | null;  // Encrypted at rest, masked on read (****5678)
  payout_iban?: string | null;            // Encrypted at rest, masked on read (***...5678)
  payout_swift_bic?: string | null;       // Encrypted at rest, masked on read (BCI***)
  payout_country?: string | null;
  created_at: string;
  updated_at: string;
}
```

### Encryption Details
- `payout_account_number`: Read → `****` + last 4 digits; Write → `Crypt::encryptString()`
- `payout_iban`: Read → masked with `*` except last 4; Write → `Crypt::encryptString()`
- `payout_swift_bic`: Read → first 3 chars + `***`; Write → `Crypt::encryptString()`
- Raw (decrypted) values available via `getRawPayoutAccountNumber()`, `getRawPayoutIban()`, `getRawPayoutSwiftBic()` for processing.

```typescript
interface PartnerSettings {
  id: string | number;
  partner_id: string | number;
  notify_new_booking: boolean;
  notify_cancellation: boolean;
  notify_daily_summary: boolean;
  notify_review_received: boolean;
  notify_tour_status_change: boolean;
  locale: string;                       // 2-letter code (en, es, it)
  created_at: string;
  updated_at: string;
}
```

### Backend Model Fields (`PartnerSettings.php`)
All notification preferences are flat boolean columns (not nested JSON), cast as `boolean` in Eloquent.

---

## 11. Analytics Summary

```typescript
interface AnalyticsSummary {
  total_bookings: number;
  total_revenue: {
    amount: number;
    currency: string;
    formatted: string;
  };
  average_rating: number;
  review_count: number;
  conversion_rate: number; // percentage
  upcoming_bookings: number;
  bookings_over_time: {
    date: string;
    count: number;
    revenue: number;
  }[];
}

interface DateRangeFilter {
  from: string; // ISO8601
  to: string;   // ISO8601
}
```

---

## 12. Pagination & Upload

```typescript
interface PaginatedPartnerResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface SignedUploadUrl {
  signed_url: string;     // R2 presigned PUT URL
  public_url: string;     // CDN URL after upload
  expires_at: string;     // ISO8601, 15 min expiry
}
```

### Upload Constraints
- Allowed MIME types: `image/jpeg`, `image/png`
- Maximum file size: 5 MB (5,248,880 bytes)
- Upload flow: POST `/api/partner/uploads/signed-url` → receive presigned URL → PUT directly to R2