import { apiClient } from './client';
import type {
  PaginatedTravelerResponse,
  TravelerBooking,
  TravelerProfile,
  TravelerReview,
  WishlistItem,
} from '@/types/traveler';

function authHeaders(extra?: Record<string, string>) {
  const headers: Record<string, string> = { ...extra };
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('auth_token');
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }
  return headers;
}

export function getTravelerBookings(status?: string, page = 1) {
  const params = new URLSearchParams({ page: String(page), per_page: '10' });
  if (status) params.set('status', status);
  return apiClient<PaginatedTravelerResponse<TravelerBooking>>(`/traveler/bookings?${params}`, {
    headers: authHeaders(),
  });
}

export function getTravelerBooking(reference: string) {
  return apiClient<{ data: TravelerBooking }>(`/traveler/bookings/${encodeURIComponent(reference)}`, {
    headers: authHeaders(),
  });
}

export function cancelTravelerBooking(reference: string, reason?: string) {
  return apiClient<{ data: Partial<TravelerBooking>; message?: string }>(
    `/traveler/bookings/${encodeURIComponent(reference)}/cancel`,
    {
      method: 'POST',
      requireCsrf: true,
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ reason: reason || 'Traveler requested cancellation' }),
    }
  );
}

export function getTravelerProfile() {
  return apiClient<{ data: TravelerProfile }>('/traveler/profile', {
    headers: authHeaders(),
  });
}

export function updateTravelerProfile(profile: Omit<TravelerProfile, 'id' | 'email' | 'avatar_url'>) {
  return apiClient<{ data: TravelerProfile }>('/traveler/profile', {
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
  return apiClient<{ message: string }>('/traveler/profile/change-password', {
    method: 'POST',
    requireCsrf: true,
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(data),
  });
}

export function getTravelerWishlist(page = 1) {
  return apiClient<PaginatedTravelerResponse<WishlistItem>>(`/traveler/wishlist?page=${page}&per_page=12`, {
    headers: authHeaders(),
  });
}

export function removeTravelerWishlistItem(tourId: string | number) {
  return apiClient<void>(`/traveler/wishlist/${encodeURIComponent(String(tourId))}`, {
    method: 'DELETE',
    requireCsrf: true,
    headers: authHeaders(),
  });
}

export function getTravelerReviews(page = 1) {
  return apiClient<PaginatedTravelerResponse<TravelerReview>>(`/traveler/reviews?page=${page}&per_page=10`, {
    headers: authHeaders(),
  });
}
