import { cache } from 'react';
import { apiClient } from './client';
import type { PublicTourDeparture, TourDetail } from './types';

export interface TourDetailResponse {
  data: TourDetail;
}

export interface PublicTourDeparturesParams {
  date_from: string;
  date_to: string;
  option_id?: number;
  participants?: number;
}

// Request-scoped React cache: generateMetadata() and the page component share ONE backend call per request, with no cross-request caching.
// The fetch keeps `cache: 'no-store'`; in the client bundle React's cache is a pass-through, so BookingForm/useTour behavior is unchanged.
export const getTourDetail = cache(
  async (slug: string, locale: string): Promise<TourDetailResponse> =>
    apiClient<TourDetailResponse>(
      `/api/public/tours/${encodeURIComponent(slug)}?locale=${encodeURIComponent(locale)}`,
      { locale, cache: 'no-store' }
    )
);

export async function getPublicTourDepartures(
  slug: string,
  params: PublicTourDeparturesParams,
): Promise<{ data: PublicTourDeparture[] }> {
  const qs = new URLSearchParams({
    date_from: params.date_from,
    date_to: params.date_to,
  });
  if (params.option_id !== undefined) {
    qs.set('option_id', String(params.option_id));
  }
  if (params.participants !== undefined) {
    qs.set('participants', String(params.participants));
  }

  return apiClient<{ data: PublicTourDeparture[] }>(
    `/api/public/tours/${encodeURIComponent(slug)}/departures?${qs.toString()}`,
    { cache: 'no-store' }
  );
}
