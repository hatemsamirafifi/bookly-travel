import { apiClient } from './client';
import type {
  BlogCategoryResponse,
  BlogDetailResponse,
  BlogListResponse,
} from './types';

export async function getBlogPost(
  slug: string,
  locale: string
): Promise<BlogDetailResponse> {
  const params = new URLSearchParams({ locale });

  return apiClient<BlogDetailResponse>(
    `/api/public/blog/${encodeURIComponent(slug)}?${params.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );
}

export async function getBlogPosts(
  locale: string,
  params?: { category?: string; page?: number; per_page?: number }
): Promise<BlogListResponse> {
  const searchParams = new URLSearchParams({ locale });
  if (params?.category) {
    searchParams.set('category', params.category);
  }
  if (params?.page) {
    searchParams.set('page', params.page.toString());
  }
  if (params?.per_page) {
    searchParams.set('per_page', params.per_page.toString());
  }

  return apiClient<BlogListResponse>(
    `/api/public/blog?${searchParams.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );
}

export async function getBlogCategory(
  slug: string,
  locale: string,
  params?: { page?: number; per_page?: number }
): Promise<BlogCategoryResponse> {
  const searchParams = new URLSearchParams({ locale });
  if (params?.page) {
    searchParams.set('page', params.page.toString());
  }
  if (params?.per_page) {
    searchParams.set('per_page', params.per_page.toString());
  }

  return apiClient<BlogCategoryResponse>(
    `/api/public/blog/category/${encodeURIComponent(slug)}?${searchParams.toString()}`,
    {
      locale,
      cache: 'no-store',
    }
  );
}

/**
 * Fetch a blog post preview via signed HMAC token (no caching)
 */
export async function getBlogPostPreview(
  slug: string,
  token: string,
  locale: string = 'en'
): Promise<BlogDetailResponse> {
  return apiClient<BlogDetailResponse>(
    `/api/public/blog/${encodeURIComponent(slug)}/preview?token=${encodeURIComponent(token)}&locale=${encodeURIComponent(locale)}`,
    {
      locale,
      cache: 'no-store',
    }
  );
}