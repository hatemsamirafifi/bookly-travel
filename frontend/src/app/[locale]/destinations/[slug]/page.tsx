import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getDestinationTours } from '@/lib/api/destinations';
import { NotFoundError } from '@/lib/api/client';
import type { SearchParams } from '@/lib/api/types';
import SearchResults from '@/components/search/SearchResults';
import Pagination from '@/components/search/Pagination';
import SortDropdown from '@/components/search/SortDropdown';

interface DestinationPageProps {
  params: Promise<{ locale: string; slug: string }>;
  searchParams: Promise<{ page?: string; sort?: string; category?: string; price_min?: string; price_max?: string; duration?: string }>;
}

export async function generateMetadata({ params }: DestinationPageProps): Promise<Metadata> {
  const { locale, slug } = await params;
  const name = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

  return {
    title: `Tours in ${name} | Bookly`,
    description: `Discover and book the best tours in ${name}. Browse experiences across top locations.`,
    robots: { index: true, follow: true },
  };
}

export default async function DestinationPage({ params, searchParams }: DestinationPageProps) {
  const { locale, slug } = await params;
  const sp = await searchParams;

  const currentPage = sp.page ? parseInt(sp.page, 10) : 1;

  let data;
  try {
    data = await getDestinationTours(slug, {
      locale,
      page: currentPage,
      sort: sp.sort as unknown as SearchParams['sort'],
      category: sp.category,
      price_min: sp.price_min ? parseInt(sp.price_min, 10) : undefined,
      price_max: sp.price_max ? parseInt(sp.price_max, 10) : undefined,
      duration: sp.duration,
    });
  } catch (e) {
    if (e instanceof NotFoundError) {
      notFound();
    }
    throw e;
  }

  const destinationName = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

  return (
    <main className="min-h-screen bg-[#F7F9FB]">
      <div className="bg-white border-b border-gray-200 py-12">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <h1 className="text-3xl font-bold text-[#0A2540]">Tours in {destinationName}</h1>
          <p className="mt-2 text-[#5A6B7B]">{data.meta.total} tour{data.meta.total !== 1 ? 's' : ''} available</p>
        </div>
      </div>

      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="mb-6 flex justify-end">
          <SortDropdown />
        </div>
        <SearchResults tours={data.data} locale={locale} />
        <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} />
      </div>
    </main>
  );
}
