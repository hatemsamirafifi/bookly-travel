import type { Metadata } from 'next';
import { searchTours } from '@/lib/api/search';
import { RateLimitError } from '@/lib/api/client';
import { parseSort } from '@/lib/validators/search';
import { getTranslations } from 'next-intl/server';
import SearchBar from '@/components/search/SearchBar';
import SearchResults from '@/components/search/SearchResults';
import Pagination from '@/components/search/Pagination';
import FilterSidebar from '@/components/search/FilterSidebar';
import MobileFilterDrawer from '@/components/search/MobileFilterDrawer';
import ActiveFilterChips from '@/components/search/ActiveFilterChips';
import SortDropdown from '@/components/search/SortDropdown';
import SearchUnavailable from '@/components/search/SearchUnavailable';

interface SearchPageProps {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{
    q?: string;
    page?: string;
    category?: string;
    location?: string;
    price_min?: string;
    price_max?: string;
    duration?: string;
    date?: string;
    sort?: string;
  }>;
}

export async function generateMetadata({ params, searchParams }: SearchPageProps): Promise<Metadata> {
  const { locale } = await params;
  const { q } = await searchParams;
  const t = await getTranslations({ locale, namespace: 'search' });
  const title = q ? `${q} - ${t('pageTitle')} | Bookly` : `${t('pageTitle')} | Bookly`;
  const description = t('noResultsHint');

  return {
    title,
    description,
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

export default async function SearchPage({ params, searchParams }: SearchPageProps) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'search' });
  const sp = await searchParams;

  const currentPage = sp.page ? parseInt(sp.page, 10) : 1;
  const query = sp.q || '';

  let data;
  let error: string | null = null;
  let isServiceUnavailable = false;
  let retryAfterSeconds: number | undefined;

  try {
    data = await searchTours({
      q: query,
      locale,
      page: currentPage,
      category: sp.category,
      location: sp.location,
      price_min: sp.price_min ? parseInt(sp.price_min, 10) : undefined,
      price_max: sp.price_max ? parseInt(sp.price_max, 10) : undefined,
      duration: sp.duration,
      date: sp.date,
      sort: parseSort(sp.sort),
    });
  } catch (err) {
    if (err instanceof RateLimitError) {
      isServiceUnavailable = true;
      retryAfterSeconds = err.retryAfter;
    } else if (err && typeof err === 'object' && 'status' in err && (err as { status: number }).status === 503) {
      isServiceUnavailable = true;
    }
    error = t('loadError');
  }

  // Build lookup labels for ActiveFilterChips
  const categoryLabels: Record<string, string> = {};
  if (data?.filters?.categories) {
    data.filters.categories.forEach((c) => {
      categoryLabels[c.slug] = c.name;
    });
  }
  const locationLabels: Record<string, string> = {};
  if (data?.filters?.locations) {
    data.filters.locations.forEach((l) => {
      const key = l.slug ?? l.name;
      locationLabels[key] = l.name;
    });
  }

  return (
    <div className="min-h-screen bg-surface-alt py-8">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h1 className="sr-only">{t('pageTitle')}</h1>

        {/* Global Search Bar */}
        <div className="mb-8 flex justify-center">
          <SearchBar initialQuery={query} />
        </div>

        {error && isServiceUnavailable ? (
          <SearchUnavailable retryAfter={retryAfterSeconds} />
        ) : error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 p-8 text-center" role="alert">
            <p className="text-red-700 font-medium">{error}</p>
          </div>
        ) : data ? (
          <div className="flex flex-col gap-8 lg:flex-row">
            {/* Desktop Left Sidebar */}
            <FilterSidebar filterData={data.filters} />

            {/* Main Content Area */}
            <div className="flex-1 min-w-0">
              {/* Mobile trigger bar and sort controls */}
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <MobileFilterDrawer filterData={data.filters} totalResults={data.meta.total} />
                  {query ? (
                    <p className="text-sm font-medium text-text-muted">
                      {t(data.meta.total === 1 ? 'resultsFor' : 'resultsFor_plural', {
                        total: data.meta.total,
                        query,
                      })}
                    </p>
                  ) : (
                    <p className="text-sm font-medium text-text-muted">
                      {t(data.meta.total === 1 ? 'toursAvailable' : 'toursAvailable_plural', {
                        total: data.meta.total,
                      })}
                    </p>
                  )}
                </div>

                <div className="flex items-center gap-2">
                  <SortDropdown />
                </div>
              </div>

              {/* Active Filter Chips */}
              <ActiveFilterChips categoryLabels={categoryLabels} locationLabels={locationLabels} />

              {/* Tour Results Grid */}
              <SearchResults tours={data.data} locale={locale} />

              {/* Pagination */}
              <div className="mt-8">
                <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} />
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
