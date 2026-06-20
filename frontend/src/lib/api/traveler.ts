import { apiClient } from './client';
import { getAuthToken } from '@/lib/auth/token';
import type {
  PaginatedTravelerResponse,
  TravelerBooking,
  TravelerProfile,
  TravelerReview,
  WishlistItem,
} from '@/types/traveler';

function authHeaders(extra?: Record<string, string>) {
  const headers: Record<string, string> = { ...extra };
  const token = getAuthToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

export interface BookingSummary {
  data: {
    total: number;
    confirmed: number;
    completed: number;
    cancelled: number;
    no_show: number;
  };
}

export function getTravelerBookingsSummary() {
  return apiClient<BookingSummary>('/api/public/traveler/bookings/summary', {
    headers: authHeaders(),
  });
}

export function getTravelerBookings(status?: string, page = 1) {
  const params = new URLSearchParams({ page: String(page), per_page: '10' });
  if (status) params.set('status', status);
  return apiClient<PaginatedTravelerResponse<TravelerBooking>>(`/api/public/traveler/bookings?${params}`, {
    headers: authHeaders(),
  });
}

export function getTravelerBooking(reference: string) {
  return apiClient<{ data: TravelerBooking }>(`/api/public/traveler/bookings/${encodeURIComponent(reference)}`, {
    headers: authHeaders(),
  });
}

export function cancelTravelerBooking(reference: string, reason?: string) {
  return apiClient<{ data: Partial<TravelerBooking>; message?: string }>(
    `/api/public/traveler/bookings/${encodeURIComponent(reference)}/cancel`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ reason: reason || 'Traveler requested cancellation' }),
    }
  );
}

export function getTravelerProfile() {
  return apiClient<{ data: TravelerProfile }>('/api/public/traveler/profile', {
    headers: authHeaders(),
  });
}

export function updateTravelerProfile(profile: Omit<TravelerProfile, 'id' | 'email' | 'avatar_url'>) {
  return apiClient<{ data: TravelerProfile }>('/api/public/traveler/profile', {
    method: 'PUT',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(profile),
  });
}

export function changeTravelerPassword(data: {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}) {
  return apiClient<{ message: string }>('/api/public/traveler/profile/change-password', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(data),
  });
}

export function getTravelerWishlist(page = 1) {
  return apiClient<PaginatedTravelerResponse<WishlistItem>>(`/api/public/traveler/wishlist?page=${page}&per_page=12`, {
    headers: authHeaders(),
  });
}

export function addTravelerWishlistItem(tourId: string | number) {
  return apiClient<void>('/api/public/traveler/wishlist', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ tour_id: tourId }),
  });
}

export function removeTravelerWishlistItem(tourId: string | number) {
  return apiClient<void>(`/api/public/traveler/wishlist/${encodeURIComponent(String(tourId))}`, {
    method: 'DELETE',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

export function getTravelerReviews(page = 1) {
  return apiClient<PaginatedTravelerResponse<TravelerReview>>(`/api/public/traveler/reviews?page=${page}&per_page=10`, {
    headers: authHeaders(),
  });
}

export async function downloadTravelerBookingVoucher(reference: string): Promise<void> {
  const token = getAuthToken();
  const response = await fetch(`/api/public/traveler/bookings/${encodeURIComponent(reference)}/voucher`, {
    headers: {
      Authorization: token ? `Bearer ${token}` : '',
    },
  });
  if (!response.ok) {
    throw new Error('Failed to download voucher');
  }
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `voucher-${reference}.pdf`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
}

