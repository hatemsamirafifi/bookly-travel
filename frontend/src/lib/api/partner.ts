import { apiClient } from './client';
import { getAuthToken } from '@/lib/auth/token';
import type {
  Tour,
  TourDraft,
  PartnerBooking,
  PartnerReview,
  ReviewResponse,
  Notification,
  PartnerProfile,
  PartnerSettings,
  AnalyticsResponse,
  DateRangeFilter,
  PaginatedPartnerResponse,
  SignedUploadUrl,
  PartnerRegistrationPayload,
  PartnerRegistrationResponse,
  PartnerOnboardingStatus,
  ResubmitPayload,
  InviteValidationResponse,
  InviteCompletionPayload,
} from '@/types/partner';

function authHeaders(extra?: Record<string, string>) {
  const headers: Record<string, string> = { ...extra };
  const token = getAuthToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

/* ─── Tours ─────────────────────────────────────────────────────────────── */

export function getTours(status?: string, page = 1) {
  const params = new URLSearchParams({ page: String(page), per_page: '12' });
  if (status) params.set('status', status);
  return apiClient<PaginatedPartnerResponse<Tour>>(`/api/partner/tours?${params}`, {
    headers: authHeaders(),
  });
}

export function getTour(id: string | number) {
  return apiClient<{ data: Tour }>(`/api/partner/tours/${encodeURIComponent(String(id))}`, {
    headers: authHeaders(),
  });
}

export function createTour(tour: Omit<Tour, 'id' | 'partner_id' | 'created_at' | 'updated_at' | 'published_at'>) {
  return apiClient<{ data: Tour }>('/api/partner/tours', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(tour),
  });
}

export function updateTour(id: string | number, tour: Partial<Tour>) {
  return apiClient<{ data: Tour }>(`/api/partner/tours/${encodeURIComponent(String(id))}`, {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(tour),
  });
}

export function deleteTour(id: string | number) {
  return apiClient<void>(`/api/partner/tours/${encodeURIComponent(String(id))}`, {
    method: 'DELETE',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

export function archiveTour(id: string | number) {
  return apiClient<{ data: Tour }>(`/api/partner/tours/${encodeURIComponent(String(id))}/archive`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
  });
}

/* ─── Tour Drafts ───────────────────────────────────────────────────────── */

export function saveTourDraft(tourId: string | number, payload: Record<string, unknown>) {
  return apiClient<TourDraft>(`/api/partner/tours/${encodeURIComponent(String(tourId))}/drafts/save`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ payload }),
  });
}

export function getLatestTourDraft(tourId: string | number) {
  return apiClient<TourDraft>(`/api/partner/tours/${encodeURIComponent(String(tourId))}/drafts/latest`, {
    headers: authHeaders(),
  });
}

/* ─── Bookings ──────────────────────────────────────────────────────────── */

export function getBookings(filters?: {
  status?: string;
  tour_id?: string | number;
  date_from?: string;
  date_to?: string;
  search?: string;
  page?: number;
}) {
  const params = new URLSearchParams();
  params.set('page', String(filters?.page ?? 1));
  params.set('per_page', '20');
  if (filters?.status) params.set('status', filters.status);
  if (filters?.tour_id) params.set('tour_id', String(filters.tour_id));
  if (filters?.date_from) params.set('date_from', filters.date_from);
  if (filters?.date_to) params.set('date_to', filters.date_to);
  if (filters?.search) params.set('search', filters.search);
  return apiClient<PaginatedPartnerResponse<PartnerBooking>>(`/api/partner/bookings?${params}`, {
    headers: authHeaders(),
  });
}

export function getBooking(reference: string) {
  return apiClient<{ data: PartnerBooking }>(`/api/partner/bookings/${encodeURIComponent(reference)}`, {
    headers: authHeaders(),
  });
}

export function updateBookingStatus(reference: string, status: 'completed') {
  return apiClient<{ data: PartnerBooking }>(`/api/partner/bookings/${encodeURIComponent(reference)}/status`, {
    method: 'PATCH',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ status }),
  });
}

export function requestBookingCancellation(reference: string, reason: string, evidence?: string[]) {
  return apiClient<{ message?: string; reference?: string }>(
    `/api/partner/bookings/${encodeURIComponent(reference)}/cancellation-request`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ reason, evidence }),
    }
  );
}

/* ─── Reviews ───────────────────────────────────────────────────────────── */

export function getReviews(page = 1) {
  return apiClient<PaginatedPartnerResponse<PartnerReview>>(
    `/api/partner/reviews?page=${page}&per_page=10`,
    {
      headers: authHeaders(),
    }
  );
}

export function respondToReview(reviewId: string | number, text: string) {
  return apiClient<{ data: ReviewResponse }>(`/api/partner/reviews/${encodeURIComponent(String(reviewId))}/responses`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ response_text: text }),
  });
}

export function updateReviewResponse(reviewId: string | number, text: string) {
  return apiClient<{ data: ReviewResponse }>(`/api/partner/reviews/${encodeURIComponent(String(reviewId))}/responses`, {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ response_text: text }),
  });
}

/* ─── Notifications ─────────────────────────────────────────────────────── */

export function getNotifications(page = 1, unreadOnly = false) {
  const params = new URLSearchParams();
  params.set('page', String(page));
  params.set('per_page', '20');
  if (unreadOnly) params.set('unread_only', '1');
  return apiClient<PaginatedPartnerResponse<Notification>>(`/api/partner/notifications?${params}`, {
    headers: authHeaders(),
  });
}

export function markNotificationAsRead(id: string | number) {
  return apiClient<void>(`/api/partner/notifications/${encodeURIComponent(String(id))}/read`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

export function markAllNotificationsAsRead() {
  return apiClient<void>('/api/partner/notifications/read-all', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

/* ─── Analytics ─────────────────────────────────────────────────────────── */

export function getAnalytics(range?: DateRangeFilter, tourId?: string | number) {
  const params = new URLSearchParams();
  if (range) {
    params.set('from', range.from);
    params.set('to', range.to);
  }
  if (tourId) params.set('tour_id', String(tourId));
  const qs = params.toString();
  return apiClient<AnalyticsResponse>(`/api/partner/analytics${qs ? `?${qs}` : ''}`, {
    headers: authHeaders(),
  });
}

/* ─── Profile ─────────────────────────────────────────────────────────── */

export function getProfile() {
  return apiClient<{ data: PartnerProfile }>('/api/partner/profile', {
    headers: authHeaders(),
  });
}

export function updateProfile(profile: Partial<Omit<PartnerProfile, 'id' | 'created_at' | 'updated_at'>>) {
  return apiClient<{ data: PartnerProfile }>('/api/partner/profile', {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(profile),
  });
}

export function getPartnerSettings() {
  return apiClient<{ data: PartnerSettings }>('/api/partner/settings', {
    headers: authHeaders(),
  });
}

export function updatePartnerSettings(settings: Partial<PartnerSettings>) {
  return apiClient<{ data: PartnerSettings }>('/api/partner/settings', {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(settings),
  });
}

/* ─── Uploads ───────────────────────────────────────────────────────────── */

export function getSignedUploadUrl(
  fileType: string,
  fileSize: number
) {
  return apiClient<SignedUploadUrl>('/api/partner/uploads/signed-url', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ file_type: fileType, file_size: fileSize }),
  });
}

/* ─── Onboarding & Registration (Spec 015) ────────────────────────────────── */

export function registerPartner(payload: PartnerRegistrationPayload) {
  return apiClient<PartnerRegistrationResponse>('/api/public/auth/partners/register', {
    method: 'POST',
    requireCsrf: true,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}

export function getOnboardingStatus() {
  return apiClient<{ data: PartnerOnboardingStatus }>('/api/partner/onboarding/status', {
    headers: authHeaders(),
  });
}

export function resubmitApplication(payload: ResubmitPayload) {
  return apiClient<{ data: PartnerProfile }>('/api/partner/onboarding/resubmit', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
}

export function validateInviteToken(token: string) {
  return apiClient<{ data: InviteValidationResponse }>(`/api/public/auth/partners/invitation/${encodeURIComponent(token)}`);
}

export function completeInvite(token: string, payload: InviteCompletionPayload) {
  return apiClient<PartnerRegistrationResponse>(`/api/public/auth/partners/invitation/${encodeURIComponent(token)}/complete`, {
    method: 'POST',
    requireCsrf: true,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}

