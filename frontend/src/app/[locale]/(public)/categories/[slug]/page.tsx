import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getCategoryTours } from '@/lib/api/categories';
import { NotFoundError } from '@/lib/api/client';
import { parseSort } from '@/lib/validators/search';
import ListingPage from '@/components/search/ListingPage';
import { getTranslations } from 'next-intl/server';

interface CategoryPageProps {
  params: Promise<{ locale: string; slug: string }>;
  searchParams: Promise<{ page?: string; sort?: string; price_min?: string; price_max?: string; duration?: string }>;
}

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { locale, slug } = await params;
  const t = await getTranslations({ locale, namespace: 'listing' });
  const name = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  const title = `${t('categoryTitle', { name })} | Bookly`;

  return {
    title,
    description: t('categoryDescription', { name }),
    robots: { index: true, follow: true },
  };
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { locale, slug } = await params;
  const t = await getTranslations({ locale, namespace: 'listing' });
  const sp = await searchParams;

  const currentPage = sp.page ? parseInt(sp.page, 10) : 1;

  let data;
  try {
    data = await getCategoryTours(slug, {
      locale,
      page: currentPage,
      sort: parseSort(sp.sort),
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

  return <ListingPage title={t('categoryTitle', { name })} data={data} locale={locale} />;
}
