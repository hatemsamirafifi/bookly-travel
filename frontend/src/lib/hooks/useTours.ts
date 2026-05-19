'use client';

import { useQuery } from '@tanstack/react-query';
import { searchTours } from '@/lib/api/search';
import type { SearchParams } from '@/lib/api/types';

export function useTours(params: SearchParams) {
  return useQuery({
    queryKey: ['tours', params],
    queryFn: () => searchTours(params),
    staleTime: 30_000,
    retry: 1,
  });
}
