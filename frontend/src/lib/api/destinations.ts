import { apiClient, buildSearchParams } from './client';
import type { Destination, SearchParams, SearchResponse } from './types';

export interface DestinationListResponse {
  data: Destination[];
}

export async function getDestinations(locale: string): Promise<DestinationListResponse> {
  // `locale` is required by the backend LocaleRequest (validation) and used as
  // Accept-Language by the rate-limit middleware for localized 429 messages.
  return apiClient<DestinationListResponse>(
    `/api/public/destinations?locale=${encodeURIComponent(locale)}`,
    { locale, revalidate: 300 }
  );
}

export async function getDestinationTours(
  slug: string,
  params: Partial<SearchParams>
): Promise<SearchResponse> {
  // `location` is omitted — the slug in the path is the scope (the backend
  // DestinationToursRequest prohibits a `location` query param).
  const sp = buildSearchParams(params, ['location']);

  return apiClient<SearchResponse>(
    `/api/public/destinations/${encodeURIComponent(slug)}/tours?${sp.toString()}`,
    { locale: params.locale, revalidate: 300 }
  );
}