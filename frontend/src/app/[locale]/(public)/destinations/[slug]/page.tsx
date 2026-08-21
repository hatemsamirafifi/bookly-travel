import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getDestinationTours } from '@/lib/api/destinations';
import { NotFoundError } from '@/lib/api/client';
import { parseSort } from '@/lib/validators/search';
import ListingPage from '@/components/search/ListingPage';

interface DestinationPageProps {
  params: Promise<{ locale: string; slug: string }>;
  searchParams: Promise<{ page?: string; sort?: string; category?: string; price_min?: string; price_max?: string; duration?: string }>;
}

export async function generateMetadata({ params }: DestinationPageProps): Promise<Metadata> {
  const { slug } = await params;
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
      sort: parseSort(sp.sort),
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

  const name = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

  return <ListingPage title={`Tours in ${name}`} data={data} locale={locale} />;
}