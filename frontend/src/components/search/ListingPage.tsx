import type { SearchResponse } from '@/lib/api/types';
import SearchResults from './SearchResults';
import Pagination from './Pagination';
import SortDropdown from './SortDropdown';
import { getTranslations } from 'next-intl/server';

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
export default async function ListingPage({ title, data, locale }: ListingPageProps) {
  const { total, current_page, last_page } = data.meta;
  const t = await getTranslations({ locale, namespace: 'search' });

  return (
    <div className="min-h-screen bg-surface-alt">
      <div className="border-b border-border bg-surface py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h1 className="max-w-3xl text-3xl font-bold tracking-tight text-bookly-navy sm:text-4xl">{title}</h1>
          <p className="mt-3 text-text-muted">
            {t(total === 1 ? 'toursAvailable' : 'toursAvailable_plural', { total })}
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
    </div>
  );
}
