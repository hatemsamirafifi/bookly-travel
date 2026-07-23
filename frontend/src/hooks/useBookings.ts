'use client';

import { useQuery } from '@tanstack/react-query';
import { getMyBookings, getMyBookingsSummary } from '@/lib/api/my-bookings';

/**
 * Fetch the traveler's bookings for a given status filter and page.
 *
 * F14: `page` is part of the query key so changing pages (or filters) refetches
 * automatically; the hook now returns `meta` (current_page / last_page / total)
 * so the list can render pagination controls. The summary is fetched in
 * parallel and is page-independent.
 */
export function useBookings(status?: string, locale?: string, page = 1) {
  return useQuery({
    queryKey: ['bookings', locale, status || 'all', page],
    queryFn: async () => {
      const [bookings, summary] = await Promise.all([
        getMyBookings(status || undefined, page),
        getMyBookingsSummary(),
      ]);
      return {
        bookings: bookings.data,
        meta: bookings.meta,
        summary: summary.data,
      };
    },
    staleTime: 60_000,
    retry: 1,
  });
}