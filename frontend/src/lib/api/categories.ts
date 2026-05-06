import { apiClient } from './client';
import type { Category, SearchParams, SearchResponse } from './types';

export interface CategoryListResponse {
  data: Category[];
}

export async function getCategories(): Promise<CategoryListResponse> {
  return apiClient<CategoryListResponse>('/api/public/categories');
}

export async function getCategoryTours(
  slug: string,
  params: Partial<SearchParams>
): Promise<SearchResponse> {
  const sp = new URLSearchParams();
  if (params.locale) sp.set('locale', params.locale);
  if (params.q) sp.set('q', params.q);
  if (params.price_min) sp.set('price_min', String(params.price_min));
  if (params.price_max) sp.set('price_max', String(params.price_max));
  if (params.duration) sp.set('duration', params.duration);
  if (params.date) sp.set('date', params.date);
  if (params.sort) sp.set('sort', params.sort);
  if (params.page) sp.set('page', String(params.page));

  return apiClient<SearchResponse>(
    `/api/public/categories/${encodeURIComponent(slug)}/tours?${sp.toString()}`,
    { locale: params.locale }
  );
}
