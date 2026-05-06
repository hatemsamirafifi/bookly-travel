import { apiClient } from './client';
import type { SearchParams, SearchResponse } from './types';

export async function searchTours(params: SearchParams): Promise<SearchResponse> {
  const searchParams = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '' && value !== null) {
      searchParams.set(key, String(value));
    }
  });

  return apiClient<SearchResponse>(
    `/api/public/search/tours?${searchParams.toString()}`,
    { locale: params.locale }
  );
}
