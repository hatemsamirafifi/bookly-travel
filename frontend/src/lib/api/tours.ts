import { cache } from 'react';
import { apiClient } from './client';
import type { TourDetail } from './types';

export interface TourDetailResponse {
  data: TourDetail;
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
