import type { Metadata } from 'next';
import { searchTours } from '@/lib/api/search';
import { RateLimitError } from '@/lib/api/client';
import { parseSort } from '@/lib/validators/search';
import SearchBar from '@/components/search/SearchBar';
import SearchResults from '@/components/search/SearchResults';
import Pagination from '@/components/search/Pagination';
import FilterPanel from '@/components/search/FilterPanel';
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

export async function generateMetadata({ searchParams }: SearchPageProps): Promise<Metadata> {
  const { q } = await searchParams;
  const title = q ? `${q} - Search Results | Bookly` : 'Search Tours | Bookly';
  const description = q
    ? `Search results for "${q}". Find and book the best tours worldwide.`
    : 'Search and discover amazing tours worldwide. Filter by category, location, price, and more.';

  return {
    title,
    description,
    robots: { index: true, follow: true },
    openGraph: { title, description, type: 'website' },
  };
}

export default async function SearchPage({ params, searchParams }: SearchPageProps) {
  const { locale } = await params;
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
    error = 'Failed to load search results. Please try again.';
  }

  return (
    <div className="min-h-screen bg-[#F7F9FB]">
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 className="sr-only">Search Tours</h1>
        <div className="mb-8 flex justify-center">
          <SearchBar initialQuery={query} />
        </div>

        {error && isServiceUnavailable ? (
          <SearchUnavailable retryAfter={retryAfterSeconds} />
        ) : error ? (
          <div className="rounded-lg border border-red-200 bg-red-50 p-8 text-center" role="alert">
            <p className="text-red-600">{error}</p>
          </div>
        ) : data ? (
          <div className="flex flex-col gap-6 lg:flex-row">
            {/* Sidebar filters */}
            <div className="shrink-0">
              <FilterPanel filterData={data.filters} />
            </div>

            {/* Results */}
            <div className="flex-1 min-w-0">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                {query && (
                  <p className="text-sm text-gray-500">
                    {data.meta.total} result{data.meta.total !== 1 ? 's' : ''} for &quot;{query}&quot;
                  </p>
                )}
                {!query && (
                  <p className="text-sm text-gray-500">
                    {data.meta.total} tour{data.meta.total !== 1 ? 's' : ''} available
                  </p>
                )}
                <SortDropdown />
              </div>

              <SearchResults tours={data.data} locale={locale} />
              <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} />
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
