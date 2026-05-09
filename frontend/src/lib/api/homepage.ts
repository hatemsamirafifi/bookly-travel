import { apiClient } from './client';
import type { HomepageData } from './types';

export async function getHomepageData(locale: string): Promise<HomepageData> {
  return apiClient<HomepageData>(
    `/api/public/homepage?locale=${encodeURIComponent(locale)}`,
    { locale }
  );
}
