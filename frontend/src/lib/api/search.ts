import { apiClient, buildSearchParams } from './client';
import type { SearchParams, SearchResponse } from './types';

/**
 * Convert a user-entered major-unit price (e.g. euros, as shown in the UI
 * and carried in the URL) to the search API contract's minor units (cents).
 * Returns undefined for missing/non-numeric/negative input so the filter
 * is omitted rather than sent as a bogus bound.
 */
export function toMinorUnits(value: number | undefined): number | undefined {
  if (value === undefined || !Number.isFinite(value) || value < 0) {
    return undefined;
  }
  return Math.round(value * 100);
}

export async function searchTours(params: SearchParams): Promise<SearchResponse> {
  // The API contract prices filters in minor units (cents) while the UI and
  // URLs work in major units; convert at this boundary so every caller
  // (search page, category/destination listings, useTours) stays correct.
  const searchParams = buildSearchParams({
    ...params,
    price_min: toMinorUnits(params.price_min),
    price_max: toMinorUnits(params.price_max),
  });

  return apiClient<SearchResponse>(
    `/api/public/search/tours?${searchParams.toString()}`,
    { locale: params.locale, revalidate: 300 }
  );
}