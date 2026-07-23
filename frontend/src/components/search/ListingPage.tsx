import type { SearchResponse } from '@/lib/api/types';
import SearchResults from './SearchResults';
import Pagination from './Pagination';
import SortDropdown from './SortDropdown';

interface ListingPageProps {
  title: string;
  data: SearchResponse;
  locale: string;
}

/**
 * Shared layout for the category and destination listing pages (spec 006
 * reuse cleanup). Both pages were near-identical 68-line components differing
 * only in the page title and the data fetcher; the header + sort + results +
 * pagination shell is shared here, keeping the per-page file to its
 * metadata + fetch concerns.
 */
export default function ListingPage({ title, data, locale }: ListingPageProps) {
  const { total, current_page, last_page } = data.meta;

  return (
    <main className="min-h-screen bg-[#F7F9FB]">
      <div className="bg-white border-b border-gray-200 py-12">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h1 className="text-3xl font-bold text-[#0A2540]">{title}</h1>
          <p className="mt-2 text-[#5A6B7B]">
            {total} tour{total !== 1 ? 's' : ''} available
          </p>
        </div>
      </div>

      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="mb-6 flex justify-end">
          <SortDropdown />
        </div>
        <SearchResults tours={data.data} locale={locale} />
        <Pagination currentPage={current_page} lastPage={last_page} />
      </div>
    </main>
  );
}