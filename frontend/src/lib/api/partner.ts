import { apiClient, getApiBaseUrl } from './client';
import { getAuthToken } from '@/lib/auth/token';
import type {
  PartnerTourWritePayload,
  TourDraftSnapshot,
  TourRevisionDetail,
  TourRevisionMutationData,
  TourRevisionPatchPayload,
  TourRevisionResponsePayload,
  PartnerApiResult,
  PartnerAvailabilityData,
  PartnerAvailabilityRule,
  PartnerAvailabilityException,
  PartnerDeparturesResponse,
  PartnerTourDeparture,
} from '@/lib/api/types';
import type {
  Tour,
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

export function createTour(tour: PartnerTourWritePayload) {
  return apiClient<{ data: Tour; draft?: Partial<TourDraftSnapshot> | null }>('/api/partner/tours', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({
      'Content-Type': 'application/json',
      ...(tour.creation_id ? { 'Client-Creation-Id': tour.creation_id } : {}),
    }),
    body: JSON.stringify(tour),
  });
}

export function updateTour(id: string | number, tour: PartnerTourWritePayload) {
  return apiClient<{ data: Tour }>(`/api/partner/tours/${encodeURIComponent(String(id))}`, {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(tour),
  });
}

export function submitTour(id: string | number) {
  return apiClient<{ data: Tour; message?: string }>(`/api/partner/tours/${encodeURIComponent(String(id))}/submit`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
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
  return apiClient<TourDraftSnapshot>(`/api/partner/tours/${encodeURIComponent(String(tourId))}/drafts/save`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ payload }),
  });
}

export function getLatestTourDraft(tourId: string | number) {
  return apiClient<TourDraftSnapshot>(`/api/partner/tours/${encodeURIComponent(String(tourId))}/drafts/latest`, {
    headers: authHeaders(),
  });
}

export function saveGeneralDraft(draft: {
  id?: number;
  creation_id?: string;
  tour_id?: number | null;
  source_locale?: string | null;
  current_step?: string;
  concurrency_token?: string | null;
  payload: Record<string, unknown>;
}) {
  return apiClient<{ data: TourDraftSnapshot }>('/api/partner/tours/drafts', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({
      'Content-Type': 'application/json',
      ...(draft.concurrency_token ? { 'If-Match': draft.concurrency_token } : {}),
    }),
    body: JSON.stringify(draft),
  });
}

export function getLatestGeneralDraft() {
  return apiClient<{ data: TourDraftSnapshot }>('/api/partner/tours/drafts/latest', {
    headers: authHeaders(),
  });
}

export function getGeneralDraft(draftId: number | string) {
  return apiClient<{ data: TourDraftSnapshot }>(`/api/partner/tours/drafts/${encodeURIComponent(String(draftId))}`, {
    headers: authHeaders(),
  });
}

export function updateGeneralDraft(
  draftId: number | string,
  draft: {
    creation_id?: string | null;
    tour_id?: number | null;
    source_locale?: string | null;
    current_step?: string;
    concurrency_token?: string | null;
    payload: Record<string, unknown>;
  }
) {
  return apiClient<{ data: TourDraftSnapshot }>(`/api/partner/tours/drafts/${encodeURIComponent(String(draftId))}`, {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({
      'Content-Type': 'application/json',
      ...(draft.concurrency_token ? { 'If-Match': draft.concurrency_token } : {}),
    }),
    body: JSON.stringify(draft),
  });
}

export function resumeGeneralDraft(draftId: number | string) {
  return apiClient<{ data: TourDraftSnapshot }>(`/api/partner/tours/drafts/${encodeURIComponent(String(draftId))}/resume`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

export function discardGeneralDraft(draftId: number | string) {
  return apiClient<{ message: string }>(`/api/partner/tours/drafts/${encodeURIComponent(String(draftId))}`, {
    method: 'DELETE',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

/* ─── Tour Content Revisions (EGM-003, Spec 019 partner contract) ────────── */

/**
 * Envelope bodies of the governed revision endpoints. The endpoints are
 * addressed by their machine error codes (REVISION_CONFLICT, …), which the
 * shared `apiClient` error classes do not carry, so these calls resolve the
 * documented HTTP shape through a non-throwing wrapper instead. Same auth
 * boundary (bearer + csrf-irrelevant JSON), same URLs, no new endpoints.
 */
export interface TourRevisionRequestBody<TResponse> {
  data: TResponse;
  message?: string;
}

export interface TourRevisionSubmittedData {
  id: number;
  revision_number: number;
  kind: string;
  state: string;
  payload_hash: string;
  base_revision_id: number;
}

export interface TourRevisionRespondedData {
  id: number;
  context: string;
  state: string;
  revision_id?: number | null;
  response_note?: string | null;
}

interface RawErrorBody {
  message?: string;
  error_code?: string;
  revision_id?: number;
  errors?: Record<string, string[]>;
}

function tourPath(tourId: string | number, suffix = ''): string {
  return `/api/partner/tours/${encodeURIComponent(String(tourId))}${suffix}`;
}

/** One governed revision HTTP call over the existing partner API boundary.
 *  Resolves with `{ ok, status, … }` and never throws, so every documented
 *  409/422/403/404 outcome reaches the fail-closed UI with its code. */
async function revisionRequest<TResponse>(
  path: string,
  method: 'GET' | 'POST' | 'PUT',
  body?: unknown
): Promise<PartnerApiResult<TResponse>> {
  let status = 0;
  try {
    const response = await fetch(`${getApiBaseUrl()}${path}`, {
      method,
      credentials: 'include',
      headers: authHeaders(method === 'GET' ? {} : { 'Content-Type': 'application/json' }),
      ...(body === undefined || method === 'GET' ? {} : { body: JSON.stringify(body) }),
    });
    status = response.status;
    const json: unknown = await response.json().catch(() => null);
    if (response.ok && typeof json === 'object' && json !== null && 'data' in json) {
      return {
        ok: true,
        status,
        data: (json as TourRevisionRequestBody<TResponse>).data,
        message: (json as TourRevisionRequestBody<TResponse>).message,
      };
    }
    const errorBody: RawErrorBody = (typeof json === 'object' && json !== null ? json : {}) as RawErrorBody;
    return {
      ok: false,
      status,
      message: errorBody.message,
      errorCode: errorBody.error_code,
      revisionId: errorBody.revision_id,
      errors: errorBody.errors,
    };
  } catch {
    // Network failure: no machine code exists; the UI keeps every entered
    // value and offers a retry (fail closed, never a silent fallback).
    return { ok: false, status, message: undefined, errorCode: 'NETWORK' };
  }
}

/** Read one owned revision including its full immutable payload. */
export function getTourRevision(tourId: string | number, revisionId: number | string) {
  return revisionRequest<TourRevisionDetail>(
    `${tourPath(tourId)}/revisions/${encodeURIComponent(String(revisionId))}`,
    'GET'
  );
}

/** Create an immutable candidate draft over the observed approved base. */
export function createTourRevision(tourId: string | number, patch: TourRevisionPatchPayload) {
  return revisionRequest<TourRevisionMutationData>(`${tourPath(tourId)}/revisions`, 'POST', patch);
}

/** Immutable replacement: never mutates the reviewed snapshot; the old id is
 *  superseded and a NEW id comes back. */
export function replaceTourRevision(tourId: string | number, revisionId: number | string, patch: TourRevisionPatchPayload) {
  return revisionRequest<TourRevisionMutationData>(
    `${tourPath(tourId)}/revisions/${encodeURIComponent(String(revisionId))}`,
    'PUT',
    patch
  );
}

/** Submit the owned current draft candidate for ordinary review. */
export function submitTourRevision(tourId: string | number, revisionId: number | string) {
  return revisionRequest<TourRevisionSubmittedData>(
    `${tourPath(tourId)}/revisions/${encodeURIComponent(String(revisionId))}/submit`,
    'POST'
  );
}

/** Withdraw the partner's own current draft/proposed candidate. */
export function withdrawTourRevision(tourId: string | number, revisionId: number | string) {
  return revisionRequest<TourRevisionSubmittedData>(
    `${tourPath(tourId)}/revisions/${encodeURIComponent(String(revisionId))}/withdraw`,
    'POST'
  );
}

/** Answer an open administrator change request: a published-context response
 *  links the exact submitted candidate; an initial-context response echoes
 *  the observed generation epoch. */
export function respondToTourChangeRequest(
  tourId: string | number,
  requestId: number | string,
  response: TourRevisionResponsePayload
) {
  return revisionRequest<TourRevisionRespondedData>(
    `${tourPath(tourId)}/revision-requests/${encodeURIComponent(String(requestId))}/respond`,
    'POST',
    response
  );
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

/* ─── Availability & Explicit Departures (EGM-005 D3, D4) ─────────────────── */

export function getPartnerAvailability(tourId: string | number) {
  return apiClient<{ data: PartnerAvailabilityData }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/availability`,
    { headers: authHeaders() }
  );
}

export function createPartnerAvailabilityRule(
  tourId: string | number,
  payload: {
    rule_type: 'recurring' | 'specific_date';
    days_of_week: number[] | null;
    start_time: string;
    start_date: string;
    end_date: string | null;
    capacity: number;
  }
) {
  return apiClient<{ data: PartnerAvailabilityRule; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/availability/rules`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function deletePartnerAvailabilityRule(tourId: string | number, ruleId: number) {
  return apiClient<{ message: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/availability/rules/${ruleId}`,
    {
      method: 'DELETE',
      requireCsrf: true,
      headers: authHeaders(),
    }
  );
}

export function createPartnerAvailabilityException(
  tourId: string | number,
  payload: {
    exception_type: 'block' | 'capacity_override' | 'price_multiplier';
    date: string;
    start_time: string | null;
    capacity: number | null;
    price_multiplier: number;
    note: string | null;
  }
) {
  return apiClient<{ data: PartnerAvailabilityException; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/availability/exceptions`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function deletePartnerAvailabilityException(tourId: string | number, exceptionId: number) {
  return apiClient<{ message: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/availability/exceptions/${exceptionId}`,
    {
      method: 'DELETE',
      requireCsrf: true,
      headers: authHeaders(),
    }
  );
}

export function getPartnerDepartures(
  tourId: string | number,
  filters?: {
    tour_option_id?: number | string;
    date_from?: string;
    date_to?: string;
    status?: string;
  }
) {
  const params = new URLSearchParams();
  if (filters?.tour_option_id) params.set('tour_option_id', String(filters.tour_option_id));
  if (filters?.date_from) params.set('date_from', filters.date_from);
  if (filters?.date_to) params.set('date_to', filters.date_to);
  if (filters?.status) params.set('status', filters.status);
  const qs = params.toString();

  return apiClient<PartnerDeparturesResponse>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/departures${qs ? `?${qs}` : ''}`,
    { headers: authHeaders() }
  );
}

export function createPartnerDeparture(
  tourId: string | number,
  payload: {
    tour_option_id: number;
    local_date: string;
    local_time: string;
    timezone: string;
    capacity: number;
    booking_cutoff_hours?: number;
  }
) {
  return apiClient<{ data: PartnerTourDeparture; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/departures`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function generatePartnerDepartures(
  tourId: string | number,
  payload: {
    start_date: string;
    end_date?: string | null;
    timezone: string;
    tour_option_id?: number | null;
  }
) {
  return apiClient<{
    data: PartnerTourDeparture[];
    meta: { generated_count: number };
    message?: string;
  }>(`/api/partner/tours/${encodeURIComponent(String(tourId))}/departures/generate`, {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
}

export function updatePartnerDeparture(
  tourId: string | number,
  departureId: number,
  payload: {
    capacity?: number;
    booking_cutoff_hours?: number;
  }
) {
  return apiClient<{ data: PartnerTourDeparture; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/departures/${departureId}`,
    {
      method: 'PUT',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function cancelPartnerDeparture(
  tourId: string | number,
  departureId: number,
  payload?: { confirm_booked_cancellation?: boolean }
) {
  return apiClient<{ data: PartnerTourDeparture; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/departures/${departureId}/cancel`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload ?? {}),
    }
  );
}

/* ─── Participant-Count Tiered Pricing (EGM-005 D5) ─────────────────────── */

import type { PartnerPricingResponse, PartnerPricingTierRecord } from './types';

export function getPartnerPricing(tourId: string | number, tourOptionId?: number | null) {
  const qs = new URLSearchParams();
  if (tourOptionId !== undefined) {
    qs.set('tour_option_id', tourOptionId === null ? 'null' : String(tourOptionId));
  }
  const suffix = qs.toString();
  return apiClient<PartnerPricingResponse>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/pricing${suffix ? `?${suffix}` : ''}`,
    { headers: authHeaders() }
  );
}

export function syncPartnerPricingTiers(
  tourId: string | number,
  payload: {
    tour_option_id: number;
    pricing_mode: 'per_person';
    tiers: Array<{
      name: string;
      min_participants: number;
      max_participants: number | null;
      price_amount: number;
      currency?: string;
    }>;
  }
) {
  return apiClient<{ data: PartnerPricingTierRecord[]; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/pricing/sync`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function createPartnerPricingTier(
  tourId: string | number,
  payload: {
    name: string;
    price_amount: number;
    min_participants: number;
    max_participants: number | null;
    tour_option_id?: number | null;
  }
) {
  return apiClient<{ data: PartnerPricingTierRecord; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/pricing`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function updatePartnerPricingTier(
  tourId: string | number,
  tierId: number,
  payload: {
    name?: string;
    price_amount?: number;
    min_participants?: number;
    max_participants?: number | null;
    tour_option_id?: number | null;
  }
) {
  return apiClient<{ data: PartnerPricingTierRecord; message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/pricing/${tierId}`,
    {
      method: 'PUT',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    }
  );
}

export function deletePartnerPricingTier(tourId: string | number, tierId: number) {
  return apiClient<{ message?: string }>(
    `/api/partner/tours/${encodeURIComponent(String(tourId))}/pricing/${tierId}`,
    {
      method: 'DELETE',
      requireCsrf: true,
      headers: authHeaders(),
    }
  );
}

