import { apiClient, buildSearchParams } from './client';
import type { SearchParams, SearchResponse } from './types';

export async function searchTours(params: SearchParams): Promise<SearchResponse> {
  const searchParams = buildSearchParams(params);

  return apiClient<SearchResponse>(
    `/api/public/search/tours?${searchParams.toString()}`,
    { locale: params.locale, revalidate: 300 }
  );
}