import type { TourCard as TourCardType } from '@/lib/api/types';
import Link from 'next/link';
import TourCard from './TourCard';

interface SearchResultsProps {
  tours: TourCardType[];
  locale: string;
  isLoading?: boolean;
}

function LoadingSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {[...Array(6)].map((_, i) => (
        <div key={i} className="animate-pulse overflow-hidden rounded-xl border border-gray-200 bg-white">
          <div className="aspect-[16/10] bg-gray-200" />
          <div className="p-4 space-y-3">
            <div className="h-3 w-2/3 rounded bg-gray-200" />
            <div className="h-5 w-full rounded bg-gray-200" />
            <div className="h-4 w-1/2 rounded bg-gray-200" />
            <div className="flex justify-between pt-3 border-t border-gray-100">
              <div className="h-5 w-16 rounded-full bg-gray-200" />
              <div className="h-6 w-20 rounded bg-gray-200" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

function EmptyState({ locale }: { locale: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <svg className="mb-4 h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <h3 className="mb-2 text-xl font-semibold text-gray-700">No tours found</h3>
      <p className="mb-6 text-gray-500">
        Try broadening your search terms or browse tours by category.
      </p>
      <Link
        href={`/${locale}/categories`}
        className="rounded-xl bg-[#FFB800] px-6 py-2.5 text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600] transition-colors"
      >
        Browse Categories
      </Link>
    </div>
  );
}

export default function SearchResults({ tours, locale, isLoading = false }: SearchResultsProps) {
  if (isLoading) {
    return <LoadingSkeleton />;
  }

  if (tours.length === 0) {
    return <EmptyState locale={locale} />;
  }

  return (
    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {tours.map((tour) => (
        <TourCard key={tour.id} tour={tour} locale={locale} />
      ))}
    </div>
  );
}
