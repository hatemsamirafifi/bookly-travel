import { apiClient } from './client';
import type { PublicStaticPage } from './types';

/**
 * Fetch a published CMS static page (Admin 2.0 Phase G) in the given
 * platform locale.
 *
 * The public endpoint only exposes published pages: a 404 means no published
 * page with that slug exists and every error is degraded to `null`, so the
 * consumer can fall back gracefully (e.g. next-intl legal strings) whenever
 * the CMS page is missing or the API is unreachable.
 */
export async function fetchPublicStaticPage(
  slug: string,
  locale: string
): Promise<PublicStaticPage | null> {
  try {
    const response = await apiClient<{ data: PublicStaticPage }>(
      `/api/public/pages/${encodeURIComponent(slug)}?locale=${encodeURIComponent(locale)}`,
      {
        locale,
        cache: 'no-store',
      }
    );

    return response.data;
  } catch {
    return null;
  }
}
