'use client';

import { useCallback, useMemo } from 'react';
import { useRouter, useSearchParams, useParams, usePathname } from 'next/navigation';

export interface FilterState {
  q?: string;
  category?: string;
  location?: string;
  price_min?: string;
  price_max?: string;
  duration?: string;
  date?: string;
  sort?: string;
  page?: string;
}

export function useFilters() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useParams();
  const pathname = usePathname();
  const locale = (params?.locale as string) || 'en';

  // Derive the navigation path from the current pathname so the shared filter
  // controls work on any listing page (search, categories/[slug],
  // destinations/[slug]) instead of always jumping back to /search.
  const basePath = pathname ?? `/${locale}/search`;

  const filters = useMemo<FilterState>(() => {
    const sp = new URLSearchParams(searchParams.toString());
    const result: FilterState = {};
    if (sp.has('q')) result.q = sp.get('q')!;
    if (sp.has('category')) result.category = sp.get('category')!;
    if (sp.has('location')) result.location = sp.get('location')!;
    if (sp.has('price_min')) result.price_min = sp.get('price_min')!;
    if (sp.has('price_max')) result.price_max = sp.get('price_max')!;
    if (sp.has('duration')) result.duration = sp.get('duration')!;
    if (sp.has('date')) result.date = sp.get('date')!;
    if (sp.has('sort')) result.sort = sp.get('sort')!;
    if (sp.has('page')) result.page = sp.get('page')!;
    return result;
  }, [searchParams]);

  const activeFilterCount = useMemo(() => {
    let count = 0;
    if (filters.category) count++;
    if (filters.location) count++;
    if (filters.price_min || filters.price_max) count++;
    if (filters.duration) count++;
    if (filters.date) count++;
    return count;
  }, [filters]);

  const setFilter = useCallback(
    (key: keyof FilterState, value: string | null) => {
      const sp = new URLSearchParams(searchParams.toString());
      if (value) {
        sp.set(key, value);
      } else {
        sp.delete(key);
      }
      // Reset page to 1 when changing filters
      if (key !== 'page') {
        sp.delete('page');
      }
      const qs = sp.toString();
      router.push(`${basePath}${qs ? `?${qs}` : ''}`, { scroll: false });
    },
    [searchParams, basePath, router]
  );

  const setMultipleFilters = useCallback(
    (updates: Partial<FilterState>) => {
      const sp = new URLSearchParams(searchParams.toString());
      for (const [key, value] of Object.entries(updates)) {
        if (value) {
          sp.set(key, value);
        } else {
          sp.delete(key);
        }
      }
      sp.delete('page');
      const qs = sp.toString();
      router.push(`${basePath}${qs ? `?${qs}` : ''}`, { scroll: false });
    },
    [searchParams, basePath, router]
  );

  const clearAll = useCallback(() => {
    router.push(basePath, { scroll: false });
  }, [basePath, router]);

  return { filters, activeFilterCount, setFilter, setMultipleFilters, clearAll };
}
