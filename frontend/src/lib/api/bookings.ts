import { apiClient } from './client';
import { getAuthToken } from '../auth/token';

export interface CreateBookingRequest {
  tour_slug: string;
  tour_date: string;
  participant_count: number;
  locale?: string;
  /** Price per person in cents as shown on the tour detail page (FR-027 drift detection). */
  page_load_price?: number;
}

export interface BookingResponse {
  reference: string;
  tour: {
    slug: string;
    title: string;
    location: string;
    cover_image_url: string;
  };
  tour_date: string;
  participant_count: number;
  total_price: {
    amount: number;
    currency: string;
    formatted: string;
  };
  pricing?: {
    price_per_person: {
      amount: number;
      currency: string;
      formatted: string;
    };
    total: {
      amount: number;
      currency: string;
      formatted: string;
    };
  };
  status: string;
  cancellation_policy: string;
  cancellation_window_hours: number;
  can_cancel: boolean;
  created_at: string;
}

export interface PaymentInfo {
  client_secret: string;
  stripe_publishable_key: string;
}

export interface CreateBookingResult {
  data: BookingResponse;
  /** True when the tour's current price differs from the price_load_price sent.
   *  When true, the UI must show a re-confirmation modal before treating the
   *  booking as accepted by the traveler (FR-027). */
  price_changed?: boolean;
  payment?: PaymentInfo;
}

/**
 * Create a booking. The caller MUST supply a stable `idempotencyKey` (a UUID
 * v4) so the backend collapses duplicate submissions / retries of the same
 * selection into a single booking (F2). There is no internal fallback — forcing
 * the caller to pass the key makes the idempotency contract explicit and
 * prevents accidental per-call key minting from creating duplicate bookings.
 */
export async function createBooking(
  params: CreateBookingRequest,
  idempotencyKey: string,
): Promise<CreateBookingResult> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Idempotency-Key': idempotencyKey,
  };

  const token = getAuthToken();
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return apiClient<CreateBookingResult>('/api/public/bookings', {
    method: 'POST',
    headers,
    body: JSON.stringify({
      tour_slug: params.tour_slug,
      tour_date: params.tour_date,
      participant_count: params.participant_count,
      locale: params.locale || 'en',
      ...(params.page_load_price !== undefined && { page_load_price: params.page_load_price }),
    }),
  });
}

