'use client';

import { useQuery } from '@tanstack/react-query';
import { getTravelerReviews } from '@/lib/api/traveler';

export function useMyReviews(page = 1) {
  return useQuery({
    queryKey: ['myReviews', page],
    queryFn: async () => {
      const res = await getTravelerReviews();
      return res.data;
    },
    staleTime: 60_000,
    retry: 1,
  });
}
