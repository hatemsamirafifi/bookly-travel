import { apiClient } from './client';
import type { TourDetail } from './types';

export interface TourDetailResponse {
  data: TourDetail;
}

export async function getTourDetail(slug: string, locale: string): Promise<TourDetailResponse> {
  return apiClient<TourDetailResponse>(
    `/api/public/tours/${encodeURIComponent(slug)}?locale=${encodeURIComponent(locale)}`,
    { locale }
  );
}
