export type TourStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';

export type BookingStatus = 'confirmed' | 'completed' | 'cancelled' | 'pending_payment' | 'expired' | 'no_show' | 'cancellation_requested';

export type NotificationType = 'booking' | 'review' | 'tour_status' | 'system' | 'payout' | 'cancellation';

export type NotificationPriority = 'high' | 'normal' | 'low';

export interface Partner {
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

export interface TourMedia {
  id: string | number;
  url: string;
  thumbnail_url?: string;
  is_cover: boolean;
  alt_text?: string;
  sort_order: number;
}

export interface PricingTier {
  id: string | number;
  name: string;
  price: number;
  currency: string;
  min_participants: number;
  max_participants: number;
}

export interface AvailabilityRule {
  id: string | number;
  frequency: 'daily' | 'weekly' | 'monthly' | 'once';
  days_of_week?: number[]; // 0 = Sunday, 6 = Saturday
  start_time: string; // HH:MM format
  end_time?: string; // HH:MM format
  capacity: number;
  start_date?: string; // ISO8601
  end_date?: string; // ISO8601
}

export interface AvailabilityException {
  id: string | number;
  date: string; // ISO8601
  type: 'blackout' | 'specific';
  start_time?: string;
  capacity?: number;
  price_override?: number;
  reason?: string;
}

export interface Tour {
  id: string | number;
  partner_id: string | number;
  title: string;
  slug: string;
  description: string;
  category: string;
  destination: string;
  location: string;
  duration: {
    minutes: number;
    label: string;
  };
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
  rating?: {
    average: number;
    count: number;
  };
  seo?: {
    meta_title: string;
    meta_description: string;
  };
  created_at: string;
  updated_at: string;
  published_at?: string | null;
}

export interface TourDraft {
  id: string | number;
  tour_id?: string | number | null;
  partner_id: string | number;
  title?: string;
  description?: string;
  category?: string;
  destination?: string;
  location?: string;
  duration?: Record<string, unknown>;
  difficulty?: string;
  meeting_point?: string;
  highlights?: string[];
  inclusions?: string[];
  exclusions?: string[];
  cancellation_policy?: string;
  languages?: string[];
  media?: TourMedia[];
  pricing_tiers?: PricingTier[];
  availability_rules?: AvailabilityRule[];
  availability_exceptions?: AvailabilityException[];
  min_participants?: number;
  max_participants?: number;
  seo?: Record<string, unknown>;
  current_step: number;
  last_saved_at: string;
  submitted_for_review: boolean;
  admin_rejection_reason?: string | null;
  created_at: string;
  updated_at: string;
}

export interface BookingParticipant {
  tier_id: string | number;
  tier_name: string;
  count: number;
  price_per_person: number;
}

export interface PartnerBooking {
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
  booking_date: string; // ISO8601
  tour_date: string; // ISO8601
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

/**
 * A review returned by `GET /api/partner/reviews` (spec-009 contracts/api.md).
 * Shape mirrors `PartnerReviewResource` on the backend: only these fields are
 * exposed — `traveler_id`/`booking_id`/`tour_id`/`locale`/`edited_at` are never
 * sent. `reviewer_name` is the traveler's first name only (FR-004).
 */
export interface PartnerReview {
  id: string | number;
  tour_slug: string;
  tour_title: string;
  reviewer_name: string;
  rating: number;
  comment: string | null;
  status: string;
  created_at: string;
  response?: {
    response_text: string;
    created_at: string;
    updated_at: string;
  } | null;
}

export interface TourSummary {
  tour_slug: string;
  tour_title: string;
  average_rating: number;
  review_count: number;
}

export interface ReviewResponse {
  id: string | number;
  response_text: string;
  created_at: string;
  updated_at: string;
  edited_at?: string | null;
}

export interface Notification {
  id: string | number;
  type: NotificationType;
  priority: NotificationPriority;
  title: string;
  message: string;
  data?: Record<string, unknown>;
  read_at?: string | null;
  created_at: string;
}

export interface PartnerProfile {
  id: string | number;
  company_name: string;
  description?: string | null;
  email: string;
  phone?: string | null;
  website?: string | null;
  logo_url?: string | null;
  address?: {
    street?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
  } | null;
  tax_id?: string | null;
  preferred_language: 'en' | 'es' | 'it';
  preferred_currency: string;
  verified: boolean;
  created_at: string;
  updated_at: string;
}

export interface PartnerPayoutInfo {
  account_holder_name: string;
  bank_name: string;
  account_number: string;
  iban?: string | null;
  swift_bic?: string | null;
  country: string;
}

export interface PartnerSettings {
  notification_preferences: {
    new_booking_alert: boolean;
    cancellation_alert: boolean;
    daily_summary_email: boolean;
    review_received_alert: boolean;
    tour_status_change_alert: boolean;
  };
  payout_info?: PartnerPayoutInfo | null;
}

/**
 * Summary metrics returned by `GET /api/partner/analytics` under the `summary`
 * key. Mirrors `AnalyticsService::getSummary` exactly:
 * - `total_revenue` is a raw integer sum of `total_price` (major currency units).
 * - `conversion_rate` is a percentage (e.g. `3.4` means 3.4%); the backend
 *   currently returns `0.0` until tour-view tracking is implemented.
 *
 * `upcoming_bookings` and `review_count` are optional: the backend does not
 * emit them today, so the dashboard hides those cards when absent rather than
 * fabricating values.
 */
export interface AnalyticsSummary {
  total_bookings: number;
  total_revenue: number;
  average_rating: number;
  conversion_rate: number; // percentage
  upcoming_bookings?: number;
  review_count?: number;
}

/** A single point in the `bookings_over_time` series returned by the analytics endpoint. */
export interface AnalyticsBookingsPoint {
  date: string;
  bookings: number;
  revenue: number;
}

/** Full response of `GET /api/partner/analytics` (no `{ data }` envelope). */
export interface AnalyticsResponse {
  summary: AnalyticsSummary;
  bookings_over_time: AnalyticsBookingsPoint[];
  period: {
    from: string;
    to: string;
  };
}

export interface DateRangeFilter {
  from: string; // ISO8601
  to: string; // ISO8601
}

export interface PaginatedPartnerResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    /**
     * Per-tour rating aggregates. Only present on the reviews endpoint
     * (`GET /api/partner/reviews`); other paginated partner endpoints omit it.
     */
    tour_summaries?: TourSummary[];
  };
}

export interface SignedUploadUrl {
  upload_url: string;
  public_url: string;
  expires_at: string;
}

