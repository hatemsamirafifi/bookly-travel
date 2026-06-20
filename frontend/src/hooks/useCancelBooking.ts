'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { cancelBooking } from '@/lib/api/my-bookings';

export function useCancelBooking(reference: string, locale?: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const res = await cancelBooking(reference);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookingDetail', reference] });
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
      if (locale) {
        queryClient.invalidateQueries({ queryKey: ['bookings', locale] });
      }
    },
  });
}
