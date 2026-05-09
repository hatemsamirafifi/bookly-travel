import { apiClient } from './client';
import type { BookingResponse } from './bookings';

interface BookingsListResponse {
  data: BookingResponse[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export async function getMyBookings(page = 1, status?: string): Promise<BookingsListResponse> {
  const params = new URLSearchParams();
  params.set('page', String(page));
  if (status) params.set('status', status);

  const token = localStorage.getItem('auth_token');
  const headers: Record<string, string> = {};
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return apiClient<BookingsListResponse>(`/api/public/my-bookings?${params.toString()}`, {
    headers,
  });
}

export async function getBookingDetail(reference: string): Promise<{ data: BookingResponse }> {
  const token = localStorage.getItem('auth_token');
  const headers: Record<string, string> = {};
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return apiClient<{ data: BookingResponse }>(`/api/public/my-bookings/${encodeURIComponent(reference)}`, {
    headers,
  });
}

export async function cancelBooking(reference: string): Promise<{ data: BookingResponse; message: string }> {
  const token = localStorage.getItem('auth_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return apiClient<{ data: BookingResponse; message: string }>(
    `/api/public/my-bookings/${encodeURIComponent(reference)}/cancel`,
    { method: 'POST', headers }
  );
}
