'use client';

import { useQuery } from '@tanstack/react-query';
import { getMyBookings, getMyBookingsSummary } from '@/lib/api/my-bookings';

export function useBookings(status?: string, locale?: string) {
  return useQuery({
    queryKey: ['bookings', locale, status || 'all'],
    queryFn: async () => {
      const [bookings, summary] = await Promise.all([
        getMyBookings(status || undefined, 1),
        getMyBookingsSummary(),
      ]);
      return {
        bookings: bookings.data,
        summary: summary.data,
      };
    },
    staleTime: 60_000,
    retry: 1,
  });
}
