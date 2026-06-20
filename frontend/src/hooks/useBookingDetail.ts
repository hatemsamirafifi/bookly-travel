'use client';

import { useQuery } from '@tanstack/react-query';
import { getBookingDetail } from '@/lib/api/my-bookings';

export function useBookingDetail(reference: string) {
  return useQuery({
    queryKey: ['bookingDetail', reference],
    queryFn: async () => {
      const res = await getBookingDetail(reference);
      return res.data;
    },
    staleTime: 60_000,
    retry: 1,
    enabled: Boolean(reference),
  });
}
