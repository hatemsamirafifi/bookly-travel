'use client';

import { useQuery } from '@tanstack/react-query';
import { getTourDetail } from '@/lib/api/tours';

export function useTour(slug: string, locale: string) {
  return useQuery({
    queryKey: ['tour', slug, locale],
    queryFn: () => getTourDetail(slug, locale).then((res) => res.data),
    staleTime: 300_000,
    retry: 1,
  });
}
