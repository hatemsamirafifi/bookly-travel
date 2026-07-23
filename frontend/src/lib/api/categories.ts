import { apiClient, buildSearchParams } from './client';
import type { Category, SearchParams, SearchResponse } from './types';

export interface CategoryListResponse {
  data: Category[];
}

export async function getCategories(locale: string): Promise<CategoryListResponse> {
  // `locale` is required by the backend LocaleRequest (validation) and used as
  // Accept-Language by the rate-limit middleware for localized 429 messages.
  return apiClient<CategoryListResponse>(
    `/api/public/categories?locale=${encodeURIComponent(locale)}`,
    { locale, revalidate: 300 }
  );
}

export async function getCategoryTours(
  slug: string,
  params: Partial<SearchParams>
): Promise<SearchResponse> {
  // `category` is omitted — the slug in the path is the scope (the backend
  // CategoryToursRequest prohibits a `category` query param).
  const sp = buildSearchParams(params, ['category']);

  return apiClient<SearchResponse>(
    `/api/public/categories/${encodeURIComponent(slug)}/tours?${sp.toString()}`,
    { locale: params.locale, revalidate: 300 }
  );
}