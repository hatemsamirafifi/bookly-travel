import type { Metadata } from 'next';
import { getCategories } from '@/lib/api/categories';
import type { Category } from '@/lib/api/types';
import CategoryGrid from '@/components/home/CategoryGrid';

interface CategoriesPageProps {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  return {
    title: 'Tour Categories | Bookly',
    description: 'Explore tours and activities by category on Bookly.',
    robots: { index: true, follow: true },
  };
}

export default async function CategoriesPage({ params }: CategoriesPageProps) {
  const { locale } = await params;
  let categories: Category[] = [];
  try {
    const res = await getCategories(locale);
    categories = res.data || [];
  } catch {
    categories = [];
  }

  return (
    <div className="min-h-[60vh] bg-white py-8">
      <CategoryGrid categories={categories} locale={locale} title="All Categories" />
    </div>
  );
}