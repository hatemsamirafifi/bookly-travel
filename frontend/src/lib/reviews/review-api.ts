import { apiClient } from '@/lib/api/client';
import { getAuthToken } from '@/lib/auth/token';
import type { PaginatedPartnerResponse, PartnerReview } from '@/types/partner';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '';

interface SubmitReviewPayload {
  booking_reference: string;
  rating: number;
  comment?: string;
  locale: string;
}

interface EditReviewPayload {
  rating: number;
  comment?: string;
}

interface SubmitReviewApiResponse {
  data: {
    id: number;
    reviewer_name: string;
    rating: number;
    comment?: string | null;
    status: string;
    edited: boolean;
    created_at: string;
    updated_at?: string;
  };
}

interface TourReviewsResponse {
  data: SubmitReviewApiResponse['data'][];
  meta: {
    average_rating: number;
    review_count: number;
    current_page: number;
    per_page: number;
    total: number;
  };
}

async function apiFetch(url: string, options?: RequestInit) {
  const token = getAuthToken();

  const res = await fetch(`${API_BASE}/api${url}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options?.headers,
    },
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    const message =
      body?.error?.message || body?.message || `Request failed with status ${res.status}`;
    const error = new Error(message);
    (error as Error & { status: number }).status = res.status;
    throw error;
  }

  return res.json();
}

export async function submitReview(payload: SubmitReviewPayload): Promise<SubmitReviewApiResponse> {
  return apiFetch('/public/reviews', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function editReview(reviewId: number, payload: EditReviewPayload): Promise<SubmitReviewApiResponse> {
  return apiFetch(`/public/reviews/${reviewId}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function fetchTourReviews(
  tourSlug: string,
  page = 1,
  perPage = 5,
): Promise<TourReviewsResponse> {
  return apiFetch(
    `/public/tours/${tourSlug}/reviews?page=${page}&per_page=${perPage}`,
  );
}

/* ─── Authenticated review APIs (partner & admin) ───────────────────────── */

function authHeaders(extra?: Record<string, string>) {
  const headers: Record<string, string> = { ...extra };
  const token = getAuthToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

export async function fetchPartnerReviews(tourId?: string, page = 1, perPage = 100) {
  const params = new URLSearchParams();
  params.set('page', String(page));
  params.set('per_page', String(perPage));
  if (tourId) params.set('tour_id', tourId);
  return apiClient<PaginatedPartnerResponse<PartnerReview>>(`/api/partner/reviews?${params}`, {
    headers: authHeaders(),
  });
}

export interface AdminReview {
  id: number;
  reviewer_name: string;
  rating: number;
  comment: string;
  status: 'visible' | 'hidden' | 'flagged';
  tour_id: number;
  tour_title: string;
  flagged: boolean;
  created_at: string;
  audit_trail?: {
    action: string;
    reason?: string;
    created_at: string;
    actor_name?: string;
  }[];
}

export interface AdminReviewsResponse {
  data: AdminReview[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export async function fetchAdminReviews(filters?: {
  status?: string;
  tour_id?: string;
  date_from?: string;
  date_to?: string;
  flagged?: boolean;
  page?: number;
}) {
  const params = new URLSearchParams();
  params.set('page', String(filters?.page ?? 1));
  params.set('per_page', '20');
  if (filters?.status) params.set('status', filters.status);
  if (filters?.tour_id) params.set('tour_id', filters.tour_id);
  if (filters?.date_from) params.set('date_from', filters.date_from);
  if (filters?.date_to) params.set('date_to', filters.date_to);
  if (filters?.flagged) params.set('flagged', '1');
  return apiClient<AdminReviewsResponse>(`/api/admin/reviews?${params}`, {
    headers: authHeaders(),
  });
}

export async function hideReview(id: number, reason: string) {
  return apiClient<{ message?: string }>(`/api/admin/reviews/${id}/hide`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ reason }),
  });
}

export async function reinstateReview(id: number, reason: string) {
  return apiClient<{ message?: string }>(`/api/admin/reviews/${id}/reinstate`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ reason }),
  });
}
