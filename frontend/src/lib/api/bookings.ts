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
 * RFC 4122 v4 UUID for the Idempotency-Key header (backend validates UUID
 * format). `crypto.randomUUID()` is only exposed in secure contexts (HTTPS or
 * localhost) — over plain HTTP (the in-container E2E browses http://nginx, and
 * any non-localhost HTTP deploy is the same) it is `undefined` and calling it
 * throws a TypeError, which would abort booking submission before the request
 * is even sent. Fall back to `crypto.getRandomValues` (available in insecure
 * contexts), and finally to Math.random.
 */
function uuidv4(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 10
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

export async function createBooking(params: CreateBookingRequest): Promise<CreateBookingResult> {
  const idempotencyKey = uuidv4();

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

